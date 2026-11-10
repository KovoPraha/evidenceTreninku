<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli' || getenv('EVIDENCE_PERFORMANCE_CONFIRM') !== 'SYNTHETIC-ONLY') {
    http_response_code(404);
    exit;
}
$output = realpath($argv[1] ?? '');
if ($output === false) {
    throw new RuntimeException('Owned output required.');
}
$mode = $argv[2] ?? '--compare';
$argv = [__FILE__, '--library'];
require_once __DIR__ . '/FullHttpReadProfile.php';
require_once __DIR__ . '/FamilyAssetsComparison.php';
[$runtime, $admin] = fullHttpEnvironment($output);
$admin->exec('USE `' . $runtime['schema'] . '`');
if ($mode === '--seed') {
    require_once dirname(__DIR__, 2) . '/includes/club_program_terms.php';
    $admin->exec("UPDATE club_event_term_versions SET consent_text_plain='LOCALHOST NEPLATIT synteticky schvalene informace.' "
        . "WHERE scope_type='athlete_registration'");
    foreach (CLUB_PROGRAM_TERM_PURPOSES as $purpose) {
        clubProgramTermsConfigure($admin, 7, 'program', 1, $purpose, 'LOCALHOST NEPLATIT synteticke podminky programu.', true);
        clubProgramTermsConfigure($admin, 7, 'program', 2, $purpose, 'LOCALHOST NEPLATIT priprava podminek.', true);
    }
    [$missingType, $missingKey] = clubProgramTermsScope('program', 2);
    $admin->prepare('UPDATE club_event_term_versions SET consent_text_plain=? WHERE scope_type=? AND scope_key=?')
        ->execute([CLUB_PROGRAM_TERM_DRAFT_MARKER . ' LOCALHOST incomplete terms', $missingType, $missingKey]);
    $admin->exec("UPDATE club_program_offers SET status='active',capacity=20000,starts_on='2020-01-01',ends_on='2099-12-31',"
        . "birth_year_from=2014,birth_year_to=2020 WHERE id IN (1,2)");
    $admin->exec("INSERT INTO shop_catalog_product_candidates(id,run_id,external_product_key,name,offer_type,classification_confidence,needs_manual_review,payload_json) "
        . "VALUES(9001,1,'LOCALHOST-CSS-GOODS','LOCALHOST NEPLATIT zbozi','goods','high',0,'{}')");
    $admin->exec("INSERT INTO shop_products(id,source_candidate_id,source_run_id,external_product_key,name,offer_type,catalog_status) "
        . "VALUES(9001,9001,1,'LOCALHOST-CSS-GOODS','LOCALHOST NEPLATIT zbozi','goods','active')");
    $admin->exec("INSERT INTO shop_product_publications(product_id,status,public_name,public_summary,decision_note) "
        . "VALUES(9001,'active','LOCALHOST NEPLATIT zbozi','Synteticky neverejny test','Approved synthetic fixture')");
    $variants = $admin->prepare('INSERT INTO shop_variants(id,product_id,source_candidate_id,sku,attributes_json,price_mode,amount_minor,currency,'
        . 'includes_vat,vat_rate_basis_points,stock_quantity_decimal,visible,catalog_status) VALUES(?,9001,NULL,?,\'{}\',\'fixed\',?,\'CZK\',1,2100,?,1,\'active\')');
    foreach ([[900101, 'LOCALHOST-CSS-IN', 12500, '5.000000'], [900102, 'LOCALHOST-CSS-EMPTY', 12500, '0.000000'],
        [900103, 'LOCALHOST-CSS-UNKNOWN', 25000, null]] as $variant) {
        $variants->execute($variant);
    }

    foreach ([1,2,3,10,20] as $person) {
        $admin->prepare("INSERT INTO account_person_roles(account_id,sportovec_id,relation_role,status,valid_from,created_by_trainer_id,decision_note) "
            . "VALUES(10,?,'guardian','approved','2020-01-01',7,'LOCALHOST ASSET7')")->execute([$person]);
    }
    $admin->exec("UPDATE sportovci SET jmeno='ASSET7_OWNED_CHILD' WHERE id=1");
    $admin->exec("UPDATE individualni_lekce SET datum=DATE_ADD(CURRENT_DATE,INTERVAL 10 DAY)");
    // The real account overview joins canonical payments; capacity-only fixtures do not create them.
    $admin->exec("INSERT INTO payments(payable_type,payable_id,method,status,amount_minor,currency,variable_symbol,"
        . "iban_snapshot,account_label_snapshot,spd_payload,due_at) "
        . "SELECT 'shop_order',id,'bank_transfer','pending',total_minor,currency,CAST(8980000000+id AS CHAR),"
        . "'CZ-SYNTHETIC','LOCALHOST NEPLATIT','SYNTHETIC DO NOT PAY','2099-12-31 23:59:59' FROM shop_orders WHERE account_id=10");
    $admin->exec("INSERT INTO verejne_rezervace(id,lekce_id,uzivatel_id,stav,slot_cas_od,cas_rezervace) "
        . "VALUES(90001,900001,10,'potvrzena','10:00:00','2026-01-01 10:00:00'),"
        . "(90002,900002,11,'potvrzena','11:00:00','2026-01-01 10:00:00')");
    $credential = ['email' => 'family-assets@example.invalid', 'password' => 'LOCALHOST-Neplatit-' . bin2hex(random_bytes(16))];
    $admin->prepare('UPDATE verejni_uzivatele SET email=?,heslo_hash=?,aktivni=1,email_overeno=1 WHERE id=10')
        ->execute([$credential['email'], password_hash($credential['password'], PASSWORD_DEFAULT)]);

    $credential['cohorts'] = ['main' => ['id' => 10,'email' => $credential['email'],'password' => $credential['password']]];
    foreach (['empty' => [12,1], 'unverified' => [13,0]] as $cohort => [$id,$verified]) {
        $email = 'family-assets-'.$cohort.'@example.invalid';
        $admin->prepare('INSERT INTO verejni_uzivatele(id,jmeno,prijmeni,email,heslo_hash,aktivni,email_overeno,registrovan) '
            . "VALUES(?,'LOCALHOST','NEPLATIT',?,?,1,?,CURRENT_TIMESTAMP)")
            ->execute([$id,$email,password_hash($credential['password'], PASSWORD_DEFAULT),$verified]);
        $credential['cohorts'][$cohort] = ['id' => $id,'email' => $email,'password' => $credential['password']];
    }
    file_put_contents($output . '/family-credential-private.json', json_encode($credential, JSON_THROW_ON_ERROR));
    exit;
}

$normalize = static function (array $response): string {
    $html = str_replace("\r\n", "\n", $response['body']);
    if (preg_match("/nonce-([^']+)/", $response['headers']['content-security-policy'] ?? '', $nonce)) {
        $html = str_replace($nonce[1], 'SYNTHETIC_NONCE', $html);
    }
    if (preg_match_all('/name="csrf_token"\s+value="([a-f0-9]{64})"/', $html, $tokens)) {
        foreach (array_unique($tokens[1]) as $token) {
            $html = str_replace($token, 'SYNTHETIC_CSRF', $html);
        }
    }
    return familyAssetsComparisonHtml($html);
};
$credential = json_decode((string)file_get_contents($output . '/family-credential-private.json'), true, 512, JSON_THROW_ON_ERROR);
$login = [];
foreach (['before','after'] as $variant) {
    $rt = $runtime;
    $rt['base_url'] = 'http://127.0.0.1:'.($variant === 'before' ? 18099 : 18100);
    foreach ($credential['cohorts'] as $cohort => $account) {
        $role = 'family-'.$cohort.'-'.$variant;
        $form = fullHttpRequest($rt, $output, $role, '/booking/prihlaseni.php');
        if ($form['status'] !== 200 || !preg_match('/name="csrf_token"\s+value="([a-f0-9]{64})"/', $form['body'], $token)) {
            throw new RuntimeException('Synthetic family login form failed.');
        }
        $post = fullHttpRequest($rt, $output, $role, '/booking/prihlaseni.php', [
            'csrf_token' => $token[1],'email' => $account['email'],'heslo' => $account['password'],
        ]);
        $expectedLogin = $cohort === 'unverified' ? 200 : 302;
        if ($post['status'] !== $expectedLogin) {
            throw new RuntimeException('Synthetic family login '.$cohort.' status '.$post['status']);
        }
        if ($cohort === 'unverified' && !str_contains($post['body'], 'Email není ověřen.')) {
            throw new RuntimeException('Unverified email login denial absent.');
        }
        $login[$variant][$cohort] = $expectedLogin;
    }
}
$checksums = static function () use ($admin): array {
    $rows = [];
    foreach ($admin->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN) as $table) {
        if (!preg_match('/^[a-zA-Z0-9_]+$/D', $table)) {
            throw new RuntimeException('Unexpected owned table.');
        }
        $rows[$table] = $admin->query('CHECKSUM TABLE `'.$table.'` EXTENDED')->fetch(PDO::FETCH_ASSOC)['Checksum'];
    }
    return $rows;
};
$beforeData = $checksums();
$paths = ['people' => '/booking/moje_osoby.php','orders' => '/booking/moje_objednavky.php',
    'programs' => '/booking/moje_programy.php','reservations' => '/booking/moje_rezervace.php'];
$cases = [];
foreach (['main','empty','unverified'] as $cohort) {
    foreach ($paths as $name => $path) {
        $cases[$cohort.'-'.$name] = [$path,$cohort === 'unverified' ? 302 : 200,$cohort,$cohort !== 'unverified'];
    }
}
foreach ($paths as $name => $path) {
    $cases['anonymous-'.$name] = [$path,302,'anonymous',false];
}
$cases['control'] = ['/booking/produkt.php?id=9001',200,'anonymous',false];
$report = ['complete' => false,'samples' => [],'signatures' => [],'customer_login' => $login,'denials' => []];
$referenceBodies = [];
$signature = static fn (array $response): string => hash('sha256', $response['status']."\n".($response['headers']['location'] ?? '')."\n".$normalize($response));
foreach (range(-4, 16) as $round) {
    foreach ($round % 2 === 0 ? ['before','after'] : ['after','before'] as $variant) {
        $rt = $runtime;
        $rt['base_url'] = 'http://127.0.0.1:'.($variant === 'before' ? 18099 : 18100);
        foreach ($cases as $case => [$path,$status,$cohort,$target]) {
            $response = fullHttpRequest($rt, $output, 'family-'.$cohort.'-'.$variant, $path);
            $body = $response['body'];
            if ($response['status'] !== $status || preg_match('/Fatal error|Warning:/', $body)) {
                file_put_contents($output.'/failure-private.html', $body);
                throw new RuntimeException('Family route failed '.$case.':'.$response['status']);
            }
            if ($status === 200) {
                $local = preg_match_all('~href="/(?!/)[^"]*assets/vendor/bootstrap/bootstrap\.min\.css\?v=[a-f0-9]{64}"~', $body);
                $cdn = substr_count($body, 'https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css');
                if ($local !== 1 || $cdn !== ($variant === 'before' && $target ? 1 : 0)) {
                    throw new RuntimeException('Family CSS count '.$case);
                }
                if (str_contains($body, 'PRIVATE_OTHER_FAMILY')) {
                    throw new RuntimeException('Foreign family data escaped.');
                }
                if ($cohort === 'main') {
                    $sentinel = ['main-people' => 'ASSET7_OWNED_CHILD','main-orders' => 'PROFILE00000000001',
                        'main-programs' => 'Synthetic','main-reservations' => 'LOCALHOST slot 1'][$case];
                    if (!str_contains($body, $sentinel)) {
                        throw new RuntimeException('Nonempty own data absent '.$case);
                    }
                } elseif (in_array($cohort, ['empty','unverified'], true) &&
                    (str_contains($body, 'ASSET7_OWNED_CHILD') || str_contains($body, 'PROFILE00000000001') || str_contains($body, 'LOCALHOST slot 1'))) {
                    throw new RuntimeException('Another account data appeared in '.$case);
                }
            }
            $hash = $signature($response);
            $report['signatures'][$case] ??= $hash;
            $referenceBodies[$case] ??= $normalize($response);
            if ($hash !== $report['signatures'][$case]) {
                $other = $referenceBodies[$case];
                $now = $normalize($response);
                $offset = 0;
                while ($offset < min(strlen($other), strlen($now)) && $other[$offset] === $now[$offset]) {
                    $offset++;
                }
                $safe = static fn (string $s): string => (string)preg_replace('~[A-Za-z0-9+/_-]{32,}~', '[OPAQUE]', $s);
                file_put_contents($output.'/http-difference.json', json_encode(['case' => $case,'offset' => $offset,
                    'before' => $safe(substr($other, max(0, $offset - 70), 240)),'after' => $safe(substr($now, max(0, $offset - 70), 240))], JSON_THROW_ON_ERROR));
                throw new RuntimeException('Whole family HTML differs '.$case.':'.$variant);
            }
            if ($round >= 0) {
                $report['samples'][$case][$variant][] = ['total_ms' => $response['total_ms'],'ttfb_ms' => $response['ttfb_ms']];
            }
        }
        if ($round === -4) {
            foreach (['people','programs','reservations'] as $name) {
                $denied = fullHttpRequest($rt, $output, 'family-main-'.$variant, $paths[$name], ['csrf_token' => 'invalid','action' => 'submit','order_item_id' => 1,'rezervace_id' => 90001]);
                if ($denied['status'] !== 200) {
                    throw new RuntimeException('Invalid CSRF response changed '.$name);
                }
                if ($name !== 'reservations' && !str_contains($denied['body'], 'Formulář vypršel')) {
                    throw new RuntimeException('CSRF rejection absent '.$name);
                }
                // Reservations silently ignore invalid CSRF under their existing contract.
                $report['denials'][$variant][$name] = $signature($denied);
            }
        }
    }
}
if ($report['denials']['before'] !== $report['denials']['after']) {
    throw new RuntimeException('CSRF whole HTML differs.');
}
$relation = $admin->query("SELECT id,status,updated_at FROM account_person_roles WHERE account_id=10 AND sportovec_id=1")->fetch(PDO::FETCH_ASSOC);
try {
    $admin->prepare("UPDATE account_person_roles SET status='revoked' WHERE id=?")->execute([$relation['id']]);
    foreach (['before','after'] as $variant) {
        $rt = $runtime;
        $rt['base_url'] = 'http://127.0.0.1:'.($variant === 'before' ? 18099 : 18100);
        $r = fullHttpRequest($rt, $output, 'family-main-'.$variant, $paths['people']);
        if ($r['status'] !== 200 || str_contains($r['body'], 'ASSET7_OWNED_CHILD')) {
            throw new RuntimeException('Fresh relation revocation not applied.');
        }
        $report['relation_revocation'][$variant] = $signature($r);
    }
} finally {
    $admin->prepare('UPDATE account_person_roles SET status=?,updated_at=? WHERE id=?')
        ->execute([$relation['status'],$relation['updated_at'],$relation['id']]);
}
if ($report['relation_revocation']['before'] !== $report['relation_revocation']['after']) {
    throw new RuntimeException('Relation revocation parity failed.');
}
try {
    $admin->exec('UPDATE verejni_uzivatele SET aktivni=0 WHERE id=12');
    foreach (['before','after'] as $variant) {
        $rt = $runtime;
        $rt['base_url'] = 'http://127.0.0.1:'.($variant === 'before' ? 18099 : 18100);
        $r = fullHttpRequest($rt, $output, 'family-empty-'.$variant, $paths['people']);
        if ($r['status'] !== 303) {
            throw new RuntimeException('Account revocation expected exact303, got'.$r['status']);
        }
        $report['account_revocation'][$variant] = $signature($r);
    }
} finally {
    $admin->exec('UPDATE verejni_uzivatele SET aktivni=1 WHERE id=12');
}
if ($report['account_revocation']['before'] !== $report['account_revocation']['after']) {
    throw new RuntimeException('Account revocation parity failed.');
}
if ($beforeData !== $checksums()) {
    throw new RuntimeException('Read/denial comparison changed domain data.');
}
$report['tables_unchanged'] = count($beforeData);
$report['complete'] = true;
file_put_contents($output.'/family-http.json', json_encode($report, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT));
echo 'Family whole HTML, roles, CSRF/revocation and all domain checksums equal.'.PHP_EOL;
