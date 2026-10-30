<?php

declare(strict_types=1);

/** Read-only release preflight. Never load db.php, bootstrap, or migration code. */
function clubReleaseImpactIdentity(string $host, array $config): bool
{
    if ($host === 'kis.kovopraha.cz') {
        return ($config['JE_LOKALNE'] ?? null) === false
            && ($config['APP_BASE_URL'] ?? null) === 'https://kis.kovopraha.cz';
    }
    if (!in_array($host, ['localhost', '127.0.0.1', '127.0.0.1:18086'], true)
        || ($config['JE_LOKALNE'] ?? null) !== true) {
        return false;
    }
    $url = parse_url((string)($config['APP_BASE_URL'] ?? ''));
    return is_array($url) && in_array($url['host'] ?? '', ['localhost', '127.0.0.1'], true)
        && in_array($url['scheme'] ?? '', ['http', 'https'], true)
        && !isset($url['user']) && !isset($url['pass']);
}

function clubReleaseImpactPrivateStorage(string $appRoot, mixed $configured, bool $local = false): array
{
    $explicit = is_string($configured) && trim($configured) !== '';
    $path = $explicit ? trim($configured) : ($local ? dirname($appRoot, 2).'/private/evidencePavel' : '');
    $resolved = $path === '' ? false : realpath($path);
    $app = realpath($appRoot);
    $normalize = static fn (string $v): string => strtolower(rtrim(str_replace(
        '\\',
        '/',
        $v
    ), '/'));
    $outside = $resolved !== false && $app !== false && $normalize($resolved) !== $normalize($app)
        && !str_starts_with($normalize($resolved), $normalize($app).'/');
    return ['configured' => $explicit, 'local_fallback' => $local && !$explicit, 'exists' => $resolved !== false && is_dir($resolved),
     'writable' => $resolved !== false && is_writable($resolved),
        'outside_application_tree' => $outside, 'public_webroot_not_proven_by_cli' => true,
     'write_probe_performed' => false];
}

/** Only aggregate counts leave the database. Missing old/new schema is explicit. */
function clubReleaseImpact(PDO $pdo, string $today): array
{
    $date = DateTimeImmutable::createFromFormat('!Y-m-d', $today);
    if (!$date || $date->format('Y-m-d') !== $today || $pdo->inTransaction()) {
        throw new RuntimeException('impact_invalid_context');
    }
    $driver = (string)$pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
    if (!in_array($driver, ['mysql', 'sqlite'], true)) {
        throw new RuntimeException('impact_unsupported_driver');
    }
    $sqliteReadOnly = $driver === 'sqlite' ? (int)$pdo->query('PRAGMA query_only')->fetchColumn() : null;
    if ($driver === 'mysql') {
        $pdo->exec('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');
        $pdo->exec('START TRANSACTION WITH CONSISTENT SNAPSHOT, READ ONLY');
    } else {
        $pdo->exec('PRAGMA query_only=ON');
        $pdo->beginTransaction();
    }
    try {
        $cache = [];
        $columns = static function (string $table) use ($pdo, $driver, &$cache): array {
            if (isset($cache[$table])) {
                return $cache[$table];
            }
            if (!preg_match('/^[a-z_]+$/D', $table)) {
                throw new RuntimeException('impact_invalid_table');
            }
            if ($driver === 'mysql') {
                $q = $pdo->prepare('SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?');
                $q->execute([$table]);
                return $cache[$table] = $q->fetchAll(PDO::FETCH_COLUMN);
            }
            return $cache[$table] = array_column($pdo->query('PRAGMA table_info('.$table
    .')')->fetchAll(PDO::FETCH_ASSOC), 'name');
        };
        $has = static function (string $table, array $required) use ($columns): bool {
            return array_diff($required, $columns($table)) === [];
        };
        $count = static function (string $sql, array $params = []) use ($pdo): int {
            $q = $pdo->prepare($sql);
            $q->execute($params);
            return (int)$q->fetchColumn();
        };
        $checks = [];
        $unknown = static function (string $key) use (&$checks): void {
            $checks[$key] = ['status' => 'unknown_schema', 'count' => null];
        };
        $record = static function (string $key, int $value, string $basis) use (&$checks): void {
            $checks[$key] = ['status' => 'counted', 'count' => $value, 'basis' => $basis];
        };
        $scopeAbsent = $columns('coach_scope_grants') === [];
        $stockAbsent = $columns('shop_stock_balances') === [];
        $agreementsAbsent = $columns('member_fee_agreements') === [];
        $decisionsAbsent = $columns('member_fee_period_decisions') === [];
        $coachBase = "FROM treneri t WHERE t.aktivni=1 AND EXISTS(SELECT 1 FROM staff_user_positions p WHERE p.trainer_id=t.id AND p.position_code='coach')";
        if ($has('treneri', ['id','aktivni']) && $has('staff_user_positions', ['trainer_id',
    'position_code'])) {
            $record(
                'active_staff_with_coach_position',
                $count('SELECT COUNT(*) '.$coachBase),
                'explicit_coach_position'
            );
            if ($scopeAbsent || $has('coach_scope_grants', ['trainer_id','revoked_at','valid_from',
    'valid_to'])) {
                $condition = $scopeAbsent ? '' : (' AND NOT EXISTS(SELECT 1 FROM coach_scope_grants g WHERE g.trainer_id=t.id AND g.revoked_at IS NULL '
 . 'AND g.valid_from<=CURRENT_TIMESTAMP AND (g.valid_to IS NULL OR g.valid_to>CURRENT_TIMESTAMP))');
                $record('coaches_without_current_scope_grant', $count('SELECT COUNT(*) '
    .$coachBase.$condition), $scopeAbsent ? 'scope_table_absent_no_grants_inferred' : 'current_nonrevoked_grant_any_scope');
            } else {
                $unknown('coaches_without_current_scope_grant');
            }
        } else {
            $unknown('active_staff_with_coach_position');
            $unknown('coaches_without_current_scope_grant');
        }
        $goodsBase = ('FROM shop_variants v JOIN shop_products p ON p.id=v.product_id WHERE p.offer_type=\'goods\' AND p.cata'
 . 'log_status=\'active\' AND v.catalog_status=\'active\' AND v.visible=1 AND EXISTS(SELECT 1 FROM shop_prod'
 . 'uct_publications pub WHERE pub.product_id=p.id AND pub.status=\'active\')');
        if ($has('shop_variants', ['id','product_id','catalog_status','visible','stock_quantity_decimal'])
            && $has('shop_products', ['id','offer_type','catalog_status']) && $has(
                'shop_product_publications',
                ['product_id','status']
            )) {
            $record('published_goods_variants', $count('SELECT COUNT(*) '.$goodsBase), 'active_visible_published_goods');
            $record('published_goods_null_legacy_quantity', $count('SELECT COUNT(*) '.$goodsBase
    .' AND v.stock_quantity_decimal IS NULL'), 'new_checkout_rejects_null_stock_quantity');
            if ($stockAbsent || $has('shop_stock_balances', ['variant_id'])) {
                $condition = $stockAbsent ? '' : ' AND NOT EXISTS(SELECT 1 FROM shop_stock_balances b WHERE b.variant_id=v.id)';
                $record('published_goods_without_stock_opening', $count('SELECT COUNT(*) '
    .$goodsBase.$condition), $stockAbsent ? 'stock_table_absent_no_openings_inferred' : 'no_physical_stock_balance_row');
            } else {
                $unknown('published_goods_without_stock_opening');
            }
        } else {
            foreach (['published_goods_variants','published_goods_null_legacy_quantity',
    'published_goods_without_stock_opening'] as $key) {
                $unknown($key);
            }
        }
        $from = $date->format('Y-m-01');
        $to = $date->format('Y-m-t');
        $planBase = "FROM member_fee_plans p WHERE p.status='active' AND p.starts_on<=? AND (p.ends_on IS NULL OR p.ends_on>=?)";
        if ($has('member_fee_plans', ['id','status','starts_on','ends_on'])) {
            $record('active_fee_plans_current_month', $count(
                'SELECT COUNT(*) '.$planBase,
                [$to,$from]
            ), 'active_plans_overlapping_report_month');
            if ($agreementsAbsent || $has('member_fee_agreements', ['plan_id','valid_from',
    'valid_to'])) {
                $condition = $agreementsAbsent ? '' : ' AND NOT EXISTS(SELECT 1 FROM member_fee_agreements a WHERE a.plan_id=p.id AND a.valid_from<=? AND a.valid_to>=?)';
                $record('active_fee_plans_without_any_month_agreement', $count('SELECT COUNT(*) '
    .$planBase.$condition, $agreementsAbsent ? [$to,$from] : [$to,$from,$to,$from]), $agreementsAbsent ? 'agreement_table_absent' : 'no_agreement_overlapping_month_for_any_person');
            } else {
                $unknown('active_fee_plans_without_any_month_agreement');
            }
            if ($decisionsAbsent || $has('member_fee_period_decisions', ['plan_id','period_key'])) {
                $condition = $decisionsAbsent ? '' : ' AND NOT EXISTS(SELECT 1 FROM member_fee_period_decisions d WHERE d.plan_id=p.id AND d.period_key=?)';
                $record(
                    'active_fee_plans_without_any_month_amount_decision',
                    $count('SELECT COUNT(*) '
    .$planBase.$condition, $decisionsAbsent ? [$to,$from] : [$to,$from,$date->format('Y-m')]),
                    $decisionsAbsent ? 'decision_table_absent' : 'no_amount_decision_for_any_person_in_month'
                );
            } else {
                $unknown('active_fee_plans_without_any_month_amount_decision');
            }
        } else {
            foreach (['active_fee_plans_current_month','active_fee_plans_without_any_month_agreement',
    'active_fee_plans_without_any_month_amount_decision'] as $key) {
                $unknown($key);
            }
        }
        if ($has('family_weekly_summaries', ['id','status'])) {
            $record(
                'pending_family_weekly_summaries',
                $count("SELECT COUNT(*) FROM family_weekly_summaries WHERE status='pending'"),
                'pending_queue'
            );
            $snapshotAbsent = $columns('notification_authorization_snapshots') === [];
            if ($snapshotAbsent || $has('notification_authorization_snapshots', ['queue_key',
    'message_id'])) {
                $condition = $snapshotAbsent ? '' : " AND NOT EXISTS(SELECT 1 FROM notification_authorization_snapshots a WHERE a.queue_key='family_weekly_summaries' AND a.message_id=s.id)";
                $record('pending_family_summaries_without_authorization_snapshot', $count("SELECT COUNT(*) FROM family_weekly_summaries s WHERE s.status='pending'"
    .$condition), 'worker_will_hold_missing_snapshot');
            } else {
                $unknown('pending_family_summaries_without_authorization_snapshot');
            }
        } else {
            $unknown('pending_family_weekly_summaries');
            $unknown('pending_family_summaries_without_authorization_snapshot');
        }
        return ['ok' => true, 'read_only' => true, 'read_only_session' => true, 'deployment_ready' => false,
     'as_of' => $today, 'month' => $date->format('Y-m'),
            'complete' => !in_array('unknown_schema', array_column($checks, 'status'), true),

            'checks' => $checks,
            'limitations' => [
                'Counts are an impact inventory, not permission to deploy or evidence of complete operating readiness.',

                'A current coach grant is not proof of coverage of every assigned athlete or historical training.',

                'A stock balance row is not proof of an approved physical inventory or valid valuation.',

                ('One agreement or amount decision does not prove coverage of all members, all days, or a valid payer;'
 . ' plans with partial coverage need separate review.'),
                'No roles, payers, amounts, stock quantities, or source identities are inferred or changed.',

            ]];
    } finally {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        if ($driver === 'sqlite') {
            $pdo->exec('PRAGMA query_only='.($sqliteReadOnly ? 'ON' : 'OFF'));
        }
    }
}


/** Bounded read-only release evidence. Values of secrets and personal records never leave this process. */
function integrationReleaseEvidence(PDO $pdo, string $root): array
{
    $manifest = json_decode(('{"old_sha":"a23e393f64eeffafd75df834e6e5ba8c78213b72","new_sha":"b13e9f7c788f7d22ee08162868c907d14ca'
 . '66144","old":{"accounting_ledger_admin.php":"f46bd610e8ca14540a714f25cc176776761501b22f9e14bf24d601c'
 . 'f23c7e8fe","assets/app-ui.css":"4c53b75476aba47ed091fb20d3fe826c3194d5b77fcf42608729ad4501d36792","a'
 . 'ssets/app-ui.js":"3360c75b10356b06e3661488c8965acc551183003ae1d45a99f14ee082e352b1","bin/db-backup.p'
 . 'hp":"d4de39cf97c6545e70139973d76a2e157f314609465c25bb3c71e35a1f84a047","bin/seed-local-demo.php":"ef'
 . '7a9412703c2ec20250cfc6f6b231c9ad6f6d031ec78eb928043febc0d05ade","booking/eshop.php":"eeadb32e4314118'
 . 'a1de9a2437a6e26a3d0dc80201a174885904ef259e60180e6","booking/moje_objednavky.php":"0493f78a48a449f127'
 . '1e5e22ab45d0c45add5ef53c2bebe23e1f8dd3e2f5246d","booking/moje_rezervace.php":"9926771ebe102371620299'
 . '5b43de53803d3499ea6b2a98f7d5915b3f3250ef23","booking/objednavka.php":"664e6dc2d8f78c66628e08938da5fa'
 . '63489883fdd4ca428cf455ef50f4c07c2b","booking/sportovni_prehled.php":"24f586fe9d9346010b1b53a1118f5dc'
 . '55f3f059ee0134ad83f89824c13465adf","booking/velodrom.php":"eb113e4f24330904187261a7af891ed0633289889'
 . 'a6250d0fff649dcd3c2d019","booking/waiting_list.php":"a6d499c65052f293cee3d80dcd82c29103be049376d933a'
 . '8f0de670655bf83d8","edit_trenink.php":"475e3e60575b52d783468a264cf326c3f067ee5b30ff1aa8a00aba977979e'
 . '5e0","edit_zavod_form.php":"f1884bd7a056df336691a2f0fe41f389214785e107288715fa1f3099edf2bea6","eshop'
 . '_admin.php":"02bc62e069a666047267ba35fc863ca69bb22fffe9fb88f05b2d2df5e7baba86","eshop_events_admin.p'
 . 'hp":"989eeae441223467bf31bc1d93ec2f3e9a7f4b4c2fe83d56c2ed6828baa9ecda","formular.php":"58a2036baa70d'
 . 'f8a54972078f3678502d440924d32465e59a9f7d4a6fa0ce1fa","formular_zavod.php":"226f3b802c874349f3b7fc4b7'
 . 'ab3939c6d661c606f5221af2649dfbc91c22ace","hlavicka.php":"632e02d97dd4db850e8b17d393243923037a2a7873f'
 . '7cebb5eea4fbd4da3d777","includes/family_annual_paid_overview.php":"d0e344a2fef488590d5c7b1f8394a567b'
 . 'd1dc615ea40fa8be42cdc373f3bc38d","includes/kis_review_handoff.php":null,"includes/localhost_acceptan'
 . 'ce_hub.php":"51ac8d0f6f616863dad4f3d306fe275657cc8208c800816300cd6c676987c904","includes/m2_finaliza'
 . 'tion.php":"c034c67d6b7ac0b1330ddebded964124afc0af813f8a267553ad507ee8e8a63e","includes/member_charge'
 . '_read.php":"dec3bb9df8b8bb041775e2fd0fd46cab920dc38837cc47dd6309c903ec50d462","includes/public_velod'
 . 'rome.php":"cf2be2c9f3195cbd36a0579d6912873298ecc57ca1ece9557ea1735c55d57051","includes/race_results.'
 . 'php":null,"includes/race_results_table.php":null,"includes/shop_payment_presentation.php":null,"incl'
 . 'udes/staff_workspaces.php":"610ab65369d699a8e39215751fbf7d9a29b84811dd5975fd199ffc91d4f8932d","inclu'
 . 'des/ui_shell.php":"835b3fe193a4c2acaf0cc8b46ead960c90a22478922c29b8726f5887f650c6fa","includes/venue'
 . '_operations.php":"fbf48c0ae94b5e411bbb19b3d842b7e70ffee687186448ab96ebb134b6e35b9a","individualni_le'
 . 'kce_sprava.php":"201d830c1563c767eed9f579231114a9370c3a2eec20415ae73a4262d4e914c0","kis_review_admin'
 . '.php":null,"kis_sync_center.php":"4a8bd690943dd638a1a8ff64f0d24ecfaa9cf741f8ff57d9d97a8c4c3e366d33",'
 . '"member_charges_admin.php":"25d044e8450e1156eb5f74547bbf248b312428b1a60cca22c88ddd9c47484d9d","migra'
 . 'tions/20261025230000_race_result_revisions.php":null,"migrations/20261025231000_kis_review_handoff.p'
 . 'hp":null,"planovac.php":"f61782968047ae1a0ec3660d4658d4b748e228c8c7c166ee0fc53f182e5c7cd3","prehled_'
 . 'trenera.php":"18f4425533d1b70e396c2264116aa9bdb40b876c4cbe12f5e0ea9e20c5576588","shop_returns_admin.'
 . 'php":"a6728591e8d1f0469fb4ad1c04640a4bf37e55101a0ca0cb2a2efc6cb148f6c9","sync_evidence.php":"e4edbc9'
 . '597fae932b7b611f3360a1eb5fea48cecde694cadc4e09c6a1a08f48c","update_trenink.php":"e0c4793aa1eb795c3bd'
 . '5afbdfb3a002df4474f25c7a4297eec2d575d74552fca","zavod_detail.php":"9ba6a4321bcb6367e0fc8aea9b6b3fa26'
 . '86c19c8899c97ee149ab511c7040330","zavod_vysledky.php":null},"new":{"accounting_ledger_admin.php":"71'
 . '09f41bf5a552752a1e1f335fc5db0acc6ab1da87de658cc074d5e5dafbd236","assets/app-ui.css":"664a0949bcd1360'
 . '92129192140c607bfd36234d85f3c8739dbf762d2a6de8189","assets/app-ui.js":"d39d60fa7b642b0fb8f87fedf0017'
 . '0c6eb50a6e2e35ae0c7b113719d76ba0805","bin/db-backup.php":"b74d7d56e06442d8032f89753d65259fdbcdf3d832'
 . 'a04c2bdfd8061e70ec15f2","bin/seed-local-demo.php":"2bce45bc32e2dae588a94b674ab48167a3f82487cc0501ee0'
 . 'fdb47e7345ceae7","booking/eshop.php":"6fee701ee6e722f6c8e1e95296b5131724ffc4c8799b6c0c4ff678cf4bd17c'
 . '4a","booking/moje_objednavky.php":"8da9a5d362a1dba04fd5c6d80c6bb983517e568bc1c3c830f834c5106d59235b"'
 . ',"booking/moje_rezervace.php":"873118912e7bceb07f3f78bb4c7c7901d10d334c21b7e11a81f2e4bd74fcebc4","bo'
 . 'oking/objednavka.php":"097c1f5ad8629b41d833f93f3b61e65adf18aa409243669a77d5684e95046f26","booking/sp'
 . 'ortovni_prehled.php":"127861e7cb69af82ed56a3459428967e1a2095649c34a5a97027b8e7974f21b3","booking/vel'
 . 'odrom.php":"6f6fd6aee596b59d5aa53b39fa95166e611f7afc9613f35c0059c902a6130e8c","booking/waiting_list.'
 . 'php":"b2dc5795d815c5b27e368f6ef766627fbfdfa592449db9b81244f6c824f74691","edit_trenink.php":"30f39837'
 . '4b23c5e0cb4cda770e69e4064bbaf6236f2723c2095fa8e9797ffa7d","edit_zavod_form.php":"3575367360d8c4c6731'
 . '29cd16171f9f312d8dbbf7dd8beca09d33ab8fa56e5bb","eshop_admin.php":"10fb65d8261e9757146b7c4bbbd239b658'
 . '5f212676f04f20e8e7470be96b64c6","eshop_events_admin.php":"f6118e7a33cd5c3a104838f605f2e0b558de3a9dfb'
 . '12b3097544205ece7e3a5e","formular.php":"5686bc9c70af936929a226f46c97eea083742f8c5a07884d741a57dbea41'
 . '14f5","formular_zavod.php":"6b91e4bd428f787708d2cc89832b97e650c75de4d266447ac8811bf9edc9aa90","hlavi'
 . 'cka.php":"07cd6187de3e34ccc7f349f96de62411532945221104d19aa37e0211b1c543fb","includes/family_annual_'
 . 'paid_overview.php":"2f0c169c189ed4964472f390f96067eb8c385ee8edb1335a7518492db47e2157","includes/kis_'
 . 'review_handoff.php":"a2fd5b0656b2ff28afbd9c9b87c6e8fa656359ed5cf1b2e796ce9420e69fad41","includes/loc'
 . 'alhost_acceptance_hub.php":"497ecd4ce9c7b61a4d21d9d34dde0dad3a6f40bf02fb15192f0570aa8be393b6","inclu'
 . 'des/m2_finalization.php":"09af32175cc201baa7317b544bfc8209344dbc64fa843d08e4c8a1fab15fd12e","include'
 . 's/member_charge_read.php":"2955e0cfc88057b241c5efaa1d3328cbf7b0986b8c55e9aa378b2c11739e40cd","includ'
 . 'es/public_velodrome.php":"d45e201d26d6abe8597433035f93f2b82fbb54f5d07962ac43686ce22e2a6bc1","include'
 . 's/race_results.php":"8bc08d7d83e0bbf40a9f7ddbdeff71bf1a15ece0cda55664fd8bb7aba5485a37","includes/rac'
 . 'e_results_table.php":"3d5f0d529761452e055e65b0154a4fc0ff6046bb7c236ee01187d340b8c48d47","includes/sh'
 . 'op_payment_presentation.php":"a22eea9b61ef43f7a75595d22cf1f418e924f98d88872d1253b02927bf5bc117","inc'
 . 'ludes/staff_workspaces.php":"bbd7fe37f51a57abce3a3aba22f6f5954efa5327f461eddd0e9a60021ffa7be5","incl'
 . 'udes/ui_shell.php":"15ac46d46549563c6757fc2832872fbbad22ebd0352150ec19a00de03a7cd4ff","includes/venu'
 . 'e_operations.php":"be3f625004a22e0f943160fc2fea8d12f18d38f0a2083e684c78626a44d5bc64","individualni_l'
 . 'ekce_sprava.php":"c3896d7cbfb05c3b93e56a1f79c334f7210c3b38e293fe24ab6e603200de3052","kis_review_admi'
 . 'n.php":"278e2dec7a7b9700b601fab0d771094bd6ff98b3142e61b542948cfcc0b96004","kis_sync_center.php":"68c'
 . 'a195e9cd9934b96f8e5e1cfcb160f4fc07392018283a63476452fc4c4ae08","member_charges_admin.php":"84182bc60'
 . 'd317533b090b006915035b5571321fa0c94bffffe1d4062da571abf","migrations/20261025230000_race_result_revi'
 . 'sions.php":"942407ab4cf3e691df29d4aead993e89e7047312d3c057d9ce2ec226518bd27e","migrations/2026102523'
 . '1000_kis_review_handoff.php":"390599d8453e57538f7cc42dc82d010a85fcffd2acd38afbc5a081faca1cddb6","pla'
 . 'novac.php":"5fb85bf28042a53dbdc64eb495d0cd50289954588a58815ffc9077385e82f823","prehled_trenera.php":'
 . '"be7388548144c809df260c83249a4ffe2bdfe105b8c32738f054ea9445e0d28b","shop_returns_admin.php":"6408a47'
 . '85020602a44f84c86241f59715763f88f30fff29b4bd7a81bd0e07c01","sync_evidence.php":"784424f6b4ccb598fc64'
 . 'e7b425a1b7577d3527e45a861ea0f07a14e2da3a1425","update_trenink.php":"65b1eff7fc9d54bfaa5fb4a5df08e05e'
 . '0556280e0f28cb946a6d9a7edc386db6","zavod_detail.php":"cca9088e02d75b6e549ce0d4dd09b4e5897441de73256b'
 . '52c16f2c722345cf21","zavod_vysledky.php":"de45d4d31d209c2b3dc69d6cb6e749829a99f35d68f089b3610aa0f2ca'
 . '5676ec"}}'), true, 512, JSON_THROW_ON_ERROR);
    $hashes = static function (string $dir, array $expected): array {
        $rows = [];
        $bad = 0;
        foreach ($expected as $path => $hash) {
            $actual = is_file($dir.'/'.$path) ? hash_file('sha256', $dir.'/'.$path) : null;
            $ok = $actual === $hash;
            $bad += (int)!$ok;
            $rows[] = ['file' => $path,'match' => $ok];
        }
        return ['checked' => count($rows),'mismatches' => $bad,'files' => $rows];
    };
    $metadata = static function (string $dir): array {
        $path = $dir.'/var/deployment.json';
        if (!is_file($path)) {
            return [];
        }
        $m = json_decode(file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
        return array_intersect_key($m, array_flip(['sha','run_id','deployed_at','uat_approved']));
    };
    $result = ['live' => $metadata($root),'old_source' => $hashes($root, $manifest['old']),'new_source' => $hashes(
        $root,
        $manifest['new']
    ),
        'config_sha256' => hash_file('sha256', $root.'/config.php')];
    $previous = __DIR__.'/releases/'.$manifest['old_sha'].'-40198413027';
    $result['previous_release'] = ['available' => is_dir($previous),'metadata' => $metadata($previous),
    'source' => $hashes($previous, $manifest['old'])];
    $flags = [];
    foreach (['FIO_IMPORT_ENABLED','FIO_AUTO_CONFIRM_ENABLED','SUMUP_ENABLED','STRIPE_ENABLED',
    'VELOCOTA_INTEGRATION','PACKETA_ENABLED'] as $key) {
        $flags[$key] = defined($key) ? (bool)constant($key) : null;
    }
    $result['integration_flags'] = $flags;
    $pdo->exec('START TRANSACTION WITH CONSISTENT SNAPSHOT, READ ONLY');
    try {
        $states = ['movable' => 0,'missing' => 0,'already_private' => 0,'invalid' => 0];
        foreach ([['ucto_uctenky','obrazek_path','uploads/uctenky'],['zatezove_testy_soubory',
    'cesta','uploads/zatezove_testy'],['ucto_servis','dokument','uploads/servis']] as [$table,
        $column,$prefix]) {
            foreach ($pdo->query("SELECT `$column` FROM `$table` WHERE `$column` IS NOT NULL AND `$column`<>''") as $row) {
                $value = (string)$row[$column];
                if (str_starts_with($value, 'private://')) {
                    $states['already_private']++;
                    continue;
                }
                $value = str_replace('\\', '/', ltrim($value, '/'));
                $base = basename($value);
                if (!str_starts_with($value, $prefix.'/') || $base === '' || $base !== substr(
                    $value,
                    strlen($prefix) + 1
                )) {
                    $states['invalid']++;
                    continue;
                }
                $states[is_file($root.'/'.$prefix.'/'.$base) ? 'movable' : 'missing']++;
            }
        }
        $result['private_migration_candidates'] = $states;
        $result['kis_canonical_write_enabled'] = defined('JE_LOKALNE') && JE_LOKALNE === true;
        $result['new_tables'] = [];
        foreach (['race_result_states','race_participants','race_result_revisions','kis_review_plans',
    'kis_review_events','kis_review_mutex'] as $table) {
            $q = $pdo->prepare('SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?');
            $q->execute([$table]);
            $result['new_tables'][$table] = $q->fetchColumn() ? (int)$pdo->query('SELECT COUNT(*) FROM '
    .$table)->fetchColumn() : null;
        }
        $result['migration_ledger_count'] = (int)$pdo->query('SELECT COUNT(*) FROM evidence_schema_migrations')->fetchColumn();
        $result['forward_vs_repair_applied'] = (bool)$pdo->query("SELECT 1 FROM evidence_schema_migrations WHERE id='20261024200000_payment_variable_symbol_index_repair'")->fetchColumn();
    } finally {
        $pdo->rollBack();
    }
    $result['domain_mutations'] = false;
    return $result;
}

/** Bounded read-only inventory; web free space does not prove remote DB free space. */
function clubReleaseStorageInventory(string $root, ?PDO $pdo): array
{
    $report = ['web_host' => gethostname(), 'web_free_bytes' => @disk_free_space($root),
        'web_total_bytes' => @disk_total_space($root), 'php_temp_free_bytes' => @disk_free_space(sys_get_temp_dir()),
        'database' => null, 'release_directories' => [], 'backup_files' => 0, 'backup_bytes' => 0,
        'inventory_complete' => true, 'mutations' => false];
    if ($pdo !== null) {
        try {
            $report['database'] = $pdo->query('SELECT @@hostname AS host,@@datadir AS data_directory,@@tmpdir AS temp_directory')->fetch(PDO::FETCH_ASSOC);
        } catch (Throwable) {
            $report['database'] = ['identity_unavailable' => true];
        }
    }
    $base = dirname($root) . '/data';
    $deadline = microtime(true) + 5;
    $visited = 0;
    $directories = glob($base . '/.kis-deploy/releases/*', GLOB_ONLYDIR) ?: [];
    $report['release_directory_count'] = count($directories);
    foreach ($directories as $directory) {
        if (is_link($directory)) {
            continue;
        }
        $entry = ['name' => basename($directory), 'mtime' => filemtime($directory), 'files' => 0, 'bytes' => 0, 'complete' => true];
        try {
            $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS));
            foreach ($files as $file) {
                if (++$visited > 200000 || microtime(true) > $deadline) {
                    $entry['complete'] = $report['inventory_complete'] = false;
                    break;
                }
                if ($file->isFile() && !$file->isLink()) {
                    $entry['files']++;
                    $entry['bytes'] += $file->getSize();
                }
            }
        } catch (Throwable) {
            $entry['complete'] = $report['inventory_complete'] = false;
        }
        $report['release_directories'][] = $entry;
        if (microtime(true) > $deadline || $visited > 200000) {
            break;
        }
    }
    foreach (glob($base . '/.kis-backups/*') ?: [] as $file) {
        if (is_file($file) && !is_link($file)) {
            $report['backup_files']++;
            $report['backup_bytes'] += filesize($file);
        }
    }
    return $report;
}

function clubReleaseImpactMain(): int
{
    if (PHP_SAPI !== 'cli') {
        return 2;
    }
    ini_set('display_errors', '0');
    $rootInput = (string)getenv('APP_ROOT');
    $root = $rootInput === '' ? false : realpath($rootInput);
    $host = (string)getenv('APP_HOST');
    if (getenv('CLUB_RELEASE_IMPACT_CONFIRM') !== 'OVERIT-DOPAD-VYDANI' || !$root || !is_file($root
    .'/config.php') || !in_array(
        $host,
        ['kis.kovopraha.cz','localhost','127.0.0.1','127.0.0.1:18086'],
        true
    )) {
        echo "{\"ok\":false,\"error\":\"impact_invalid_target\"}\n";
        return 2;
    }
    $_SERVER['HTTP_HOST'] = $host;
    $_SERVER['SERVER_NAME'] = explode(':', $host)[0];
    ob_start();
    try {
        require $root.'/config.php';
    } catch (Throwable) {
        ob_end_clean();
        echo "{\"ok\":false,\"error\":\"impact_config_failed\"}\n";
        return 2;
    }
    ob_end_clean();
    $config = [];
    foreach (['JE_LOKALNE','APP_BASE_URL','DB_HOST','DB_NAME','DB_USER','DB_PASS'] as $key) {
        if (!defined($key)) {
            echo "{\"ok\":false,\"error\":\"impact_config_incomplete\"}\n";
            return 2;
        }
        $config[$key] = constant($key);
    }
    if (!clubReleaseImpactIdentity($host, $config)) {
        echo "{\"ok\":false,\"error\":\"impact_identity_mismatch\"}\n";
        return 2;
    }
    $phase = 'connection';
    try {
        $pdo = new PDO(
            'mysql:host='.$config['DB_HOST'].';dbname='.$config['DB_NAME'].';charset=utf8mb4',
            $config['DB_USER'],
            $config['DB_PASS'],
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
     PDO::ATTR_EMULATE_PREPARES => false]
        );
        if ($host !== 'kis.kovopraha.cz') {
            $identity = $pdo->query('SELECT @@port AS port,DATABASE() AS db')->fetch(PDO::FETCH_ASSOC);
            if ((int)$identity['port'] !== 33426 || $identity['db'] !== 'evidence_b1') {
                throw new RuntimeException('impact_wrong_local_database');
            }
        }
        $phase = 'domain_impact';
        $report = clubReleaseImpact($pdo, (new DateTimeImmutable('now', new DateTimeZone('Europe/Prague')))->format('Y-m-d'));
        $phase = 'private_storage';
        $storage = getenv('APP_PRIVATE_STORAGE_ROOT');
        if ($storage === false || trim($storage) === '') {
            $storage = defined('APP_PRIVATE_STORAGE_ROOT') ? constant('APP_PRIVATE_STORAGE_ROOT') : null;
        }
        $report['private_storage'] = clubReleaseImpactPrivateStorage($root, $storage, $config['JE_LOKALNE'] === true);
        $phase = 'release_evidence';
        $report['release_verification'] = integrationReleaseEvidence($pdo, $root);
        echo json_encode($report, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
    ."\n";
        return 0;
    } catch (Throwable $exception) {
        echo json_encode(
            ['ok' => false,'error' => 'impact_probe_failed','phase' => $phase,
            'exception_class' => get_class($exception),'source' => basename($exception->getFile()),

            'line' => $exception->getLine(),'sqlstate' => $exception instanceof PDOException ? (string)$exception->getCode() : null,

            'driver_code' => $exception instanceof PDOException ? ($exception->errorInfo[1] ?? null) : null,
            'storage' => clubReleaseStorageInventory($root, isset($pdo) ? $pdo : null)],
            JSON_THROW_ON_ERROR
        )."\n";
        return 2;
    }
}

if (realpath((string)($_SERVER['SCRIPT_FILENAME'] ?? '')) === __FILE__) {
    exit(clubReleaseImpactMain());
}
