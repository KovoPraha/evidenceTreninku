# Provedení schváleného převodu členství

Vlastník schválil [mapu](MEMBER_EVIDENCE_APPROVED_MAP_2026-11-02.md), převzetí
435 aktivních zařazení od **2. 11. 2026**, odstranění 560 historických zařazení
a samostatnou technickou identitu **Převod evidence členů** bez přihlašování.
Datum počátku se nemění ani při pozdějším provedení operace či poslední synchronizaci.

## Konkrétní operace

Chráněný workflow `member-evidence-production.yml` přijímá `PREVEST-CLENSTVI`
a úplné SHA nasazené aplikace. Spouští se pouze z `main`, v prostředí `production`
a ve společné frontě `produkce-kis`; ostatní GitHub workery tak nemohou běžet současně.
Soukromou mapu porovnává s přesným otiskem úspěšného nácviku
`b4168c1bc94b824e4d70c167cdba795465607fc07f8ee3c71b9593b467fea0c9`.
Ověří nasazené SHA i obsah souborů jádra převodu proti zdroji workflow.

1. Náhled v READ ONLY transakci ověří původní názvy/kódy, vazby a přesně 435/560.
2. Soukromě uloží původní konfiguraci a `.htaccess`, jejich práva a otisky.
   Dočasná pravidla vracejí HTTP 503; konfigurace odmítá nové CLI procesy
   kromě právě běžícího převodu a jeho zálohovacího procesu. Tři skutečné URL
   musí vracet 503 s textem údržby. Dosavadní DB spojení musí doběhnout;
   nástroj žádné cizí spojení neukončuje. Při nedoběhnutí převod neprovede.
3. Vytvoří novou aplikační zálohu v soukromém adresáři operace, ověří její SHA
   a shodu dat před/po zálohování. Nevyprazdňuje starší zálohy.
4. Vytvoří přesně jednu technickou identitu s neplatným heslovým formátem,
   prázdným e-mailem a dočasnou pozicí registráře. Login tak nemá ověřitelné
   heslo a reset nemá cílový e-mail. Kolize s existující osobou operaci zastaví.
   Audit obsahuje GitHub aktéra, workflow, run a schválené vydání.
5. Za pozastavených zápisů vytvoří nový úplný manifest a aplikuje běžný
   transakční převod. Kontroluje parity zachovaných dat, účinné členství,
   nové skupiny, idempotentní opakování a zachování stávajícího trenérského pilotu.
6. Vždy se pokusí odebrat dočasnou pozici, deaktivovat technickou identitu
   a obnovit původní soubory přesně po bajtech. Při cizí souběžné úpravě soubor
   nepřepíše. Následný samostatný recovery krok ověří obnovu i po chybě prvního
   procesu. Souborový zámek mu zakáže zasáhnout do dosud běžícího převodu.
7. Ověří HTTP dostupnost. Čerstvou zálohu obnoví do izolované MariaDB,
   porovná počty všech tabulek a triggery. Živou DB automaticky neobnovuje.

Soukromé soubory leží v `data/.kis-deploy/member-cutover-RUN/` mimo webroot
(adresář 0700, soubory 0600). Po ověřené obnově provozu recovery odstraní
pomocnou kopii konfigurace s přístupovými údaji. Manifest, záloha a protokoly
zůstávají soukromé; žádná osobní data se neposílají do CI artifactů.

## Selhání a hranice dokončení

Před commitem se členská transakce vrátí zpět. Po commitu je pravdivým dokladem
DB ledger a `committed.json`; chyba pozdější kontroly nesmí být vydávána za
neprovedený převod. Automatické opakování mutujícího SSH příkazu je zakázané.
Existující technická identita z nedokončeného pokusu vyžaduje kontrolu konkrétního
runu; nástroj ji nesmí zaměnit s jiným účtem nebo bez kontroly znovu povýšit.

Oprava čtecího náhledu reaguje na běh
[41537033912](https://github.com/KovoPraha/evidenceTreninku/actions/runs/41537033912):
MariaDB odmítla implicitní `FOR UPDATE` v READ ONLY transakci (25006/1792).
Náhled nyní výslovně nepožaduje zámky hierarchie. Zápisové cesty mají dosavadní
výchozí zamykání a ostrý převod navíc zamyká celý schválený aplikační rozsah.
Regrese je pokryta skutečným READ ONLY náhledem skupiny na MariaDB 10.3/11.4.
Neúspěšný čtecí běh nic nezapsal a provoz nepozastavil.

Provozní data zatím nejsou tímto dokumentem prohlášena za převedená. Výsledek
bude doplněn do [aktuálního stavu](CURRENT_STATE.md) a protokolu konkrétního runu.
Přihlášená přejímka všech rolí a dva již chybějící podklady zůstávají otevřené;
členský převod nemění soubory příloh. Poslední synchronizace čeká na další export.
E8 nesmí odstranit její technické mapy ani předčasně uzavřít bod návratu.
