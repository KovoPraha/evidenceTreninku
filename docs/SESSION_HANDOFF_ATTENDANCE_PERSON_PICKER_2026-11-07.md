# P11 R06 — předání výběru osob v docházce, 7. 11. 2026

## Rozsah a stav

Výslovné zadání: optimalizovat podle měření, stejná syntetická data, zachovat
chování a WIP, produkci neměnit. Implementace a místní měření/UAT HOTOVO.
[Plán](ATTENDANCE_PERSON_PICKER_2026-11-07.md),
[výsledky a důkazy](verification/attendance-person-picker-20261107/RESULTS.md).
Base `d958fd6e`, větev `codex/attendance-person-picker-20261107`, worktree
`C:/Users/Marek/Documents/Codex/evidencePavel-attendance-picker-20261107`.
Předáno přes [PR #366](https://github.com/KovoPraha/evidenceTreninku/pull/366);
stav finálního CI/začlenění a konkrétní hlava se ověřují u PR. Žádný náš deploy.

## Co je hotové

- Nativní hledání a stránkování po 50, všechny již vybrané osoby připnuté;
  samostatně pro docházku a omluvu, bez nového JS nebo AJAX endpointu.
  Žádný limit 50 účastníků, žádné omezení hostů na vlastní soupisku.
- Hledací POST je čtecí, má CSRF i čerstvé oprávnění/lekci. Zachovává celý
  draft, papírový původ, vybrané trenéry, potvrzení, prázdný výběr a původní
  verzi. Kanonický writer ani transakční zámky se neměnily.
- Nový přímý pár **21 HTTP případů / 270 pozitivních časů na variantu**,
  9 DOM kontrol na variantu; **39 Chrome pozorování na variantu** pro trenéra,
  vedoucího a druhého trenéra. Standardní přihlášení, bootstrap/nav zahrnuté.
- Stejná DB před/po: 350 tabulek / 161 migrací, 728 plánů / 52 týdnů,
  200 účastníků / 52 revizí a 10k zdravotních sledování / 30k záznamů.
  Staré časy feb8fe8c nejsou přímá kontrolní skupina.
- Velká docházka **1,37 MB → 92 kB (−93,2 %)**; Chrome warm medián trenér
  **5,23 → 1,11 s**, vedoucí **5,21 → 0,46 s**, druhý trenér **5,32 → 0,47 s**.
  SQL někde stouplo (59 → 75); netvrdíme plošné snížení počtu dotazů.
- Celé nezměněné HTML v oddělené diagnostice každé varianty, 0 zápisových SQL,
  shodné doménové checksums. Všechny 10221 osoby přes 205 stran právě jednou
  pro obě role; doplňková MariaDB parita kolací/hostů/role PASS.
- JS/noJS browser: klávesnice, hledání, prázdný/nový výběr, draft omluvy,
  390px bez horizontálního přetečení PASS. Po časování skutečný syntetický
  zápis revize 53 / 201 osob / paper, stale write odmítnutý a osobní omluva
  přes skutečné tlačítko uložená v DB. Zápisové UAT je oddělené od měření.
- 35 testů / 374 assertions, PHPStan a styl 9 PHP souborů PASS. Nový reader
  je v CI 10.3/11.4; stará R06 parita sdílí nové UI, původní validátor zůstává
  v before, přísná HTML/datová parita ani archivované harnessy se neruší.

## Úklid, hranice a další krok

Vlastní DB schéma/účet/config/credentials/cookies odstraněné, všechny vlastní
browsery i HTTP/MariaDB zastavené. Oba TCP sockety odmítají připojení.
Runtime `C:/xampp/tmp/kis-picker-20261107-d6aeea40` ponechaný zastavený,
včetně chybně pojmenovaného nikdy nespouštěného inicializačního adresáře;
žádné rekurzivní mazání. Původní WIP `kis_rosters_admin.php` stále SHA256
`7a7afd75bfa34f736f158096ab46a671b7fee6c6faedc3701f989c0cf64733e5`.

1. Finální sdílený stav CI/merge: [PR #366](https://github.com/KovoPraha/evidenceTreninku/pull/366).
2. Další výkonová priorita: hledání lekcí, širší případ nyní 219 SQL (před
   pickerem 203). Zjistit časové příčiny, zachovat čerstvou autorizaci a writer.
3. R13/R05 objemy/kandidáti, skutečné rows examined, mobil/CPU/síť/souběh,
   další R01–R16. Celé K0.2 otevřené, místní čísla nejsou produkční SLA.
4. Favicon 404 na localhostu a externí Bootstrap CSS zůstávají oddělené drobné
   kandidáty; zdravotní odkazy a historie nebyly kvůli výkonu zkrácené.
5. Produkční deploy/přejímka jen na nové explicitní zadání. Aktuální cizí
   vydání/aktivace přebírat z CURRENT, nikoli z této místní měřicí relace.
