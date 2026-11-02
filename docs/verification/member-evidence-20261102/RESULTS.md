# Ověření aplikační změny evidence členů — 2. 11. 2026

[PR #279](https://github.com/KovoPraha/evidenceTreninku/pull/279), výchozí main
`2aa5cbd89040474074678c6a04cfb099cc8235a9`. Aplikační implementace je v commitu
`7d413716400c32660a2238f27d3d20b93a875081`; další commity upravují diagnostiku,
testovací přípravy a reference výkonových profilů. Aktuální head a stav všech
kontrol určuje [CI PR](https://github.com/KovoPraha/evidenceTreninku/pull/279/checks).

PR #279 byl sloučen jako `58690c42fdd38c620cfa6ba5663b966088e55976` po všech
11 úspěšných kontrolách přesného headu `103b140a014a650e326bca53499105805d9bee99`.
[Testovací matice 41496584905](https://github.com/KovoPraha/evidenceTreninku/actions/runs/41496584905)
prošla včetně PHPUnit, všech integračních kroků i profilů na MariaDB 10.3/11.4.
Sloučení není nasazení.

Samostatná [obnova skutečné poslední zálohy 41498416970](https://github.com/KovoPraha/evidenceTreninku/actions/runs/41498416970)
prošla: **337 tabulek a 2 triggery**. Tento běh ověřuje zdrojovou zálohu, nikoli
ještě převod novým nástrojem nebo obnovu soukromých příloh.
První [čtecí inventura 41498151528](https://github.com/KovoPraha/evidenceTreninku/actions/runs/41498151528)
selhala bez zápisu; doplněna diagnostika fáze a SQLSTATE bez výpisu osobních dat
nebo textu PDO výjimky. Úspěšný výsledek inventury musí doložit další běh.

Navazující PR #280 je sloučen jako `c3cb0168fdcbac36147e6c86a138e92281a5350e`,
opět po všech 11 kontrolách PASS. [Nácvik 41500616687](https://github.com/KovoPraha/evidenceTreninku/actions/runs/41500616687)
nad skutečnou zálohou aplikace **prošel**: 337 zdrojových tabulek, po upgradu
341 tabulek ve snapshotu (ledger převodu se nepočítá), **44 převedených soupisek**,
0 smazaných řádků, nemutující preview, zachovaná parita a idempotentní opakování.
Po opětovné obnově souhlasily otisky dat i schématu všech 337 původních tabulek
a oba triggery. SHA-256 zdrojové zálohy:
`62aa5ba85830fdbd0d02ada60a0d365147eeff1a97621ac4fe268b673f5e1bdb`.

Rozsah tohoto důkazu je **aplikační ownership zálohy**, nikoli všechny cizí či
archivní tabulky sdílené produkční databáze. Kódy soupisek byly pouze technické
`REHEARSAL-ID`. Nejde o schválenou cílovou mapu, nácvik mazání ani přejímku příloh.
Celá E6 a E7/E8 tím nejsou uzavřené.

[Druhá inventura 41500610498](https://github.com/KovoPraha/evidenceTreninku/actions/runs/41500610498)
odhalila příliš úzkou kontrolu názvů tabulek v katalogu. Nástroje nyní identifikátory
bezpečně uzavírají a zdvojují vnitřní backtick; zahrnou také staré názvy s mezerou,
pomlčkou či číslicí. Regrese s názvem obsahujícím SQL text potvrzuje zahrnutí
tabulky do snapshotu a zachování finanční tabulky při převodu: **6 testů / 21 assertions PASS**.

## Doložené výsledky

| Kontrola | Výsledek a rozsah |
|---|---|
| Úplná linuxová PHPUnit | [Běh 41493722731, PHPUnit job](https://github.com/KovoPraha/evidenceTreninku/actions/runs/41493722731/job/125443088099): **1935 testů, 38188 assertions, PASS**, bez skipů. Celý tehdejší CI běh nebyl zelený kvůli následným samostatným databázovým profilům; nepovažovat tento dílčí PASS za všechny kontroly. |
| Cílená lokální regrese | **97 testů / 6181 assertions PASS**: časové členství, idempotence/přestup, věk, cutover, programové platby a náhrady, registrace, původní URL a UAT příprava. Katalogová sada po doplnění `valid_to` do fixture: **7 / 205 PASS**. |
| PHPStan a potlačené nálezy | Analýza bez chyb; baseline klesla z 512 položek / 2312 nálezů na 503 / 2254, bez rozšíření potlačení. Linux PHP quality kontroluje také PSR-12 a délky řádků. |
| Anonymní HTTP | **271 tras, 0 chyb**: 32 veřejných, 237 chráněných, 2 CLI. Kontrola běžela nad trackovanými novými soubory v izolované kopii. |
| Převod na MariaDB | `MemberEvidenceMariaDbSmoke.php`: **16 kontrol PASS** na místní 10.4.32; migrace/opakování, čistý preview, odmítnutí změny dat, převod s paritou, opakovaný převod a přestup. Stejný krok prošel také v CI na **10.3 i 11.4**. |
| Záloha a obnova | `DatabaseBackupMariaDbSmoke.php`: **294 tabulek PASS** v oddělené místní DB; nové ownership/sloupcové kontrakty zahrnuté. Nejde o produkční kopii ani zálohu soukromých příloh. |
| Souběhy tréninků/náhrad | `TrainingClosureMariaDbRace.php`: **12 kontrol PASS**, skutečné čekání na zámky, jedna rezervace posledního místa, uzavření a zrušení/finalizace bez dvojího čerpání. |
| Revokace při čekání | `FamilyWriteAuthorizationRace.php`: **32 kontrol, 0 chyb**, včetně potvrzení i rollbacku revokace rodiče/sportovce. Fixture používá konkrétní politiku nabídky a explicitní povolenou návaznost. |
| Rodinný přehled | Lokální porovnání dat/HTML pro 0/1/5/20 osob prošlo. Samostatný plný profil dávkového členství pro 20 osob: shodná data/HTML, **662 vs. 795 SQL**, **27 vs. 160 členských dotazů**, shodně 663 načtených řádků. Referenční strategie načítání osob zůstala zachovaná. |
| Přednasazovací inventura | Samostatný čtecí nástroj ověřen na kompletním syntetickém schématu 294 tabulek po odstranění nového `activity_kind`: funguje i před aplikací nových migrací. |
| Composer / diff | `composer validate --strict`, kontrola rozpočtu PHPStan baseline a `git diff --check` PASS. |

Původní výkonové reference očekávaly sezonní názvy a sloupec v HTML. Záměrně
přebírají novou projekci a aktuální šablonu, ale ponechávají původní controller,
způsob načítání a rozpočty dotazů. Neodstraňují se kontroly finančních dat,
izolace cizí rodiny ani skutečných souběhů. Místní Windows kontroly nenahrazují
Linux CI; rozhodující stav celého vydání je na přesném headu PR.

## Provozní hranice

Tento protokol nedokládá produkční nasazení ani provedení E7/E8. Chybí uzavřená
produkční inventura, konkrétní mapa D1–D3 a manifest cleanupu, jeho nácvik včetně
příloh a přihlášená/lidská přejímka všech rolí. Databázové změny a opakování
operací proběhly nad syntetickými daty a oddělenou kopií aplikační zálohy.
Žádné skutečné importy, rozesílky ani bankovní přepínače se tímto ověřením nezapínají.

Rozsah a postup převodu: [implementační protokol](../../MEMBER_EVIDENCE_IMPLEMENTATION_2026-11-02.md).
