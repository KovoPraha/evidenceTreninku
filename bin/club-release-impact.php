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
        || ($config['JE_LOKALNE'] ?? null) !== true) return false;
    $url = parse_url((string)($config['APP_BASE_URL'] ?? ''));
    return is_array($url) && in_array($url['host'] ?? '', ['localhost', '127.0.0.1'], true)
        && in_array($url['scheme'] ?? '', ['http', 'https'], true)
        && !isset($url['user']) && !isset($url['pass']);
}

function clubReleaseImpactPrivateStorage(string $appRoot, mixed $configured, bool $local = false): array
{
    $explicit = is_string($configured) && trim($configured) !== '';
    $path = $explicit ? trim($configured) : ($local ? dirname($appRoot, 2).'/private/evidencePavel' : '');
    $resolved = $path === '' ? false : realpath($path); $app = realpath($appRoot);
    $normalize = static fn(string $v): string => strtolower(rtrim(str_replace('\\', '/', $v), '/'));
    $outside = $resolved !== false && $app !== false && $normalize($resolved) !== $normalize($app)
        && !str_starts_with($normalize($resolved), $normalize($app).'/');
    return ['configured'=>$explicit, 'local_fallback'=>$local && !$explicit, 'exists'=>$resolved !== false && is_dir($resolved), 'writable'=>$resolved !== false && is_writable($resolved),
        'outside_application_tree'=>$outside, 'public_webroot_not_proven_by_cli'=>true, 'write_probe_performed'=>false];
}

/** Only aggregate counts leave the database. Missing old/new schema is explicit. */
function clubReleaseImpact(PDO $pdo, string $today): array
{
    $date = DateTimeImmutable::createFromFormat('!Y-m-d', $today);
    if (!$date || $date->format('Y-m-d') !== $today || $pdo->inTransaction()) {
        throw new RuntimeException('impact_invalid_context');
    }
    $driver = (string)$pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
    if (!in_array($driver, ['mysql', 'sqlite'], true)) throw new RuntimeException('impact_unsupported_driver');
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
            if (isset($cache[$table])) return $cache[$table];
            if (!preg_match('/^[a-z_]+$/D', $table)) throw new RuntimeException('impact_invalid_table');
            if ($driver === 'mysql') {
                $q = $pdo->prepare('SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?');
                $q->execute([$table]);
                return $cache[$table] = $q->fetchAll(PDO::FETCH_COLUMN);
            }
            return $cache[$table] = array_column($pdo->query('PRAGMA table_info('.$table.')')->fetchAll(PDO::FETCH_ASSOC), 'name');
        };
        $has = static function (string $table, array $required) use ($columns): bool {
            return array_diff($required, $columns($table)) === [];
        };
        $count = static function (string $sql, array $params = []) use ($pdo): int {
            $q = $pdo->prepare($sql); $q->execute($params); return (int)$q->fetchColumn();
        };
        $checks = [];
        $unknown = static function (string $key) use (&$checks): void {
            $checks[$key] = ['status'=>'unknown_schema', 'count'=>null];
        };
        $record = static function (string $key, int $value, string $basis) use (&$checks): void {
            $checks[$key] = ['status'=>'counted', 'count'=>$value, 'basis'=>$basis];
        };
        $scopeAbsent = $columns('coach_scope_grants') === [];
        $stockAbsent = $columns('shop_stock_balances') === [];
        $agreementsAbsent = $columns('member_fee_agreements') === [];
        $decisionsAbsent = $columns('member_fee_period_decisions') === [];
        $coachBase = "FROM treneri t WHERE t.aktivni=1 AND EXISTS(SELECT 1 FROM staff_user_positions p WHERE p.trainer_id=t.id AND p.position_code='coach')";
        if ($has('treneri', ['id','aktivni']) && $has('staff_user_positions', ['trainer_id','position_code'])) {
            $record('active_staff_with_coach_position', $count('SELECT COUNT(*) '.$coachBase), 'explicit_coach_position');
            if ($scopeAbsent || $has('coach_scope_grants', ['trainer_id','revoked_at','valid_from','valid_to'])) {
                $condition = $scopeAbsent ? '' : " AND NOT EXISTS(SELECT 1 FROM coach_scope_grants g WHERE g.trainer_id=t.id AND g.revoked_at IS NULL AND g.valid_from<=CURRENT_TIMESTAMP AND (g.valid_to IS NULL OR g.valid_to>CURRENT_TIMESTAMP))";
                $record('coaches_without_current_scope_grant', $count('SELECT COUNT(*) '.$coachBase.$condition), $scopeAbsent ? 'scope_table_absent_no_grants_inferred' : 'current_nonrevoked_grant_any_scope');
            } else $unknown('coaches_without_current_scope_grant');
        } else { $unknown('active_staff_with_coach_position'); $unknown('coaches_without_current_scope_grant'); }
        $goodsBase = "FROM shop_variants v JOIN shop_products p ON p.id=v.product_id WHERE p.offer_type='goods' AND p.catalog_status='active' AND v.catalog_status='active' AND v.visible=1 AND EXISTS(SELECT 1 FROM shop_product_publications pub WHERE pub.product_id=p.id AND pub.status='active')";
        if ($has('shop_variants', ['id','product_id','catalog_status','visible','stock_quantity_decimal'])
            && $has('shop_products', ['id','offer_type','catalog_status']) && $has('shop_product_publications', ['product_id','status'])) {
            $record('published_goods_variants', $count('SELECT COUNT(*) '.$goodsBase), 'active_visible_published_goods');
            $record('published_goods_null_legacy_quantity', $count('SELECT COUNT(*) '.$goodsBase.' AND v.stock_quantity_decimal IS NULL'), 'new_checkout_rejects_null_stock_quantity');
            if ($stockAbsent || $has('shop_stock_balances', ['variant_id'])) {
                $condition = $stockAbsent ? '' : ' AND NOT EXISTS(SELECT 1 FROM shop_stock_balances b WHERE b.variant_id=v.id)';
                $record('published_goods_without_stock_opening', $count('SELECT COUNT(*) '.$goodsBase.$condition), $stockAbsent ? 'stock_table_absent_no_openings_inferred' : 'no_physical_stock_balance_row');
            } else $unknown('published_goods_without_stock_opening');
        } else { foreach (['published_goods_variants','published_goods_null_legacy_quantity','published_goods_without_stock_opening'] as $key) $unknown($key); }
        $from = $date->format('Y-m-01'); $to = $date->format('Y-m-t');
        $planBase = "FROM member_fee_plans p WHERE p.status='active' AND p.starts_on<=? AND (p.ends_on IS NULL OR p.ends_on>=?)";
        if ($has('member_fee_plans', ['id','status','starts_on','ends_on'])) {
            $record('active_fee_plans_current_month', $count('SELECT COUNT(*) '.$planBase, [$to,$from]), 'active_plans_overlapping_report_month');
            if ($agreementsAbsent || $has('member_fee_agreements', ['plan_id','valid_from','valid_to'])) {
                $condition = $agreementsAbsent ? '' : ' AND NOT EXISTS(SELECT 1 FROM member_fee_agreements a WHERE a.plan_id=p.id AND a.valid_from<=? AND a.valid_to>=?)';
                $record('active_fee_plans_without_any_month_agreement', $count('SELECT COUNT(*) '.$planBase.$condition, $agreementsAbsent ? [$to,$from] : [$to,$from,$to,$from]), $agreementsAbsent ? 'agreement_table_absent' : 'no_agreement_overlapping_month_for_any_person');
            } else $unknown('active_fee_plans_without_any_month_agreement');
            if ($decisionsAbsent || $has('member_fee_period_decisions', ['plan_id','period_key'])) {
                $condition = $decisionsAbsent ? '' : ' AND NOT EXISTS(SELECT 1 FROM member_fee_period_decisions d WHERE d.plan_id=p.id AND d.period_key=?)';
                $record('active_fee_plans_without_any_month_amount_decision', $count('SELECT COUNT(*) '.$planBase.$condition, $decisionsAbsent ? [$to,$from] : [$to,$from,$date->format('Y-m')]), $decisionsAbsent ? 'decision_table_absent' : 'no_amount_decision_for_any_person_in_month');
            } else $unknown('active_fee_plans_without_any_month_amount_decision');
        } else { foreach (['active_fee_plans_current_month','active_fee_plans_without_any_month_agreement','active_fee_plans_without_any_month_amount_decision'] as $key) $unknown($key); }
        if ($has('family_weekly_summaries', ['id','status'])) {
            $record('pending_family_weekly_summaries', $count("SELECT COUNT(*) FROM family_weekly_summaries WHERE status='pending'"), 'pending_queue');
            $snapshotAbsent = $columns('notification_authorization_snapshots') === [];
            if ($snapshotAbsent || $has('notification_authorization_snapshots', ['queue_key','message_id'])) {
                $condition = $snapshotAbsent ? '' : " AND NOT EXISTS(SELECT 1 FROM notification_authorization_snapshots a WHERE a.queue_key='family_weekly_summaries' AND a.message_id=s.id)";
                $record('pending_family_summaries_without_authorization_snapshot', $count("SELECT COUNT(*) FROM family_weekly_summaries s WHERE s.status='pending'".$condition), 'worker_will_hold_missing_snapshot');
            } else $unknown('pending_family_summaries_without_authorization_snapshot');
        } else { $unknown('pending_family_weekly_summaries'); $unknown('pending_family_summaries_without_authorization_snapshot'); }
        return ['ok'=>true, 'read_only'=>true, 'read_only_session'=>true, 'deployment_ready'=>false, 'as_of'=>$today, 'month'=>$date->format('Y-m'),
            'complete'=>!in_array('unknown_schema', array_column($checks, 'status'), true),
            'checks'=>$checks,
            'limitations'=>[
                'Counts are an impact inventory, not permission to deploy or evidence of complete operating readiness.',
                'A current coach grant is not proof of coverage of every assigned athlete or historical training.',
                'A stock balance row is not proof of an approved physical inventory or valid valuation.',
                'One agreement or amount decision does not prove coverage of all members, all days, or a valid payer; plans with partial coverage need separate review.',
                'No roles, payers, amounts, stock quantities, or source identities are inferred or changed.',
            ]];
    } finally {
        if ($pdo->inTransaction()) $pdo->rollBack();
        if ($driver === 'sqlite') $pdo->exec('PRAGMA query_only='.($sqliteReadOnly ? 'ON' : 'OFF'));
    }
}


/** Bounded read-only release evidence. Values of secrets and personal records never leave this process. */
function integrationReleaseEvidence(PDO $pdo,string $root):array {
    $manifest=json_decode('{"old_sha":"6bf57d6fe4c20fd9d9e182d3bde4253b1abc4a51","new_sha":"00a438dc29fa0faa8414c6ab308e1d6269b1e187","old":{"assets/app-ui.css":"71524d835495be81f2afe82ba9ff5fffacc24d1362790c16e882aba765e6888f","booking/sportovni_prehled.php":"b17da69bf20fbc3d7d06640eb980192b73562e472e8105ad9803d93dfa98d482","club_calendar.php":"3e42cd78962e3b3a1f12320e01162075826750a1f2ac6927c3d7cf7e0dec4e85","club_communication_admin.php":null,"club_cutover_admin.php":"6af1a45b1b3473ae453cb436291b90e2d8aee27c5f14c247bd531d5599753bc8","club_event_work_admin.php":null,"club_expenses_admin.php":"063f16148c81ca7aff08b302176bbeb1ee895ab5bb7223a908a7cf474d8640d3","club_rewards_admin.php":"38ce821406a6032489b8605bb2fcc3ef8babd1d6cc3ca911b8041c4acbace618","family_weekly_summaries_admin.php":"c045317fa1543541d33d0cfdf370759a779d55e441e517fb8683edac8048ca06","hromadne_odmeny.php":"7a57e4e2e36833b028025ede1fec8e0b299b15ad55fa5c6b6a7c417f27e83b9f","includes/athlete_reward_guard.php":"c30ea1ae0fccdb0823afedf3e73ac34f03e10ae19a12c039ef13a19b0c0382dd","includes/club_administration.php":null,"includes/club_administration_view.php":null,"includes/club_communication.php":null,"includes/club_cutover.php":"e3db9b5def37c63100dd69c2d7c93f53ffec02327e52f60204467a4cdcb529b8","includes/club_event_work.php":null,"includes/club_transition_map.php":null,"includes/club_transition_view.php":null,"includes/family_tasks.php":null,"includes/family_tasks_view.php":null,"includes/medical_activity.php":null,"includes/medical_clearance.php":"e5506112d12eabb6444ebdce6f1522c035068d0812054f2a47ef5ab6cec3f662","includes/migration_runner.php":"7d199e9aed45e41411a71de9df7e6c789da8638fb83f1ae06633112331e78410","includes/operational_cases.php":"06c195862120fa8d532be16aebcc6a8f256a937cade6dc984fb5ba981306bc2b","includes/operational_cases_view.php":"188c96c4231dc749ab9fe86bae452a4fc366a04f043b583d9fb5f84ec8cc1969","includes/payment_variable_symbol_schema.php":null,"includes/person_lifecycle.php":null,"includes/person_workflow.php":null,"includes/person_workflow_view.php":null,"includes/roster_message.php":"696867f8b1b42ddcb7ce36a6a768724fa7bf88aad44222ad3f3734470e11a1b1","includes/season_preparation.php":null,"includes/sportovec_editor_view.php":"27ad76cbfff7a228773c9145b079617545cd6a8eb542a5445f9158426d96ea51","includes/sports_activity_form.php":null,"includes/sports_activity_form_view.php":null,"includes/staff_agenda_lists.php":null,"includes/staff_record_list.php":null,"includes/staff_workspaces.php":"675945fe8c3c9af6a0d98f1d41642d282dc3b67e342f7cb18c28caf9d533230f","includes/training_day.php":null,"includes/training_day_view.php":null,"medical_activity_admin.php":null,"medical_clearance_admin.php":"b69b2f8fee6b4ef54069717a96d2419f6d59d06677219ac87d3ade0dd700e3a6","migrations/20261024200000_payment_variable_symbol_index_repair.php":null,"person_lifecycle_admin.php":null,"planovac.php":"46e2c9260e0fa66b932e417675e5abb59a58a51c40c8f5257e92730b498c4a26","prehled_kreditu.php":"4f7d51e2218e616ee51097c56d1970b08e07f7eb600e6768b92c3047d86cb7ce","roster_messages_admin.php":"a3b8fb76f3bb3db650c510ea29a160514369fe26447eca55bae2a6c8edb79af3","season_preparation_admin.php":null,"sports_activity_admin.php":"1175f1a3c2cb72d4e1e22796f8cae484e752bf05938fb1d1f9c59ac90d860234","sprava_sportovec_obdobi.php":"f023c0aa205e0d8745a90bef3accf272e813be65f93852fe42aa5ca8ca31f9f0","training_closure_admin.php":"f32c1eae6245c538b94c585525bf57f5d5484926c8662ac195ffaee2e17449aa","training_rsvps_admin.php":"8a8c95cddaaea80a6996065e0393e4d6a2ecf69e3a56d1a030208231aa3f4555","uctenky/formular.php":"d0aaea592a6d0a431495be4af9c0c0d5a7231191d2a2a3c3cfc9403bd35bc795","uctenky/uloz.php":"5a3d01c188a3a65c9dbab4535ca94020de72b600a1c1f26c12f543672d9ecbe2","ulozit_trenink.php":"959cf24c3da55a4f95632cc9de0a8c2e3a06726c01a68bccf44440ee3bc7d5b0"},"new":{"assets/app-ui.css":"baf15a4c3874aec22005bcf0797974326ea66fea670f88b2088d4079fed83f54","booking/sportovni_prehled.php":"24f586fe9d9346010b1b53a1118f5dc55f3f059ee0134ad83f89824c13465adf","club_calendar.php":"13f3006c12b3f8d3b40ae7ab5aa3666a59a7967740895199ce22f938fb8ab30d","club_communication_admin.php":"db57160de6b854f51bc6cc86d23dbf063e308395131b7fb43e4e6b20a239d61c","club_cutover_admin.php":"1b0ff372324eef219cbdb6d368143281c90cd5ab68ec162b0fd00b92b135cd5c","club_event_work_admin.php":"23f38ef40c42211ca8687f33bf9eba868b458c47dba52e117736c04e1837db0e","club_expenses_admin.php":"036a7a4cb6fd8f82695ddaebe7ee1697eeac2796e7d54f0b3985e0d707977580","club_rewards_admin.php":"c9c999574fea3dd256f791ef610695a822e874e8785d664129ba69514f27be75","family_weekly_summaries_admin.php":"5e440267b317c965fa13388f8a2d71cf4bd3cfa76ffdddd1e23597111215404f","hromadne_odmeny.php":"a4a30bf62ea49dd59ce92d10974d5c8ec9728143e8c71f8d64d2f6dac734663a","includes/athlete_reward_guard.php":"7a2411e8913a87b8575d2aee4c19242c33f9f161a67a0a5dbdd48d3aa557136a","includes/club_administration.php":"9f59e65c0e9de617479caab27989be9456aec0655ecbeba5bd38c63c88d766b8","includes/club_administration_view.php":"96c8c25164823558ed6ec685fc53722790dc347ce26fb8d84cb20f3f7d44e05b","includes/club_communication.php":"f4e173566393be441bd298f829261a331a48b1dd2f075d9152b0785ffb15c329","includes/club_cutover.php":"6ad29506982b89b6ab2e738ab8a93f8b0a9a94b2973974bebcddcb3dc49464de","includes/club_event_work.php":"dd798be4e43b4c38224e6c8f9f6974aa40509f84a652472b9941bdcfe97af2d1","includes/club_transition_map.php":"3c6ecdf15c6beb51484fbc20ad0078e0238b8dcf1ebca058288f8e10c110be85","includes/club_transition_view.php":"3ad7098b22d2667d566967aa573fbdefe3934331de715cfc38bba30eeb2100fb","includes/family_tasks.php":"bdcb5f15ecb415a3b52b851cb09bee5222dfab7c10d0d34af98227ff29218708","includes/family_tasks_view.php":"fa63f552e2cc1bd0b1c6a540711779edd9aa7697a11d9071839ab301fe8b364b","includes/medical_activity.php":"ed50920160443b50845769b73482f0186bca85e72422a790645cadd5533f444f","includes/medical_clearance.php":"2c1a307f0dc9d566e57fcbbbb183dc525080f57aa4b334936b6728267425dd6d","includes/migration_runner.php":"b56ee161971b6059b94050051144b72be5e361270ccc4777a8b772ff7760f361","includes/operational_cases.php":"e015d3749689c2e6f3f0633fc7429c8c8836c7503ef321e948fae043512d2ecc","includes/operational_cases_view.php":"fc8dec87ef7a983c19c9c03a0510975496ed96f24e6760a1ae2eceb71f27ebd7","includes/payment_variable_symbol_schema.php":"80b68f458e21e3d503fd04ffaae1796e2be5021bf3b190474addd712a0af174c","includes/person_lifecycle.php":"58a93db8cd39ea9076706273f7a7d36e25dc7b5ddb42079c318746119e98c960","includes/person_workflow.php":"a261516237d0f626d1f1be258d4bca07486ebaacbde4971c4c6a1b69dccfd1d9","includes/person_workflow_view.php":"f4b16914d04e15156a50b7afc42cdffb314d0db3f38bf3cc005c16a36efd0eb9","includes/roster_message.php":"55252f9622aa058a01b9c0182a15b30b3e5eabcc878cd4809b19ae55625cc76f","includes/season_preparation.php":"f58ded4e5f07685cb1b0f91e3cc56eff844d31d628a3f451cc7d0a888b893265","includes/sportovec_editor_view.php":"06d0bfc40ecab81c6c4cb6949a5b1cb2fd1dfd87b8dec283a4e90218810ecb71","includes/sports_activity_form.php":"686ea4f554e24e53a0fc2aa53d3cce35e7f86c6f0ae2b0718fe53ec3bd4891b3","includes/sports_activity_form_view.php":"21f3abb289472326a361927ab36ecb0039632bcce550d46f57c2435a0abf35ab","includes/staff_agenda_lists.php":"a1c4b99069557b3c31f587fcf4265d238b5e74ead53a1a67a5c8af7283389a31","includes/staff_record_list.php":"8bc50843e58aa51fcd5671e2263ebac06a86af3834da11083bdff1394b060c8b","includes/staff_workspaces.php":"610ab65369d699a8e39215751fbf7d9a29b84811dd5975fd199ffc91d4f8932d","includes/training_day.php":"caa7a46c2bd1f85456cf704c60a750fc427bc124f6d4c7192f0a6054cbfc87eb","includes/training_day_view.php":"624f64a4fb947ba425a8bf79644a22d8e3967a4692bf33b13e91f47e7c183422","medical_activity_admin.php":"537ecf57512956c7b1981b7838be90ec73d6176d22b8f03fde12022747b0d522","medical_clearance_admin.php":"784a5f6a2ea8d0b06aac2d1566cf73cae29ac4ae1bbe15cb530ad3f5b5b3174e","migrations/20261024200000_payment_variable_symbol_index_repair.php":"8581dc0bac96d6990eb5cdc56e28f6f80ae1047c3e60eb138f51f6c02be6d6f0","person_lifecycle_admin.php":"15638ab4acaada0ee48368ff85cb5f898fce3e248fbcb434ea4700dc7784209e","planovac.php":"f61782968047ae1a0ec3660d4658d4b748e228c8c7c166ee0fc53f182e5c7cd3","prehled_kreditu.php":"626fd92f864ff4b83af2f45bd3eec6b9cd965101a9583cf50a6330b10edb5923","roster_messages_admin.php":"158117ef4d285dcd18655293f65cfa56489928f23629968eb5aa741ad675113b","season_preparation_admin.php":"30c812c55e18ad9f2ff93ed750895aa4905c7a7d8d8ef077374ae573566f067f","sports_activity_admin.php":"6c3fb6cbd7e8fc67ea85644b5e3b82e9cd8c29f8d176c760e64ffb26f222ddd7","sprava_sportovec_obdobi.php":"7c5e91728c6ede27adc8f64487c00d14192de95288eb8228f07a53893eef4656","training_closure_admin.php":"379bc80743a6dc9a18ac1c8fc99227be0582bae7072afa5d40f517695e193d52","training_rsvps_admin.php":"4d12f6d6e2dbf0c2f970dbe83f6b887e5a0bbbddb9a7fb7e1325e03f1aa365b2","uctenky/formular.php":"7443ccfb569cbce7c02c2bebaad57ca402dd813a5ffa58409140c3d79817fbc0","uctenky/uloz.php":"dd4be0d8aa156cfb311861af6f5bb1ab94e7e27834aafb0c5b82c98cafc57c9c","ulozit_trenink.php":"a5d467710047e3adf2acf2eb17b7786a749b6576fc55fda3b91539af7304107a"}}',true,512,JSON_THROW_ON_ERROR);
    $hashes=static function(string $dir,array $expected):array {
        $rows=[];$bad=0;
        foreach($expected as $path=>$hash){
            $actual=is_file($dir.'/'.$path)?hash_file('sha256',$dir.'/'.$path):null;
            $ok=$actual===$hash;$bad+=(int)!$ok;
            $rows[]=['file'=>$path,'match'=>$ok];
        }
        return ['checked'=>count($rows),'mismatches'=>$bad,'files'=>$rows];
    };
    $metadata=static function(string $dir):array {
        $path=$dir.'/var/deployment.json';if(!is_file($path))return [];
        $m=json_decode(file_get_contents($path),true,512,JSON_THROW_ON_ERROR);
        return array_intersect_key($m,array_flip(['sha','run_id','deployed_at','uat_approved']));
    };
    $result=['live'=>$metadata($root),'old_source'=>$hashes($root,$manifest['old']),'new_source'=>$hashes($root,$manifest['new']),
        'config_sha256'=>hash_file('sha256',$root.'/config.php')];
    $previous=__DIR__.'/releases/'.$manifest['old_sha'].'-40131981680';
    $result['previous_release']=['available'=>is_dir($previous),'metadata'=>$metadata($previous),'source'=>$hashes($previous,$manifest['old'])];
    $flags=[];
    foreach(['FIO_IMPORT_ENABLED','FIO_AUTO_CONFIRM_ENABLED','SUMUP_ENABLED','STRIPE_ENABLED','VELOCOTA_INTEGRATION','PACKETA_ENABLED'] as $key){$flags[$key]=defined($key)?(bool)constant($key):null;}
    $result['integration_flags']=$flags;
    $pdo->exec('START TRANSACTION WITH CONSISTENT SNAPSHOT, READ ONLY');
    try {
        $states=['movable'=>0,'missing'=>0,'already_private'=>0,'invalid'=>0];
        foreach([['ucto_uctenky','obrazek_path','uploads/uctenky'],['zatezove_testy_soubory','cesta','uploads/zatezove_testy'],['ucto_servis','dokument','uploads/servis']] as [$table,$column,$prefix]){
            foreach($pdo->query("SELECT `$column` FROM `$table` WHERE `$column` IS NOT NULL AND `$column`<>''") as $row){
                $value=(string)$row[$column];if(str_starts_with($value,'private://')){$states['already_private']++;continue;}
                $value=str_replace('\\','/',ltrim($value,'/'));$base=basename($value);
                if(!str_starts_with($value,$prefix.'/')||$base===''||$base!==substr($value,strlen($prefix)+1)){$states['invalid']++;continue;}
                $states[is_file($root.'/'.$prefix.'/'.$base)?'movable':'missing']++;
            }
        }
        $result['private_migration_candidates']=$states;
        $result['migration_ledger_count']=(int)$pdo->query('SELECT COUNT(*) FROM evidence_schema_migrations')->fetchColumn();
        $result['forward_vs_repair_applied']=(bool)$pdo->query("SELECT 1 FROM evidence_schema_migrations WHERE id='20261024200000_payment_variable_symbol_index_repair'")->fetchColumn();
    }finally{$pdo->rollBack();}
    $result['domain_mutations']=false;
    return $result;
}

function clubReleaseImpactMain(): int
{
    if (PHP_SAPI !== 'cli') return 2;
    ini_set('display_errors', '0');
    $rootInput = (string)getenv('APP_ROOT'); $root = $rootInput === '' ? false : realpath($rootInput); $host = (string)getenv('APP_HOST');
    if (getenv('CLUB_RELEASE_IMPACT_CONFIRM') !== 'OVERIT-DOPAD-VYDANI' || !$root || !is_file($root.'/config.php') || !in_array($host, ['kis.kovopraha.cz','localhost','127.0.0.1','127.0.0.1:18086'], true)) {
        echo "{\"ok\":false,\"error\":\"impact_invalid_target\"}\n"; return 2;
    }
    $_SERVER['HTTP_HOST'] = $host; $_SERVER['SERVER_NAME'] = explode(':', $host)[0];
    ob_start();
    try { require $root.'/config.php'; }
    catch (Throwable) { ob_end_clean(); echo "{\"ok\":false,\"error\":\"impact_config_failed\"}\n"; return 2; }
    ob_end_clean();
    $config=[]; foreach (['JE_LOKALNE','APP_BASE_URL','DB_HOST','DB_NAME','DB_USER','DB_PASS'] as $key) {
        if (!defined($key)) { echo "{\"ok\":false,\"error\":\"impact_config_incomplete\"}\n"; return 2; }
        $config[$key]=constant($key);
    }
    if (!clubReleaseImpactIdentity($host, $config)) { echo "{\"ok\":false,\"error\":\"impact_identity_mismatch\"}\n"; return 2; }
    try {
        $pdo = new PDO('mysql:host='.$config['DB_HOST'].';dbname='.$config['DB_NAME'].';charset=utf8mb4', $config['DB_USER'], $config['DB_PASS'],
            [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC, PDO::ATTR_EMULATE_PREPARES=>false]);
        if ($host !== 'kis.kovopraha.cz') {
            $identity=$pdo->query('SELECT @@port AS port,DATABASE() AS db')->fetch(PDO::FETCH_ASSOC);
            if ((int)$identity['port'] !== 33426 || $identity['db'] !== 'evidence_b1') throw new RuntimeException('impact_wrong_local_database');
        }
        $report=clubReleaseImpact($pdo, (new DateTimeImmutable('now', new DateTimeZone('Europe/Prague')))->format('Y-m-d'));
        $storage = getenv('APP_PRIVATE_STORAGE_ROOT');
        if ($storage === false || trim($storage) === '') $storage = defined('APP_PRIVATE_STORAGE_ROOT') ? constant('APP_PRIVATE_STORAGE_ROOT') : null;
        $report['private_storage']=clubReleaseImpactPrivateStorage($root, $storage, $config['JE_LOKALNE'] === true);
        $report['release_verification']=integrationReleaseEvidence($pdo,$root);
        echo json_encode($report, JSON_THROW_ON_ERROR|JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES)."\n";
        return 0;
    } catch (Throwable) { echo "{\"ok\":false,\"error\":\"impact_probe_failed\"}\n"; return 2; }
}

if (realpath((string)($_SERVER['SCRIPT_FILENAME'] ?? '')) === __FILE__) exit(clubReleaseImpactMain());
