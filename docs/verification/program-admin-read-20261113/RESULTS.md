# P11-PADMIN — dávkové čtení správy programů

13. 11. 2026, Europe/Prague. NASAZENO. PR #477 / b4d5cfe2714ebf6af16f7ed2d4f36a99569d087f, testovaný a sloučený strom 94bcfbec6d53fed9b1775dbdd8aa5e28a263d8ca. Všech 15 CI PASS.
[Plán](../../club-programs.md#p11-padmin--dávkové-čtení-správy-programů-13-11-2026), [PR #477](https://github.com/KovoPraha/evidenceTreninku/pull/477).
Testovaný head 1f09f6aebf96fa3cc3b72c4ce89972a17b71eb10, zdrojová reference c5a4ca3b18265f79d740f4be7b25491132aac887.
[Párový běh 43475985806](https://github.com/KovoPraha/evidenceTreninku/actions/runs/43475985806), [provenience](paired-run.json).

## Změna a shoda

Nový includes/club_program_admin_read.php kopíruje přesný výběrový SQL a pořadí
kanonického clubProgramAdminOffers, které zůstává nezměněnou referencí.
Využívá čerstvý lokální ClubProgramReadContext: metadata jednou, fyzické
soupisky v dávkách do400 a podmínky pro všechny správní nabídky v dávkách
do80 cílů/800 parametrů. Koncepty, uzavřené/expirující nabídky, neaktivní
programy, prázdné/VZOR override a programový fallback zůstávají viditelné
se stejnými hodnotami a typy. Poslední aktivní dokument se vybírá podle
stejné DB kolace a MAX(id), stejně jako původní ORDER BY id DESC LIMIT1.

Kapacitní SQL a pure výpočet se nemění; dva dotazy na každou fyzickou
soupisku a nabídku nadále existují. Původní public/scalar/checkout/locking API
je beze změny; zamykající volání ignorují read cache. Každé nové správní
volání vytváří nový kontext a vidí změny podmínek/účastí. Žádná persistentní
cache práv, nové schéma/indexy ani změna writerů.

## Nativní párové měření

MariaDB10.3/11.4, plně migrovaný vlastněný syntetický FullHttpReadFixture.
0/2/12/100/401 nabídek, včetně různých stavů a více fyzických soupisek,
skutečné syntetické placené účasti původních dvou nabídek zachované.
Pro každý případ 4 zahřátí a17 střídavých měřených párů, strict === celé
řádky/typy/pořadí v každém volání. Čas a přírůstek PHP peak na obyčejném
PDO v read-only transakci; SQL/řádky samostatně pomocí QueryProfilePdo.
Query cache off. Checksums všech355 tabulek před/po každé sadě a přesná
obnova po syntetických přechodech PASS; žádný native údaj není produkční SLA.

| MariaDB | Nabídek | Medián ms před → po | SQL před → po | Načtené řádky před → po | Peak přírůstek B před → po |
| --- | ---: | ---: | ---: | ---: | ---: |
| 10.3 | 0 | 0.262 → 0.269 | 1 → 1 | 0 → 0 | 39896 → 39896 |
| 10.3 | 2 | 17.591 → 5.937 | 43 → 14 | 32 → 130 | 88024 → 104416 |
| 10.3 | 12 | 113.693 → 16.933 | 270 → 40 | 237 → 196 | 158144 → 197600 |
| 10.3 | 100 | 930.402 → 110.257 | 2228 → 262 | 1987 → 758 | 809208 → 1153960 |
| 10.3 | 401 | 3726.192 → 425.651 | 8922 → 1023 | 7975 → 2685 | 3038080 → 3810408 |
| 11.4 | 0 | 0.400 → 0.403 | 1 → 1 | 0 → 0 | 39896 → 39896 |
| 11.4 | 2 | 22.623 → 7.661 | 43 → 14 | 32 → 130 | 88024 → 104416 |
| 11.4 | 12 | 147.012 → 23.127 | 270 → 40 | 237 → 196 | 158144 → 197600 |
| 11.4 | 100 | 1213.752 → 150.528 | 2228 → 262 | 1987 → 758 | 809208 → 1153960 |
| 11.4 | 401 | 4868.294 → 588.876 | 8922 → 1023 | 7975 → 2685 | 3038080 → 3810408 |

Při401 nabídkách peak přírůstek roste o772328 B (0,74MiB), protože kontext
drží mapy podmínek a metadat. Při2 nabídkách načte více metadat řádků,
ale odstraní opakované roundtripy. Přijatý přínos je čas/SQL, nikoli menší RAM.
[Všechny časy/SQL trace10.3](mariadb-10.3/native.json.gz), [11.4](mariadb-11.4/native.json.gz).

## Celé HTTP a Chrome

Na každé MariaDB16 scénářů + prázdný přehled: program_coordinator200,
11 ostatních přihlášených účtů403, anonymní302, editor programu, editor
období a neexistující program. Kladné případy4zahřátí/17střídavých párů,
záporné jeden kontrolní pár. Fixture má při čtení100/0 nabídek, po obnově100.
Celé HTML a sledované bezpečnostní hlavičky shodné pouze po normalizaci
request CSP nonce; CSRF i všechna ostatní data/atributy/skripty zachované.
Na obou DB355shodných checksumů a bezpečný úklid schématu/účtů/configu/portů.
[HTTP10.3](mariadb-10.3/http.json.gz), [11.4](mariadb-11.4/http.json.gz),
[prázdný10.3](mariadb-10.3/empty-http.json.gz), [prázdný11.4](mariadb-11.4/empty-http.json.gz).

| MariaDB | Scénář | HTTP medián ms před → po |
| --- | --- | ---: |
| 10.3 | program_coordinator | 726.991 → 91.027 |
| 10.3 | program-terms | 719.935 → 90.632 |
| 10.3 | offer-terms | 721.017 → 93.380 |
| 10.3 | missing-program | 721.078 → 90.884 |
| 10.3 | empty-overview | 4.935 → 4.928 |
| 11.4 | program_coordinator | 889.908 → 117.880 |
| 11.4 | program-terms | 883.174 → 117.202 |
| 11.4 | offer-terms | 886.682 → 119.929 |
| 11.4 | missing-program | 886.897 → 119.053 |
| 11.4 | empty-overview | 6.351 → 6.477 |

Skutečný Apache a Chrome přes Playwright CLI: 176měřených+48zahřívacích
navigací přehledu/editoru, desktop1036×703 light a mobile390×844 dark,
cold/warm cache. Čtyři full-page pixelově shodné dvojice; shodná geometrie,
computed styles, formulářové metody/akce/názvy/hodnoty. Bootstrap JS dostupný,
žádné page/console/network chyby. CSS cold31248→31248 B, warm0→0 B,
decoded232803 v obou; kanonický payload SHA256 a SRI ověřený.
Tento řez nepřidává CSS úsporu již nasazeného ASSET12.

| Cesta/téma | Cache | FCP ms před → po | Ready ms před → po |
| --- | --- | ---: | ---: |
| overview / light | cold | 1016 → 228 | 1028.7 → 252.9 |
| overview / light | warm | 1012 → 208 | 1019.7 → 225.6 |
| overview / dark | cold | 1028 → 228 | 1045.8 → 245.6 |
| overview / dark | warm | 1004 → 208 | 1017.7 → 228.6 |
| terms / light | cold | 1024 → 240 | 1033.6 → 253.4 |
| terms / light | warm | 1016 → 228 | 1024.3 → 230.6 |
| terms / dark | cold | 1032 → 236 | 1056.6 → 255.9 |
| terms / dark | warm | 1004 → 212 | 1011.9 → 232.6 |

[Browser souhrn/snímky](mariadb-11.4/browser-summary.json), [HTTP mediány](http-medians.json).

## Kontroly, checkpointy a release

Místní27testů/196assertions PASS; strict5/75 zahrnuje boundary80/81/401,
neaktivní a duplicitní varianty, sdílené kapacity a deduplikaci osob,
pending/paid/expired/rejected/cancelled, chybějící registry/sloupce,
čerstvé změny a lock čtení. Syntaxe PHP/JS/Python, PSR12/200/braces,
composer validate, docs a diff PASS. Místní composer analyse nedokončil
timeout300s; rozhoduje zelený Linux PHPStan/CI, bez nových suppressions.
Historický ASSET12 profil zachovává CSS/HTML kontroly a úzkou kompatibilitu
jen pro require+reader volání stránky; jeho starý CSS výsledek se nepřepisuje.

[První native checkpoint](native-checkpoint-1.json): native PASS, celý profil
FAIL na chybějícím přímém require fixture, opraveno. [Druhý celý pár](paired-checkpoint-2.json)
PASS; následně pouze zpřesněna metadata prázdného fixture na stav při čtení
a stav po obnově. Finální výše uvedený běh ověřuje aktuální commit úplně.

[Chráněný deploy 43478690110](https://github.com/KovoPraha/evidenceTreninku/actions/runs/43478690110) SUCCESS na stejném SHA.
2472 testů / 56686 assertions, záloha 394 tabulek / 2 triggery PASS.
Stávající konfigurace zachovaná, bez doplnění configu; migrace current/pending [],
původní VS a zdravotní index shodné, aktivace release a úklid config-copy PASS.
UAT/import false, import přeskočený. Přímý runner HTTP 0 (spojení nedokončeno), podporovaný server-side smoke HTTP 200 PASS. [Receipt](release.json).

Nezávislé anonymní GET před/po: administrace 302 se stejným cílem a místní CSS
200 s přesnými bytes/hash/cache shodné. [Před](production-before.json), [po](production-after.json).
Přihlášené produkční UAT, produkční přihlášený waterfall a produkční časový
benchmark NOT RUN; potvrzený přínos je syntetický párový CI profil.
Žádné skutečné nabídky/podmínky/účasti/objednávky/platby/role ani rozesílky
tímto ověřením měněné nebyly. Výsledkové doklady mají samostatné PR bez redeploye.

Původní fio/d1221f35 a kis_rosters_admin.php WIP SHA256
7a7afd75bfa34f736f158096ab46a671b7fee6c6faedc3701f989c0cf64733e5 zachované.
Současný blokátor UAT náhradních lekcí není tímto výkonovým řezem vyřešený.

Další kandidáti: profil zbývajících kapacitních dotazů, zejména opakované
stejné soupisky a období; omezení celého správního seznamu při doloženém
objemu; styly kalendáře a katalogové administrace. Vyžadují samostatnou
paritu, měření a přijetí; žádný další prototyp se tímto neslučuje.
