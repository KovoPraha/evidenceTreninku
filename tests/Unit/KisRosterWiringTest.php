<?php
declare(strict_types=1);
namespace Tests\Unit;
use PHPUnit\Framework\TestCase;
final class KisRosterWiringTest extends TestCase
{
    public function testRosterAdminIsProtectedAndLinked():void
    {
        $root=dirname(__DIR__,2);$admin=(string)file_get_contents($root.'/kis_rosters_admin.php');$service=(string)file_get_contents($root.'/includes/kis_roster.php');self::assertStringContainsString("canAccess('sync_evidence')",$admin);self::assertStringContainsString('csrf_verify',$admin);self::assertStringContainsString('create_series',$admin);self::assertStringContainsString('preview_season_id',$admin);self::assertStringContainsString('kis_rosters_admin.php',(string)file_get_contents($root.'/kis_sync_center.php'));self::assertStringContainsString('kis_rosters_admin.php',(string)file_get_contents($root.'/hlavicka.php'));self::assertStringNotContainsString('DELETE FROM club_roster_members',$service);self::assertStringNotContainsString('INSERT INTO club_roster_members',$this->previewFunction($service));
    }

    private function previewFunction(string$service):string{$start=strpos($service,'function kisRosterPreviewRollover');$end=strpos($service,'function kisRosterEvent',$start);self::assertNotFalse($start);self::assertNotFalse($end);return substr($service,(int)$start,(int)$end-(int)$start);}
}
