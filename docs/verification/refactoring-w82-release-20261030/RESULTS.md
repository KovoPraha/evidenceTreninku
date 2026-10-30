# W8.2 / W8.3 — společné ověřené vydání

30. 10. 2026, [kis.kovopraha.cz](https://kis.kovopraha.cz/).
[PR #238](https://github.com/KovoPraha/evidenceTreninku/pull/238) a
[PR #240](https://github.com/KovoPraha/evidenceTreninku/pull/240), release
`50c542b057944c740e9424ad12f56d28e8183e98`, [deploy 41060582108](https://github.com/KovoPraha/evidenceTreninku/actions/runs/41060582108) SUCCESS.
Vlastník autorizoval implementaci, ověření, sloučení i nasazení dalších vln.

## Změna a testy

W8.2 dávkuje historické cíle filtrů plánů/tréninků a odstraňuje opakované
počítání kopírovacího náhledu. Roční profil filtrů 1875 → 5 SQL;
kopírování bez týmového filtru 3 → 1, s týmem 1048 → 5.
CI MariaDB 10.3 roční filtr 269,47 → 1,79 ms; 11.4 416,56 → 5,04 ms.
Paměť 638512 → 620992 B. [Plán](../../REFACTORING_W82_2026-10-30.md),
[měření](../refactoring-w82-20261030/RESULTS.md),
[CI 10.3](../refactoring-w82-20261030/ci-mariadb-10.3.json),
[CI 11.4](../refactoring-w82-20261030/ci-mariadb-11.4.json).

W8.3 načítá členství pouze aktuálně autorizovaných osob rodiny, se sdílenou
historickou projekcí pro osobní časovou osu. Roční rodinný kalendář
4199 → 39 SQL, členství 1041 → 1 SQL a 102000 → 20 načtených řádků.
CI 10.3 při 20 osobách 1458,89 → 42,78 ms; 11.4 1103,17 → 26,89 ms.
Paměť 7799272 → 3582808 B. [Plán](../../REFACTORING_W83_2026-10-30.md),
[měření](../refactoring-w83-20261030/RESULTS.md),
[CI 10.3](../refactoring-w83-20261030/ci-mariadb-10.3.json),
[CI 11.4](../refactoring-w83-20261030/ci-mariadb-11.4.json).

Všechny výstupy se přesně porovnávají s původním čtením; zahrnuty cykly,
historické hranice, revokace, kolace, NULL a hranice dávek. Původní změna
chování legacy osoby ID 0 byla zachycena testem a odstraněna před PR.
Žádná cache mezi požadavky, přesun autorizace ani změna writerů.
Časy jsou syntetické lokální/CI profily, nikoli produkční latence.

Přesný výsledný main: [Current documentation 41059610473](https://github.com/KovoPraha/evidenceTreninku/actions/runs/41059610473), [Push on main 41059610417](https://github.com/KovoPraha/evidenceTreninku/actions/runs/41059610417), [PHP quality 41059610479](https://github.com/KovoPraha/evidenceTreninku/actions/runs/41059610479), [Testy 41059610469](https://github.com/KovoPraha/evidenceTreninku/actions/runs/41059610469). Deploy Linux: **1839 testů /
36699 assertions**, bez POSIX skipů. Současně PHPStan,
styl, anonymous sweep, JS a obě MariaDB PASS.

## Provozní důkazy

- [Před](pre-verification.json): čtecí kontrola 41056819294.
- [Po](post-verification.json): čtecí kontrola 41061401167,
  živé SHA/run ID odpovídá deploy; bez doménových mutací.
- [Porovnání](release-comparison.json): konfigurace, flags, canonical writes,
  ledger 141, forward VS repair a stav příloh shodné.
- [Nasazení](deployment.json): záloha `evidence_2026-10-30_130903_eaad27fb.sql.gz`, SHA256
  `b6f4b5283e5d27f84b79f8b3a8a0580ba187eae60df47b4223833aeba627ff19`, 336 tabulek / 2 triggery,
  6063580 B; migrace current, pending [], VS beze změny,
  dočasná kopie produkční konfigurace odstraněna.
- [HTTP před](pre-http.json) a [po](post-http.json): 26/26, stejné produkty.
  Přímé TCP z GitHub runneru timeoutovalo; SSH fallback v deploy vrátil 200.
  Nezávislé GET testy proběhly zvlášť. Úplné autentizované UAT tím není prokázáno.
- `uat_schvaleno=false`, `importovat_krouzky=false`. Fio import/auto-confirm,
  SumUp, Stripe, Velocota vypnuté; Packeta nedefinovaná. Žádné skutečné
  převody, registrace, importy či aktivace služeb nebyly provedeny.

## Návaznost

W8.2/W8.3 jsou technicky dokončeny a nasazeny. Pokračuje W8.4 klubový
kalendář, W8.5 sportovní kontrakty a W9–W15. Lidská/účetní přejímka
D12–D14, ověřené provozní údaje a skutečné převody zůstávají oddělené.
