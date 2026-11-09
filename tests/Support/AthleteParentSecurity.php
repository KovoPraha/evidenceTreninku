<?php

declare(strict_types=1);

// Included by the guarded CLI driver, never an application route.
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
if (!isset($runtime, $admin, $credentials, $variants, $checksums, $before) || $mode !== 'security') {
    throw new RuntimeException('Owned parent HTTP driver required.');
}
$security = ['complete' => false, 'roles' => [], 'fragments' => [], 'parents' => [], 'checks' => []];
foreach ($variants as $variant) {
    $rt = parentRuntime($runtime, $variant);
    foreach ($credentials as $role => $credential) {
        $label = 'security-' . $variant . '-' . $role;
        $cookie = $output . '/cookie-' . $label . '.txt';
        if (is_file($cookie)) {
            unlink($cookie);
        }
        fullHttpLogin($rt, $output, $label, $credential);
        $r = fullHttpRequest($rt, $output, $label, '/sportovec_treninky.php?id=1001');
        $expected = in_array($role, ['coach', 'sports_lead', 'registrar'], true) ? 200 : 403;
        if ($r['status'] !== $expected) {
            throw new RuntimeException('Parent role matrix differs: ' . $role . ':' . $r['status']);
        }
        if ($expected === 200) {
            $security['parents'][$variant][$role] = parentSignature($r);
        }
        $ajax = fullHttpRequest($rt, $output, $label, '/ajax_sportovec_treninky.php?view=measurements');
        if ($ajax['status'] !== $expected) {
            throw new RuntimeException('AJAX role matrix differs: ' . $role . ':' . $ajax['status']);
        }
        $security['roles'][$variant][$role] = ['parent' => $r['status'], 'ajax' => $ajax['status']];
    }
    $coach = 'security-' . $variant . '-coach';
    foreach (['rok=0', 'rok=' . date('Y'), 'rok=2099', 'rok=0&profile_page=2', 'profile_page=1000000',
        'view=measurements', 'view=measurements&typ=KOLO', 'view=measurements&typ=kolo%20',
        'view=measurements&typ=absent', 'view=measurements&typ=unknown',
        'view=measurements&profile_page=2', 'id=1002&sid=1002'] as $query) {
        $r = fullHttpRequest($rt, $output, $coach, '/ajax_sportovec_treninky.php?' . $query);
        if ($r['status'] !== 200 || preg_match('/Fatal error|Warning:/', $r['body']) || str_contains($r['body'], 'Foreign hidden note')) {
            throw new RuntimeException('Profile fragment failed.');
        }
        if (($r['headers']['referrer-policy'] ?? '') !== 'no-referrer'
            || !str_contains($r['headers']['cache-control'] ?? '', 'no-store')) {
            throw new RuntimeException('Privacy headers changed.');
        }
        $security['fragments'][$variant][$query] = hash('sha256', $r['body']);
    }
    $token = public_profile_access_issue($admin, 1001, 7);
    $bearer = 'security-' . $variant . '-bearer';
    $r = fullHttpRequest($rt, $output, $bearer, '/sportovec_treninky.php?hash=' . $token['token']);
    if ($r['status'] !== 303 || ($r['headers']['location'] ?? '') !== 'sportovec_treninky.php') {
        throw new RuntimeException('Real bearer exchange failed.');
    }
    $security['parents'][$variant]['bearer'] = parentSignature(fullHttpRequest($rt, $output, $bearer, '/sportovec_treninky.php'));
    foreach (['rok=0', 'view=measurements', 'view=measurements&id=1002&sid=1002'] as $query) {
        $r = fullHttpRequest($rt, $output, $bearer, '/ajax_sportovec_treninky.php?' . $query);
        if ($r['status'] !== 200 || preg_match('/class="[^"]*\bsave-note-btn\b/', $r['body'])) {
            throw new RuntimeException('Bearer cannot read or received note write controls.');
        }
        $security['bearer_fragments'][$variant][$query] = hash('sha256', $r['body']);
    }
    $admin->exec("UPDATE public_profile_access_tokens SET expires_at='2000-01-01' WHERE sportovec_id=1001");
    if (fullHttpRequest($rt, $output, $bearer, '/sportovec_treninky.php')['status'] !== 404
        || fullHttpRequest($rt, $output, $bearer, '/ajax_sportovec_treninky.php')['status'] !== 403) {
        throw new RuntimeException('Expired bearer retained access.');
    }
    $admin->exec('UPDATE public_profile_access_tokens SET expires_at=DATE_ADD(CURRENT_TIMESTAMP,INTERVAL 1 DAY) WHERE sportovec_id=1001');
    fullHttpRequest($rt, $output, $bearer, '/sportovec_treninky.php?hash=' . $token['token']);
    $admin->exec('UPDATE public_profile_access_tokens SET revoked_at=CURRENT_TIMESTAMP WHERE sportovec_id=1001');
    if (fullHttpRequest($rt, $output, $bearer, '/sportovec_treninky.php')['status'] !== 404) {
        throw new RuntimeException('Revoked bearer retained parent access.');
    }
    $admin->exec("UPDATE coach_scope_grants SET valid_to='2000-01-01' WHERE trainer_id=7 AND scope_kind='team' AND scope_id=2");
    try {
        if (fullHttpRequest($rt, $output, $coach, '/ajax_sportovec_treninky.php')['status'] !== 403
            || fullHttpRequest($rt, $output, $coach, '/sportovec_treninky.php')['status'] !== 404) {
            throw new RuntimeException('Expired scope retained access.');
        }
    } finally {
        $admin->exec("UPDATE coach_scope_grants SET valid_to=NULL WHERE trainer_id=7 AND scope_kind='team' AND scope_id=2");
    }
    $parent = fullHttpRequest($rt, $output, $coach, '/sportovec_treninky.php?id=1001');
    parentSignature($parent);
    if (!preg_match('/const CSRF_TOKEN\s*=\s*"([^"]+)"/', $parent['body'], $csrf)) {
        throw new RuntimeException('Parent did not persist actual CSRF token.');
    }
    if (fullHttpRequest($rt, $output, $coach, '/logout.php', ['csrf_token' => 'invalid-synthetic-token'])['status'] !== 403) {
        throw new RuntimeException('Invalid logout CSRF accepted.');
    }
    parentSignature(fullHttpRequest($rt, $output, $coach, '/sportovec_treninky.php'));
    if (fullHttpRequest($rt, $output, $coach, '/logout.php', ['csrf_token' => $csrf[1]])['status'] !== 303
        || fullHttpRequest($rt, $output, $coach, '/sportovec_treninky.php')['status'] !== 404
        || fullHttpRequest($rt, $output, $coach, '/ajax_sportovec_treninky.php')['status'] !== 403) {
        throw new RuntimeException('Canonical logout/session persistence changed.');
    }
    fullHttpLogin($rt, $output, $coach, $credentials['coach']);
    parentSignature(fullHttpRequest($rt, $output, $coach, '/sportovec_treninky.php?id=1001'));
    $admin->exec('UPDATE treneri SET aktivni=0 WHERE id=7');
    try {
        $status = fullHttpRequest($rt, $output, $coach, '/sportovec_treninky.php')['status'];
        if (!in_array($status, [302, 303, 401, 403], true)) {
            throw new RuntimeException('Inactive coach retained parent access.');
        }
    } finally {
        $admin->exec('UPDATE treneri SET aktivni=1 WHERE id=7');
    }
    $guest = 'security-' . $variant . '-guest';
    if (fullHttpRequest($rt, $output, $guest, '/sportovec_treninky.php')['status'] !== 404
        || fullHttpRequest($rt, $output, $guest, '/sportovec_treninky.php?hash=invalid')['status'] !== 404
        || fullHttpRequest($rt, $output, $guest, '/ajax_sportovec_treninky.php?id=1001&sid=1001')['status'] !== 403) {
        throw new RuntimeException('Anonymous profile access changed.');
    }
    $security['checks'][$variant] = ['real_login' => true, 'bearer_303' => true, 'expiry_revocation_scope' => true,
        'csrf_preserved' => true, 'csrf_logout' => true, 'inactive_coach_denied' => $status, 'guest_denied' => true];
}
if (count($variants) === 2) {
    foreach (['roles', 'parents', 'fragments', 'bearer_fragments', 'checks'] as $field) {
        if ($security[$field]['before'] !== $security[$field]['after']) {
            throw new RuntimeException('Parent access or exact rendered output differs: ' . $field);
        }
    }
}
if ($checksums() !== $before) {
    throw new RuntimeException('Parent security reads changed domain data.');
}
$security['complete'] = true;
$security['domain_checksums_unchanged'] = true;
file_put_contents($output . '/parent-security.json', json_encode($security, JSON_THROW_ON_ERROR));
echo 'Parent roles, entire DOM, raw fragments, revocation and CSRF PASS.' . PHP_EOL;
