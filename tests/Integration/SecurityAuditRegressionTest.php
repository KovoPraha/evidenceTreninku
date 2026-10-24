<?php
declare(strict_types=1);

namespace Tests\Integration;

use DOMDocument;
use DOMXPath;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__, 2) . '/includes/staff_workspaces.php';

final class SecurityAuditRegressionTest extends TestCase
{
    public static function permissionChanges(): array
    {
        return [
            'revoked permission, first POST' => ['admin', 'planovana', 0, 1],
            'permission retained, valid POST' => ['trener', 'zrusena', 1, 0],
        ];
    }

    #[DataProvider('permissionChanges')]
    public function testFirstPostUsesCurrentPermissionBeforeCancellingOwnedLesson(
        string $minimumRole, string $expectedState, int $expectedEvents, int $expectedReservations
    ): void {
        $root = dirname(__DIR__, 2);
        $source = (string)file_get_contents($root . '/individualni_lekce_sprava.php');
        // Execute the real controller through its POST redirect, not the GET template.
        $end = strpos($source, '// ── Filtr');
        self::assertNotFalse($end);
        $controller = substr($source, 5, $end - 5);
        // Only replace the MySQL connection seam; use actual auth/session, CSRF,
        // ownership, SQL mutation, transaction and audit functions against SQLite.
        $controller = preg_replace(
            "~require_once (?:__DIR__\\s*\\.\\s*)?'/?db\\.php';~",
            'if (!auth_session_validate($pdo)) { exit; }',
            $controller,
            -1,
            $replacements
        );
        self::assertSame(1, $replacements);
        $controller = str_replace('__DIR__', var_export($root, true), $controller);
        $setup = 'require ' . var_export($root . '/includes/auth_session.php', true) . ';'
            . '$minimumRole=' . var_export($minimumRole, true) . ';' . <<<'PHP'
app_session_start();
$pdo = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$pdo->exec("CREATE TABLE treneri(id INT, role TEXT, aktivni INT, session_version INT);
INSERT INTO treneri VALUES(1,'trener',1,1);
CREATE TABLE opravneni(klic TEXT, min_role TEXT);
CREATE TABLE staff_positions(code TEXT);
CREATE TABLE staff_user_positions(trainer_id INT, position_code TEXT, is_default INT);
INSERT INTO staff_user_positions VALUES(1,'coach',1);
CREATE TABLE staff_superadmins(trainer_id INT);
CREATE TABLE individualni_lekce(id INT, trener_id INT, nazev TEXT, datum TEXT, cas_od TEXT, cas_do TEXT, stav TEXT, booking_context TEXT);
INSERT INTO individualni_lekce VALUES(10,1,'LOCALHOST NEPLATIT','2099-01-01','10:00','11:00','planovana','individual_lesson');
CREATE TABLE verejne_rezervace(id INT, uzivatel_id INT, lekce_id INT, stav TEXT);
CREATE TABLE verejni_uzivatele(id INT, email TEXT, jmeno TEXT);
CREATE TABLE rezervace_sportovist(lekce_id INT);
INSERT INTO rezervace_sportovist VALUES(10);
CREATE TABLE venue_operation_events(target_type TEXT,target_id INT,actor_trainer_id INT,action TEXT,reason TEXT,payload_json TEXT);");
$pdo->prepare('INSERT INTO opravneni VALUES(?,?)')->execute(['individualni_lekce',$minimumRole]);
$_SESSION = ['trener_id'=>1,'trener_session_version'=>1,'role'=>'trener',
    'opravneni'=>['individualni_lekce'=>'trener'],'staff_positions'=>['coach'],
    'staff_active_position'=>'coach','staff_is_superadmin'=>false,'csrf_token'=>'synthetic-csrf'];
$_SERVER['REQUEST_METHOD'] = 'POST';
$_POST = ['action'=>'zrusit_lekci','lekce_id'=>'10','reason'=>'LOCALHOST NEPLATIT','csrf_token'=>'synthetic-csrf'];
register_shutdown_function(static function() use ($pdo): void {
    $result = [
        'state'=>$pdo->query('SELECT stav FROM individualni_lekce WHERE id=10')->fetchColumn(),
        'events'=>(int)$pdo->query('SELECT COUNT(*) FROM venue_operation_events')->fetchColumn(),
        'reservations'=>(int)$pdo->query('SELECT COUNT(*) FROM rezervace_sportovist')->fetchColumn(),
        'fresh_permission'=>$_SESSION['opravneni']['individualni_lekce'],
        'workspace'=>staffActivePosition(),
    ];
    app_session_destroy();
    echo json_encode($result, JSON_THROW_ON_ERROR);
});
PHP;
        $result = $this->runPhp($setup . 'eval(' . var_export($controller, true) . ');');
        self::assertSame($minimumRole, $result['fresh_permission']);
        self::assertSame('coach', $result['workspace']);
        self::assertSame($expectedState, $result['state']);
        self::assertSame($expectedEvents, $result['events']);
        self::assertSame($expectedReservations, $result['reservations']);
    }

    public function testEveryDirectPermissionGuardFollowsDatabaseBootstrap(): void
    {
        $root = dirname(__DIR__, 2);
        $violations = [];
        $checked = 0;
        foreach (glob($root . '/*.php') ?: [] as $path) {
            $source = (string)file_get_contents($path);
            if (!preg_match('~require(?:_once)?[^;]*(?:db|init)\.php[^;]*;~', $source, $bootstrap, PREG_OFFSET_CAPTURE)) continue;
            if (!preg_match('~\bcanAccess\s*\(~', $source, $guard, PREG_OFFSET_CAPTURE)) continue;
            $checked++;
            if ($guard[0][1] < $bootstrap[0][1]) $violations[] = basename($path);
        }
        self::assertGreaterThanOrEqual(27, $checked);
        self::assertSame([], $violations, 'Permission check before live authorization refresh');
    }

    public function testUciWorkbookRoundTripKeepsAllPersonFieldsAsLiteralText(): void
    {
        $root = dirname(__DIR__, 2);
        $source = (string)file_get_contents($root . '/export_uci.php');
        self::assertSame(1, preg_match('~function dobToUci\(.*?\n\}~s', $source, $dateFunction));
        $start = strpos($source, '// ---- Clear rider data rows');
        $end = strpos($source, '// ---- Stream download');
        self::assertNotFalse($start);
        self::assertNotFalse($end);
        $population = substr($source, $start, $end - $start);
        $code = 'require ' . var_export($root . '/vendor/autoload.php', true) . ';'
            . 'use PhpOffice\PhpSpreadsheet\Cell\DataType;'
            . $dateFunction[0] . <<<'PHP'
$spreadsheet = new PhpOffice\PhpSpreadsheet\Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();
$sheet->setCellValue('A1','=2+2'); // An unrelated template formula must survive.
$values = ['=1+1','+123','-123','@SUM(A1)','00123456789',"Žluťoučký O'Connor",'LOCALHOST NEPLATIT'];
$sportovciExport = [];
for ($i=0;$i<11;$i++) $sportovciExport[] = ['prijmeni'=>$values[$i%count($values)],'jmeno'=>$values[($i+1)%count($values)],'uciid'=>$values[($i+4)%count($values)],'narozeni'=>'2000-02-03'];
$directors = [
    ['prijmeni'=>'=1+1','jmeno'=>'+123','uciid'=>'00123456789'],
    ['prijmeni'=>'LOCALHOST NEPLATIT','jmeno'=>'=1+1','uciid'=>'=2+2'],
];
PHP;
        $code .= $population . <<<'PHP'
$path = tempnam(sys_get_temp_dir(),'uci-security-');
try {
    (new PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet))->save($path);
    $loaded = PhpOffice\PhpSpreadsheet\IOFactory::load($path);
    $result = [];
    $expectations = [];
    foreach ($sportovciExport as $i=>$person) {
        $row=$i<8?15+$i:25+$i-8;
        foreach (['K'=>$person['prijmeni'],'L'=>$person['jmeno'],'M'=>'CZE','N'=>'03/02/2000','O'=>$person['uciid']] as $col=>$value) $expectations[$col.$row]=$value;
    }
    foreach ([33=>$directors[0],38=>$directors[0],39=>$directors[1]] as $row=>$person) {
        foreach (['K'=>$person['prijmeni'],'L'=>$person['jmeno'],'M'=>'CZE','N'=>$person['uciid']] as $col=>$value) $expectations[$col.$row]=$value;
    }
    foreach ($expectations as $address=>$expected) {
        $cell=$loaded->getActiveSheet()->getCell($address);
        $result[$address]=['expected'=>$expected,'value'=>$cell->getValue(),'type'=>$cell->getDataType()];
    }
    echo json_encode(['cells'=>$result,'template_formula'=>$loaded->getActiveSheet()->getCell('A1')->getValue()],JSON_THROW_ON_ERROR);
    $loaded->disconnectWorksheets();
} finally { $spreadsheet->disconnectWorksheets(); unlink($path); }
PHP;
        $result = $this->runPhp($code);
        self::assertCount(67, $result['cells']);
        foreach ($result['cells'] as $address => $cell) {
            self::assertSame('s', $cell['type'], $address . ' must be text');
            self::assertSame($cell['expected'], $cell['value'], $address . ' must preserve the literal value');
        }
        self::assertSame('=2+2', $result['template_formula']);
    }

    public static function participantSources(): array
    {
        return [['rosterExpected'], ['makeupExpected']];
    }

    #[DataProvider('participantSources')]
    public function testParticipantJsonCannotCreateHtmlOutsideScript(string $variable): void
    {
        $source = (string)file_get_contents(dirname(__DIR__, 2) . '/formular.php');
        self::assertSame(1, preg_match('~const ' . $variable . ' = <\?= (.*?) \?>;~', $source, $expression));
        $surname = '</script><p id=audit-injected>LOCALHOST NEPLATIT</p>';
        $label = $surname . ' Žluťoučký & "Jan"';
        $planExpectedParticipants = [['id'=>1,'label'=>$label]];
        $planMakeupParticipants = [['sportovec_id'=>1,'prijmeni'=>$surname,'jmeno'=>'Žluťoučký & "Jan"']];
        // Evaluate only the trusted expression from the real template.
        $json = eval('return ' . $expression[1] . ';');
        self::assertSame($planExpectedParticipants, json_decode($json, true, 512, JSON_THROW_ON_ERROR));
        $document = new DOMDocument();
        $previous = libxml_use_internal_errors(true);
        try {
            $document->loadHTML('<html><body><script nonce="test">const ' . $variable . '=' . $json . ';</script></body></html>');
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }
        $xpath = new DOMXPath($document);
        self::assertSame(0, $xpath->query('//*[@id="audit-injected"]')->length);
        self::assertSame(1, $xpath->query('//script')->length);
        self::assertStringNotContainsString('</script', strtolower($json));
    }

    public function testLegacyGroupListRequiresTheSportsWorkspace(): void
    {
        self::assertSame('sports_lead', \staffRouteOwner('list.php'));
        self::assertSame(\staffRouteAllowedPositions('prehled_skupin.php'), \staffRouteAllowedPositions('list.php'));
        foreach (['finance_manager','order_operator','catalog_manager','system_admin','coach'] as $position) {
            self::assertNotContains($position, \staffRouteAllowedPositions('list.php'));
        }
        self::assertNotContains('list.php', \staffSharedRoutes());
    }

    public function testLegacyGroupListGuardRejectsOtherWorkspacesOverHttp(): void
    {
        $root = dirname(__DIR__, 2);
        $listener = stream_socket_server('tcp://127.0.0.1:0', $errno, $error);
        self::assertIsResource($listener, $error);
        $address = stream_socket_get_name($listener, false);
        fclose($listener);
        self::assertIsString($address);
        $router = tempnam(sys_get_temp_dir(), 'staff-guard-router-');
        $log = tempnam(sys_get_temp_dir(), 'staff-guard-log-');
        self::assertIsString($router);
        self::assertIsString($log);
        // Real web-SAPI enforcement, with only synthetic identity data and no DB.
        file_put_contents($router, '<?php $root=' . var_export($root, true) . ';' . <<<'PHP'
require $root.'/includes/staff_workspaces.php';
$_SESSION=['trener_id'=>1,'role'=>'admin','staff_positions'=>staffPositionCodes(),
    'staff_active_position'=>(string)($_GET['position']??''),'staff_is_superadmin'=>true];
$_SERVER['SCRIPT_FILENAME']=$root.'/list.php';
staffEnforceCurrentRoute($root);
echo 'AUTHORIZED_GROUP_QUERY';
PHP);
        $process = proc_open([PHP_BINARY, '-S', $address, $router],
            [['pipe','r'],['file',$log,'a'],['file',$log,'a']], $pipes, $root);
        self::assertIsResource($process);
        fclose($pipes[0]);
        try {
            $deadline = microtime(true) + 5;
            do {
                $connection = @stream_socket_client('tcp://' . $address, $errno, $error, 0.1);
                if (is_resource($connection)) { fclose($connection); break; }
                usleep(20000);
            } while (microtime(true) < $deadline);
            self::assertIsResource($connection, (string)file_get_contents($log));
            foreach (\staffPositionCodes() as $position) {
                $curl = curl_init('http://' . $address . '/list.php?position=' . rawurlencode($position));
                curl_setopt_array($curl, [CURLOPT_RETURNTRANSFER=>true, CURLOPT_TIMEOUT=>5,
                    CURLOPT_PROXY=>'', CURLOPT_FOLLOWLOCATION=>false]);
                $body = curl_exec($curl);
                $status = curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
                $error = curl_error($curl);
                curl_close($curl);
                self::assertIsString($body, $error);
                self::assertSame($position === 'sports_lead' ? 200 : 403, $status, $position);
                if ($position === 'sports_lead') self::assertSame('AUTHORIZED_GROUP_QUERY', $body);
                else self::assertStringNotContainsString('AUTHORIZED_GROUP_QUERY', $body, $position);
            }
        } finally {
            proc_terminate($process);
            proc_close($process);
            unlink($router);
            unlink($log);
        }
    }

    private function runPhp(string $code): array
    {
        $process = proc_open([PHP_BINARY, '-r', $code], [['pipe','r'],['pipe','w'],['pipe','w']], $pipes, dirname(__DIR__, 2));
        self::assertIsResource($process);
        fclose($pipes[0]);
        $stdout = stream_get_contents($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        self::assertSame(0, proc_close($process), $stderr);
        self::assertSame('', $stderr);
        return json_decode($stdout, true, 512, JSON_THROW_ON_ERROR);
    }
}
