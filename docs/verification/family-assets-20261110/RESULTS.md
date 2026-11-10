# P11-ASSET7 — ověření stylů čtyř rodinných stránek

Implementace/native2DB/Chrome/13CI PASS; PR #447 sloučen jakoc29642f1.
Deploy42905781826 běží, produkční receipt a after zatím NOT RUN.
[Plán](../../BOOTSTRAP_ASSET_PERFORMANCE_2026-11-10.md#p11-asset7--stránky-rodinného-účtu-10-11-2026),
[PR #447](https://github.com/KovoPraha/evidenceTreninku/pull/447).

## Rozsah a přesná shoda

Právě čtyři CDN Bootstrap CSS tagy odstraněné: moje_osoby.php,
moje_objednavky.php, moje_programy.php, moje_rezervace.php. AppUiAssets,
payloady/verze/TTL a služby beze změny. Celé PHP tokeny/literal HTML/JS mimo
tagy a4přesně pinované PSR12 guard závorky v moje_programy.php stejné; dlouhé
HTML řádky dělí nulové PHP hranice bez výstupu. Zápis kontroly registračních
podmínek zpět na jednom řádku, původní LoggedInUxWiringTest ani guard se nemění.
SQL, identity/vazby, ceny/platby, aktivace programů, storna a writery stejné.
Lokálně19testů/188assertions a unit824/28734 se3Windows symlink skip, bez
selhání. Source/PSR12/line200/lint/diff/docs PASS.

Native [42903018197](https://github.com/KovoPraha/evidenceTreninku/actions/runs/42903018197)
naMariaDB10.3/11.4 SUCCESS; [souhrn](native-acceptance.json),
[10.3 raw](native-10.3/whole-http.json.gz), [11.4 raw](native-11.4/whole-http.json.gz).
17celých HTTP scénářů/DB,17střídavých párů po4warmupech =578měřených+136warmup
GET naDB. Main účet: neprázdné osoby,20objednávek s kanonickými payments,
programové účasti, vlastní rezervace; cizí rodina sentinel nesmí uniknout.
Prázdný ověřený účet200, neověřený login přesně200/„Email není ověřen.“ a
čtyři soukromé GET302, anonymní GET302; produktcontrol200/1CSS.
Celé status/Location/HTML přesně stejné, pouze konkrétní CDNtag a request
nonce/64hexCSRF; žádná obecná whitespace/script/asset-version normalizace.
InvalidCSRF osob/programů vrací původní error200, rezervace ignorují POST200
beze zápisu podle dosavadního kontraktu. Čerstvá revokace vazby odstraní
konkrétní vlastní osobu; vypnutý přihlášený účet přesně303/session=expired.
Fixture ve finally obnovuje status i původní updated_at vazby a aktivitu účtu.
Všech350 checksumů/DB shodných, owned schema/user/config/bridge/ports cleanup PASS.

### Kalibrace a neúspěšné běhy zůstávají v historii

42900569649/964df9a6 a42900622889/5926e376 FAIL před HTTP: fixture chybně
čekala přihlášení neověřeného účtu302. Zdroj správně odmítá200, test opraven
na přesný kontrakt, ochrana se neuvolnila. 42901157327/ee01c130 FAIL ještě
v before main-orders: kapacitní fixture neměla payment řádky pro skutečný
INNER JOIN přehledu, doplněny pouze syntetické řádky bez reálné platby/IBAN.
Native42901718648/f111d30e oběDB/Chrome PASS, ale standardní PHPUnit stejného
headu měl jediný zdrojový wiring FAIL kvůli víceřádkovému zápisu guard volání.
Výraz vrácen na jeden řádek pod200znaků; test i tokeny beze změny. Finální
native/standard CI se opakovalo na novém headu, starý PASS není náhradou.
Zrušené obsolete vlastní Testy běhy964/592/ee/f111 nejsou finálními CI.

## Skutečný Apache/Chrome před a po

Izolovaná plně migrovaná syntetická DB,162migrací/350tabulek. Fyzické PHP kopie
obou rootů, pouze4before šablony z4d1e9941, všechny jiné PHP soubory byte-shodné
z aktuálního společného headu. Skutečné standardní public login formuláře,
bez mocků a umělé latence. Desktop1036×703/mobile390×844, cold/warm na5stránkách,
17střídavých párů po4warmupech:680měřených+160warmup navigací. ResourceTiming/CDP,
skutečný CSS decodedSHA/SRI, computedstyle/geometrie,40funkčních záznamů:
navigace, draft jména/vztahu, detailové odkazy objednávek, programové řádky,
identita storna a zachované hidden/CSRF. Odeslána pouze přihlášení do syntetického účtu; žádná žádost, aktivace, storno ani objednávka.
Deset přesně dekódovaných pixelových párů PASS, bez overflow/JS/network chyb.
[20souhrnů](native-11.4/browser-summary.json), raw browser-*.json.gz ve stejném
adresáři, source/shared-context/cleanup v obou native adresářích.

| Cold scénář | FCP před → po, ms | Ready před → po, ms | Encoded Bootstrap před → po, B |
| --- | --- | --- | --- |
| people desktop | 56 → 48 | 76.7 → 76.3 | 64454 → 31248 |
| people mobile | 60 → 44 | 87.9 → 76.0 | 64454 → 31248 |
| orders desktop | 72 → 56 | 93.5 → 76.8 | 64454 → 31248 |
| orders mobile | 76 → 52 | 100.8 → 76.4 | 64454 → 31248 |
| programs desktop | 72 → 52 | 92.3 → 71.2 | 64454 → 31248 |
| programs mobile | 68 → 48 | 91.4 → 59.9 | 64454 → 31248 |
| reservations desktop | 52 → 36 | 76.7 → 65.2 | 64454 → 31248 |
| reservations mobile | 52 → 36 | 82.0 → 60.3 | 64454 → 31248 |
| control desktop | 72 → 68 | 93.3 → 93.4 | 31248 → 31248 |
| control mobile | 68 → 68 | 93.4 → 93.3 | 31248 → 31248 |

Cíle2→1CSS, decoded465606→232803 B (−50 %); control1→1. Encoded rozdíl je
skutečný přenos daného CI/CDN transportu, nikoli garantované produkční číslo.
Warm network0→0 B: netvrdit síťový zisk při teplé cache. Gate20%+20ms prošel;
FCP/ready jsou mediány tohoto runneru, nikoli produkční SLA. Kontrolní desktop
FCP se také zlepšil72→68ms, mobile68→68ms; časové rozdíly zahrnují šum runneru. Backend časy a
jejich drobné odchylky jsou v native-summary.json, změna SQL/TTFB nezrychluje.

Reprodukce: .github/workflows/family-assets-performance.yml, explicitní
SYNTHETIC-ONLY, pin4d1e9941 a tests/Support/FamilyAssets*. JSON/GZ obsahují jen
bezpečné metriky/otisky/syntetické údaje; žádná hesla, cookies, raw HTML, config
nebo dumpy. Můj sport mimo přesně čtyřstránkový řez; HJS2/RIX WIP a EVENTREAD
odložený prototyp zachované, jeho PHP helper se nepřebírá. Historický ASSET4
LIMIT i ASSET6 controlfoto LIMIT se nepřepisují na PASS. Původní WIP7a7afd75
zachovaný. Živá přihlášená produkční relace není dostupná (CUA končí na loginu),
autentizované produkční UAT NOT RUN; native není tvrzená náhrada za živé UAT.
