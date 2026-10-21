<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {http_response_code(404);exit;}

/** No identifiers, symbols or personal data leave the process, only digests. */
function shopVsPreservationSnapshot(PDO $pdo, ?array $bounds=null): array
{
    $tables=[
        'payments'=>['id','payable_type','payable_id','variable_symbol'],
        'member_fee_standing_orders'=>['id','plan_id','sportovec_id','variable_symbol'],
        'member_payer_variable_symbols'=>['payer_account_id','variable_symbol'],
    ];
    if ($bounds!==null) {
        if (array_keys($bounds)!==array_keys($tables)) throw new InvalidArgumentException('Invalid snapshot bounds.');
        foreach ($bounds as $value) if (!is_int($value)||$value<0) throw new InvalidArgumentException('Invalid snapshot bounds.');
    }
    if ($pdo->inTransaction()) throw new LogicException('Snapshot requires a separate read transaction.');
    if ($pdo->getAttribute(PDO::ATTR_DRIVER_NAME)==='mysql') $pdo->exec('SET TRANSACTION READ ONLY');
    $pdo->beginTransaction();
    try {
        $result=['format'=>1,'bounds'=>[],'tables'=>[]];
        foreach ($tables as $table=>$columns) {
            $key=$columns[0];$max=$bounds[$table]??(int)$pdo->query('SELECT COALESCE(MAX('.$key.'),0) FROM '.$table)->fetchColumn();
            $s=$pdo->prepare('SELECT '.implode(',',$columns).' FROM '.$table.' WHERE '.$key.'<=? ORDER BY '.$key);$s->execute([$max]);
            $hash=hash_init('sha256');$count=0;
            while ($row=$s->fetch(PDO::FETCH_NUM)) {
                hash_update($hash,json_encode(array_map(static fn(mixed $v):?string=>$v===null?null:(string)$v,$row),JSON_THROW_ON_ERROR)."\n");$count++;
            }
            $result['bounds'][$table]=$max;$result['tables'][$table]=['count'=>$count,'sha256'=>hash_final($hash)];
        }
        $pdo->commit();return $result;
    } catch (Throwable $e) {if($pdo->inTransaction())$pdo->rollBack();throw $e;}
}

function shopVsPreservationMain(): void
{
    try {
        if (getenv('APP_HOST')!=='kis.kovopraha.cz') throw new RuntimeException('Invalid target.');
        $root=realpath((string)getenv('SHOP_VS_APP_ROOT'));
        if (!$root||!is_file($root.'/config.php')) throw new RuntimeException('Missing configuration.');
        ob_start();try {require $root.'/config.php';} finally {ob_end_clean();}
        if (!defined('JE_LOKALNE')||JE_LOKALNE!==false||!defined('APP_BASE_URL')||APP_BASE_URL!=='https://kis.kovopraha.cz') throw new RuntimeException('Invalid production identity.');
        $pdo=new PDO('mysql:host='.DB_HOST.';dbname='.DB_NAME.';charset=utf8mb4',DB_USER,DB_PASS,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_EMULATE_PREPARES=>false]);
        $baseline=null;$encoded=(string)getenv('SHOP_VS_BASELINE_BASE64');
        if ($encoded!=='') {
            $decoded=base64_decode($encoded,true);if($decoded===false)throw new InvalidArgumentException('Invalid baseline.');
            $baseline=json_decode($decoded,true,32,JSON_THROW_ON_ERROR);
            if (!is_array($baseline)||($baseline['ok']??false)!==true||($baseline['snapshot']['format']??0)!==1||!is_array($baseline['snapshot']['bounds']??null)||!is_array($baseline['snapshot']['tables']??null)) throw new InvalidArgumentException('Invalid baseline.');
        }
        $snapshot=shopVsPreservationSnapshot($pdo,$baseline['snapshot']['bounds']??null);
        $unchanged=$baseline===null?null:($snapshot===$baseline['snapshot']);
        echo json_encode(['ok'=>$unchanged!==false,'target'=>'kis.kovopraha.cz','unchanged'=>$unchanged,'snapshot'=>$snapshot],JSON_THROW_ON_ERROR|JSON_UNESCAPED_SLASHES),"\n";
        if ($unchanged===false) exit(3);
    } catch (Throwable $e) {
        // Driver/config errors may include credentials; never expose messages.
        fwrite(STDERR,'Variable symbol preservation probe failed: '.get_class($e)."\n");exit(2);
    }
}

if (!defined('SHOP_VS_PRESERVATION_LIBRARY_ONLY')) shopVsPreservationMain();
