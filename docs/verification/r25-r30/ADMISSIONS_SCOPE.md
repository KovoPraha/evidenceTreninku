# R25 a navazující nabídky R30 — lokální implementace

Pouze syntetická instance `127.0.0.1:33426/evidence_b1` a HTTP `127.0.0.1:18086`. Bez produkčních zápisů, nasazení, vzdáleného Gitu a externího doručování.

## Dodané pracovní postupy

- Registrář v `club_program_admissions_admin.php` vyhledá existující osobu, přesnou datovanou nabídku a přijaté papírové podklady. Povinné aktuální programové a vlastní registrační souhlasy musí být doloženy konkrétními verzemi. Vybere sjednaného plátce; první rodič se nikdy nedoplní automaticky.
- Rodina bez účtu používá jméno a vztah plátce odpovídající oprávněnému podepisujícímu přijaté přihlášky. Kanonická objednávka i účast mají NULL účet. Nevzniká účet, falešné ověření e-mailu, online rodinná vazba ani předstíraný odeslaný e-mail. Registrář osobně předává pokyny a potvrzení; tento další krok je viditelný u přihlášky.
- Objednávka vzniká atomicky s auditovanou přihláškou, verzemi podkladů, přesnou cenou a kontrolou společné kapacity. Platební potvrzení používá stávající finanční životní cyklus a teprve poté aktivuje účast. Začátek účasti odpovídá skutečně sjednanému vstupu, nikoli automaticky prvnímu dni zakoupené nabídky. Papírové podklady nejsou vydávány za online kliknutí rodiče.
- Přestup má v UI konkrétní kroky: ověření cílové nabídky; doložené storno původní objednávky s ukončením účasti a přepočtem náhrad; samostatné finanční vyrovnání; nová přihláška navázaná na původní ukončenou účast. Nový formulář neprovádí automatické vratky, převody peněz nebo náhrad. Původní aktivní účast přestupový formulář odmítne.
- `member_fee_plans_admin.php` eviduje časově vymezenou dohodu plátce a přesně schválenou částku osoby za měsíc. Důvod je součástí auditu; nulová částka znamená prominutí bez fiktivního příjmu. Změna plátce uprostřed období nebo nepovolený souběh plánů blokuje automatický předpis. Částky se nekrátí odhadem. Náhled obsahuje osobu, plátce, částku a podklad rozhodnutí. Stálý VS je zachován.
- Opakování nabídky nyní vytváří koncept pro kontrolu ceny, termínu, kapacity a podmínek. Nevytváří osobu, členství, objednávku ani zaplacenou účast. Ročníkové rozpětí se posouvá spolu s datovanou nabídkou. Žádný plánovač nebyl aktivován.
- Existující náhled věkového přechodu soupisek nyní ověřuje skutečný ročník proti pravidlu cílové věkové skupiny. Neznámé/zástupné datum, neplatné datum nebo ročník mimo cílovou skupinu se nepřevede. Identita a stálý VS zůstávají nedotčeny.

## Vědomé provozní hranice

- Konkrétní finanční pravidla klubu se nepředstírají jako schválená. Přesnou měsíční částku a případný souběh potvrzuje hospodář podle dohody; pro pozdější vstup do programu se cena automaticky nemění.
- Již provedený měsíční běh se neopakuje jako nový soubor předpisů. Nový člen po tomto běhu má doplňující předpis přes existující agendu Jednotlivé předpisy s odkazem na dohodu. Offline členské předpisy nadále mohou používat tuto stávající individuální agendu; nový hromadný výběr dohod plátce pracuje s ověřenými účty.
- Vystavený předpis se novým měsíčním rozhodnutím nepřepisuje. Oprava se řeší existujícím finančním postupem. Zkušební/bezplatný program není aktivován falešnou nulovou platbou; jeho pravidla nebyla klubem potvrzena.
- Přejímka zahrnuje zdrojové testy, skutečné lokální HTTP a pozorovaný souběh MariaDB. Není uživatelskou přejímkou skutečných přihlášek nebo aktivací služby v produkci.

## Zdroje a přejímka

Implementace: `includes/club_program_admission.php`, `includes/member_fee_agreement.php`, kanonické `includes/club_program.php`, `includes/member_fee_plan.php`, `includes/club_program_recurrence.php`, `includes/kis_roster.php` a příslušné stávající/nové formuláře. Schéma: migrace `20261023010000_member_admissions` a `20261023010100_offline_program_admissions`.

`tests/Support/MemberAdmissionHttpAcceptance.php` ověřuje HTTP role, CSRF, plátce mezi dvěma rodiči, podklady, cenu, idempotenci, kapacitu, placenou aktivaci, offline přijetí bez účtu, přesnou měsíční částku a odvolanou session. Strojový protokol: `admissions-http.json`.

`tests/Support/MemberAdmissionMariaDbRace.php` spouští dva nezávislé procesy pro dvě osoby na poslední místo. Nejdříve doloží oba procesy ve stavu `INNODB_TRX LOCK WAIT`; po uvolnění vznikne jediná přihláška a žádná osiřelá objednávková položka. Strojový protokol: `admissions-race.json`.

Cílené PHPUnit: `MemberAdmissionPolicyTest`, `MemberFeePlanTest`, `ClubProgramRecurrenceTest`, `KisRosterTest`, `ClubProgramTest`. Včetně opakovatelné SQLite migrace nullable účtu se zachováním původních dat a indexů.

Peer review R28: zkontrolovány přímé exporty, oprávnění k aktuální osobě, odvolání pracovní pozice, neměnné podklady a hash, unikátní event/person/revision, vazba na původní závod. Nebyl nalezen potvrzený bypass ani duplicitní započítání v kontrolovaných cestách. U skutečného data účasti není v původní implementaci vyžadována shoda s kalendářním termínem; upozornění na tento rozdíl bylo předáno vlastníkovi R28.
