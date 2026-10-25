<?php
declare(strict_types=1);
namespace Tests\Integration;
use PDO;
use PHPUnit\Framework\TestCase;
require_once dirname(__DIR__,2).'/includes/season_preparation.php';
require_once dirname(__DIR__,2).'/includes/roster_message.php';
require_once dirname(__DIR__,2).'/includes/club_communication.php';
require_once dirname(__DIR__,2).'/includes/club_cutover.php';

final class SeasonOperationsTest extends TestCase {
    private function db():PDO {
        $p=new PDO('sqlite::memory:',null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
        $p->exec("CREATE TABLE treneri(id INTEGER PRIMARY KEY,jmeno TEXT,aktivni INTEGER);INSERT INTO treneri VALUES(1,'LOCALHOST Registrář',1),(2,'LOCALHOST Trenér',1),(3,'LOCALHOST Správce',1),(4,'LOCALHOST Vedoucí',1),(5,'LOCALHOST Obsluha',1);
        CREATE TABLE staff_user_positions(trainer_id INTEGER,position_code TEXT);INSERT INTO staff_user_positions VALUES(1,'registrar'),(2,'coach'),(3,'system_admin'),(4,'sports_lead'),(5,'order_operator');CREATE TABLE staff_superadmins(trainer_id INTEGER);
        CREATE TABLE sportovci(id INTEGER PRIMARY KEY,jmeno TEXT,prijmeni TEXT,narozeni TEXT,uciid TEXT,stav_clenstvi TEXT);INSERT INTO sportovci VALUES(10,'LOCALHOST Anna','Alpha','2012-01-01',NULL,'aktivni'),(11,'LOCALHOST Bára','Beta','2013-01-01',NULL,'aktivni'),(12,'LOCALHOST Cyril','Gama','2014-01-01',NULL,'aktivni');
        CREATE TABLE coach_scope_grants(id INTEGER PRIMARY KEY,trainer_id INTEGER,scope_kind TEXT,scope_id INTEGER,valid_from TEXT,valid_to TEXT,revoked_at TEXT);
        CREATE TABLE planovane_treninky(id INTEGER PRIMARY KEY,nazev TEXT,datum TEXT,cas_od TEXT,cas_do TEXT,trener_id INTEGER,stav TEXT);CREATE TABLE training_roster_links(plan_id INTEGER,team_id INTEGER);
        CREATE TABLE verejni_uzivatele(id INTEGER PRIMARY KEY,email TEXT,jmeno TEXT,prijmeni TEXT,aktivni INTEGER,email_overeno INTEGER);INSERT INTO verejni_uzivatele VALUES(20,'parent@example.invalid','LOCALHOST','Parent',1,1),(21,'other@example.invalid','LOCALHOST','Other',1,1);
        CREATE TABLE account_person_roles(id INTEGER PRIMARY KEY,account_id INTEGER,sportovec_id INTEGER,relation_role TEXT,status TEXT,valid_from TEXT,valid_to TEXT);");
        foreach(['20260804090000_kis_teams_rosters','20260804110000_kis_roster_policies','20260804170000_kis_roster_rollover_execution','20260822170000_club_roster_structure_events','20261021090000_roster_hierarchy_temporal','20260922130000_roster_messages','20261023090000_club_cutover'] as $name){$m=require dirname(__DIR__,2).'/migrations/'.$name.'.php';$m['up']($p);}
        foreach([['ROOT','Hlavní','group'],['CHILD','Podskupina','roster'],['FUTURE','Budoucí','roster']] as [$code,$name,$kind])\kisRosterCreateStandingTeam($p,1,'LOCALHOST-'.$code,'LOCALHOST '.$name,$kind,'LOCALHOST fixture');
        $this->actor(1,'registrar');return $p;
    }
    private function actor(int $id,string $role):void {$_SESSION=['trener_id'=>$id,'staff_positions'=>[$role],'staff_active_position'=>$role];}
    protected function tearDown():void {$_SESSION=[];}
    private function member(PDO $p,int $team,int $person,string $from,?string $to=null):void {$p->prepare("INSERT INTO club_roster_members(team_id,sportovec_id,status,source,valid_from,valid_to,created_by_trainer_id) VALUES(?,?,'active','manual',?,?,1)")->execute([$team,$person,$from,$to]);}
    private function edge(PDO $p,int $child,string $from,?string $to=null):void {$p->prepare("INSERT INTO club_team_edges(parent_team_id,child_team_id,valid_from,valid_to,actor_trainer_id,reason) VALUES(1,?,?,?,1,'LOCALHOST')")->execute([$child,$from,$to]);}
    private function deny(callable $f):void {try{$f();self::fail('Expected refusal');}catch(\DomainException|\InvalidArgumentException|\RosterMessageException $e){self::assertNotSame('',$e->getMessage());}}
    public function testSeasonReadUsesDatedMembershipAndActualLessonDateWithoutWrites():void {
        $p=$this->db();$today=date('Y-m-d');$later=date('Y-m-d',strtotime('+10 days'));$future=date('Y-m-d',strtotime('+20 days'));
        $this->edge($p,2,'2000-01-01',$later);$this->edge($p,3,$future);
        $this->member($p,2,10,'2000-01-01');$this->member($p,3,11,'2000-01-01');
        $q=$p->prepare("INSERT INTO planovane_treninky VALUES(?,?,?,'10:00','11:00',2,?)");
        foreach([[1,'current',$today,'planovany'],[2,'expired child',$future,'planovany'],[3,'future child',$future,'planovany'],[4,'cancelled',$future,'zruseny']] as $row)$q->execute($row);
        $p->exec('INSERT INTO training_roster_links VALUES(1,2),(2,2),(3,3),(3,3),(4,3)');
        $p->exec("INSERT INTO coach_scope_grants VALUES(1,2,'team',2,'2000-01-01',NULL,NULL),(2,2,'team',2,'2000-01-01','2001-01-01',NULL),(3,2,'team',2,'2000-01-01',NULL,'2020-01-01')");
        $changes=$p->query('SELECT total_changes()')->fetchColumn();$d=\seasonPreparationRead($p,1,['on'=>$future]);
        self::assertSame(['continuing'=>0,'leaving'=>1,'joining'=>1],$d['counts']);self::assertSame([3],array_column($d['plans'],'id'));
        $now=\seasonPreparationRead($p,1);self::assertSame([1,3],array_column($now['plans'],'id'));self::assertSame([true,false],array_column($now['grants'],'active_on'));
        self::assertSame($changes,$p->query('SELECT total_changes()')->fetchColumn());$this->deny(fn()=>\seasonPreparationRead($p,1,['target_season_id'=>1]));$this->deny(fn()=>\seasonPreparationRead($p,1,['on'=>'2026-02-30']));
    }
    public function testSeasonPaginationAndLivePositionRevocation():void {
        $p=$this->db();$q=$p->prepare("INSERT INTO sportovci(id,jmeno,prijmeni) VALUES(?,'LOCALHOST','Many')");for($i=100;$i<131;$i++){$q->execute([$i]);$this->member($p,2,$i,'2000-01-01');}
        $d=\seasonPreparationRead($p,2,['q'=>'Many','page'=>99]);self::assertSame(31,$d['total']);self::assertSame(2,$d['page']);self::assertCount(6,$d['rows']);
        $this->actor(4,'sports_lead');self::assertSame(31,\seasonPreparationRead($p,2)['total']);$p->exec('DELETE FROM staff_user_positions WHERE trainer_id=4');$this->deny(fn()=>\seasonPreparationRead($p,2));$this->actor(2,'coach');$this->deny(fn()=>\seasonPreparationRead($p,2));
    }
    public function testHistoricalRenewalPreviewDoesNotRenewPaidParticipation():void {
        $p=$this->db();$a=\kisRosterCreateSeason($p,1,'OLD','LOCALHOST old','2025-09-01','2026-08-31','school_year');$b=\kisRosterCreateSeason($p,1,'NEW','LOCALHOST new','2026-09-01','2027-08-31','school_year');
        $series=\kisRosterCreateSeries($p,1,'RENEW','LOCALHOST new acceptance','hobby','school_year','renewal_required');$t=\kisRosterCreateSeriesTeam($p,$series['id'],$a['id'],1,'RENEW-OLD','LOCALHOST old course','Mix','Open','LOCALHOST');$this->member($p,$t['id'],10,'2026-01-01');
        $changes=$p->query('SELECT total_changes()')->fetchColumn();$d=\seasonPreparationRead($p,$t['id'],['target_season_id'=>$b['id']]);self::assertSame('2026-09-01',$d['on']);self::assertSame('await_renewal',$d['preview']['proposals'][0]['action']);self::assertSame(0,$d['preview']['executable_count']);self::assertSame($changes,$p->query('SELECT total_changes()')->fetchColumn());
    }
    public function testStandingAudienceDeduplicatesGuardianAndListsManualContact():void {
        $p=$this->db();$this->edge($p,2,'2000-01-01');$this->edge($p,3,'2000-01-01');$this->member($p,2,10,'2000-01-01');$this->member($p,3,10,'2000-01-01');$this->member($p,2,11,'2000-01-01');$this->member($p,3,12,'2000-01-01');
        $p->exec("INSERT INTO account_person_roles VALUES(1,20,10,'guardian','approved','2000-01-01',NULL),(2,20,11,'guardian','approved','2000-01-01',NULL),(3,21,12,'guardian','pending','2000-01-01',NULL)");
        $a=\rosterMessagePreview($p,1,'LOCALHOST','NEODESÍLAT');self::assertCount(1,$a['recipients']);self::assertSame([12],$a['manual_person_ids']);
        $this->member($p,2,12,'2000-01-01');self::assertSame($a['fingerprint'],\rosterMessagePreview($p,1,'LOCALHOST','NEODESÍLAT')['fingerprint']);
        $p->exec("UPDATE account_person_roles SET status='approved' WHERE id=3");$this->deny(fn()=>\rosterMessageEnqueue($p,1,'LOCALHOST','NEODESÍLAT',$a['fingerprint'],1,'LOCALHOST stale',true));self::assertSame(0,(int)$p->query('SELECT COUNT(*) FROM roster_messages')->fetchColumn());
    }
    public function testAudienceRejectsFutureAndExpiredRelationshipsWithinSameDay():void {
        $p=$this->db();$this->member($p,2,10,'2000-01-01');$q=$p->prepare("INSERT INTO account_person_roles VALUES(1,20,10,'guardian','approved',?,?)");$q->execute([date('Y-m-d H:i:s',time()-3600),date('Y-m-d H:i:s',time()-1)]);
        self::assertSame([],\rosterMessagePreview($p,2,'LOCALHOST','NEODESÍLAT')['recipients']);$p->prepare('UPDATE account_person_roles SET valid_from=?,valid_to=NULL')->execute([date('Y-m-d H:i:s',time()+3600)]);self::assertSame([],\rosterMessagePreview($p,2,'LOCALHOST','NEODESÍLAT')['recipients']);
    }
    public function testQueuedAudienceIsRecheckedAfterHierarchyClosesWithoutSenderCall():void {
        $p=$this->db();$this->edge($p,2,'2000-01-01');$this->member($p,2,10,'2000-01-01');$p->exec("INSERT INTO account_person_roles VALUES(1,20,10,'guardian','approved','2000-01-01',NULL)");$a=\rosterMessagePreview($p,1,'LOCALHOST','NEODESÍLAT');\rosterMessageEnqueue($p,1,$a['subject'],$a['body'],$a['fingerprint'],1,'LOCALHOST mock sender only',true);
        $p->exec("UPDATE club_team_edges SET valid_to='2001-01-01'");$calls=0;self::assertFalse(\rosterMessageProcessOne($p,static function()use(&$calls){$calls++;return true;}));self::assertSame(0,$calls);self::assertSame('failed',$p->query('SELECT status FROM roster_message_recipients')->fetchColumn());
        $changes=$p->query('SELECT total_changes()')->fetchColumn();$list=\clubCommunicationList($p,'roster',['queue_state'=>'failed']);self::assertSame(1,$list['total']);self::assertSame('failed',$list['rows'][0]['status']);self::assertSame('queued',$list['rows'][0]['stored_status']);self::assertSame($changes,$p->query('SELECT total_changes()')->fetchColumn());
    }
    public function testQueueHistoryPaginationInclusiveEndDateAndRoleBoundaries():void {
        $p=$this->db();$q=$p->prepare("INSERT INTO roster_messages(team_id,subject_plain,body_plain,recipient_count,preview_fingerprint,created_by_trainer_id,reason,created_at) VALUES(2,?,'PRIVATE BODY',1,'hash',1,'LOCALHOST','2030-01-10 23:59:59')");for($i=1;$i<=61;$i++)$q->execute(['LOCALHOST history '.$i]);
        $r=\clubCommunicationList($p,'roster',['queue_to'=>'2030-01-10','queue_page'=>99,'queue_q'=>'LOCALHOST history']);self::assertSame(61,$r['total']);self::assertSame(3,$r['page']);self::assertCount(11,$r['rows']);
        self::assertSame(0,\clubCommunicationList($p,'roster',['queue_to'=>'2030-01-09'])['total']);self::assertSame(0,\clubCommunicationList($p,'roster',['queue_from'=>'2030-02-30'])['total']);
        $this->actor(3,'system_admin');$this->deny(fn()=>\clubCommunicationList($p,'roster',[]));$summary=\clubCommunicationQueues($p);self::assertTrue($summary[0]['ready']);self::assertFalse($summary[1]['ready']);self::assertStringNotContainsString('PRIVATE BODY',json_encode($summary));$this->actor(2,'coach');$this->deny(fn()=>\clubCommunicationQueues($p));
        self::assertStringContainsString('blokované',\clubCommunicationNotice('exec,mail'));self::assertStringContainsString('nepotvrzuje',\clubCommunicationNotice(''));self::assertStringContainsString('outboxu',\clubCommunicationNotice(''));
    }
    public function testTransitionMapKeepsImmutableHistoryAndRejectsUnknownRoutes():void {
        $p=$this->db();$run=\clubCutoverCreate($p,3,['title'=>'LOCALHOST','scope_text'=>'LOCALHOST nonshop','release_reference'=>'LOCALHOST','source_environment'=>'LOCALHOST old','target_environment'=>'http://localhost:18086','owner_trainer_id'=>3]);
        $source=['kind'=>'source','entry_key'=>'source:paper','title'=>'LOCALHOST Paper','owner_trainer_id'=>1,'evidence'=>'LOCALHOST','origin'=>'LOCALHOST paper','history_mode'=>'unknown','shutdown_condition'=>'No cutoff'];\clubCutoverEntry($p,3,$run,0,$source,'LOCALHOST');
        $map=\clubTransitionTemplate('attendance');self::assertSame('',$map['owner_trainer_id']);$map['source_key']='source:paper';$map['owner_trainer_id']=1;\clubCutoverEntry($p,3,$run,1,$map,'LOCALHOST draft');$before=\clubCutoverEntries($p,$run)['agenda:attendance'];$map['source_screen']='LOCALHOST attendance book';$map['mapping_status']='verified';\clubCutoverEntry($p,3,$run,2,$map,'LOCALHOST verified workflow only');
        $history=\clubTransitionHistory($p,$run,'agenda:attendance');self::assertSame(2,$history['total']);self::assertSame($before['data'],$history['rows'][1]['data']);self::assertSame('verified',$history['rows'][0]['data']['mapping_status']);
        $this->deny(fn()=>\clubCutoverEntry($p,3,$run,2,$map,'LOCALHOST stale'));foreach(['https://attacker.invalid','../config.php','training_closure_admin.php?token=secret'] as $route)$this->deny(fn()=>\clubTransitionFields(array_replace($map,['target_route'=>$route])));
        $this->deny(fn()=>\clubTransitionFields(array_replace($map,['history_scope'=>''])));self::assertSame(3,(int)\clubCutoverRun($p,$run)['revision']);
        $p->exec("UPDATE club_cutover_entries SET data_sha256='invalid' WHERE revision=2");$this->deny(fn()=>\clubTransitionHistory($p,$run,'agenda:attendance'));
    }
}
