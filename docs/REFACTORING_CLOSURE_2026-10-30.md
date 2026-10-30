# Technické uzavření refaktoringu W6–W15

Stav: implementace a cílená místní přejímka dokončeny; konečné CI, sloučení
a provozní release evidence jsou samostatná závěrečná brána. Aktuální živé
vydání vždy určuje [CURRENT_STATE](CURRENT_STATE.md).

## Výsledek podle oblastí

| Vlna | Přijatá realizace a důkaz | Dispozice dalších kandidátů |
| --- | --- | --- |
| W6 | [Kapacity a migrované schéma](REFACTORING_W63_2026-10-29.md), čistá projekce, omezená materializace. | Zámky a deduplikace zachovány; žádný nový framework ani globální cache. |
| W7 | [Celý rodinný přehled](REFACTORING_W72_2026-10-29.md), [metadata](REFACTORING_W73_2026-10-30.md), [členská pole](REFACTORING_W74_2026-10-30.md), [registrář](REFACTORING_W75_2026-10-30.md). | R04 finanční historie byla přesunuta do W10; nové autorizační čtení při zápisu se nezkracuje cache. |
| W8 | [Timeline](REFACTORING_W76_W81_2026-10-30.md), [filtry](REFACTORING_W82_2026-10-30.md), [kopírování/ICS](REFACTORING_W83_2026-10-30.md), [kalendář](REFACTORING_W84_2026-10-30.md), [rezervace/docházka/zdravotní souběhy](REFACTORING_W85_2026-10-30.md). | Transakční guardy a historické revize zůstávají doménově oddělené. |
| W9 | [Celá skladová stránka](REFACTORING_W9_2026-10-30.md), [košík a skladové souběhy](REFACTORING_W92_2026-10-30.md). | Ceny, VS, kapacity a zámky se počítají kanonicky; žádná plošná přestavba checkout writeru. |
| W10 | [Indexované projekce](REFACTORING_W10_2026-10-30.md), [100k XLSX](REFACTORING_W102_2026-10-30.md), [klubová salda](REFACTORING_W103_2026-10-30.md), [ledger metadata](REFACTORING_W104_2026-10-30.md). | PDF není streamované; celý ZIP má oddělený objemový důkaz W12. Snapshot, částky a pořadí jsou zachovány. |
| W11 | [Claim/retry/crash](REFACTORING_W11_2026-10-30.md), [cílená kontrola příjemce](REFACTORING_W112_2026-10-30.md). | Nesjednocovat doménové payloady. Neznámý výsledek odeslání se drží pro kontrolu; nepředstírat exactly-once externí doručení. |
| W12 | [Parser, index preview, exportní snapshot a úplná obnova](REFACTORING_W12_2026-10-30.md). | Bez změny identity matching pravidel, reálných importů a aktivace providerů; velký součin kandidátů a libovolně velký PDF nemají nový SLA. |
| W13 | [Sdílené UI a formulářové labely](REFACTORING_W13_2026-10-30.md), čtyři role × desktop/mobil v Chromiu. | Shell byl sjednocen již dříve; další přesuny šablon a mazání assetů bez doloženého přínosu se neprovádějí. |
| W14 | [Brána PHPStan baseline](REFACTORING_W14_2026-10-30.md), negativní detekce a přenosné fixtures. | Zachováno 515 položek / 2350 legacy nálezů. Technická etapa není tvrzení o nulovém dluhu nebo úplném přepsání aplikace. |
| W15 | [Propojená technická přejímka](verification/refactoring-w15-20261030/RESULTS.md), invarianty a finální provozní evidence. | Lidské/účetní UAT, skutečná ocenění, identity, přílohy a aktivace integrací zůstávají oddělené. |

## Závěrečná brána

Propojený syntetický případ pokračuje z existujícího ParentJourneyAcceptanceTest:
ověření e-mailu a hesla, čekání na platbu/identitu, aktivace programu a soupisky,
opakované potvrzení bez duplicity a čtyři lokální zprávy. Nová část uzavře
skutečnou docházku stejné osoby, převezme stejnou platbu do ledgeru právě
jednou, vytvoří skutečný ZIP a ověří hash. Rodinná a finanční revokace jsou
účinné i pro existující session a již vytvořený export.

To je aplikační integrační důkaz na syntetickém SQLite fixture. Databázové
zámky, kolace, crash a obnova mají samostatné opakované MariaDB 10.3/11.4
testy v CI; prohlížeč W13 ověřuje sdílený shell, nikoli veškeré produkční UAT.
Každá z těchto vrstev je potřebná, žádná nenahrazuje ostatní.

Po úspěšných kontrolách se nasadí přes chráněný main s privátní zálohou,
bez importu a s `uat_schvaleno=false`. Nový index stagingu zvýší počet
migračních záznamů o 1. Po nasazení se ověří živé SHA/run, katalog/detail,
neveřejné trasy, config hash, flags, VS a migrační stav. Návrat aplikační změny
musí zachovat nový migrační soubor i ledger v dopředném opravném vydání;
starý katalog by nový záznam odmítl. Index ponechat, bez automatického mazání.

## Provozní incident mimo změny

Před nasazením W10.3/W10.4 katalog vrátil 500 a read-only audit opakovaně
SQLSTATE HY000 / driver 1021 (MariaDB ER_DISK_FULL). Produkční SHA zůstalo
W10.2. Diagnostické PR #250/#253 byly sloučeny přes běžné CI; nikdo z této
práce neodstraňoval produkční soubory ani data. Provoz se obnovil bez takové
mutace, následný audit a HTTP 26/26 prošly. Příčina uvolnění prostoru není
doložena, a proto se incident nevydává za opravenou chybu refaktoringu.

Nová diagnostika umí při dalším selhání vrátit pouze bezpečné údaje o fázi,
SQLSTATE a odděleně hostu/webovém prostoru/DB cestách. Protože úspěšný audit
chybovou větev nespouští, neznáme z něj aktuální volné místo databázového hostu.
Při opakování je nutné použít tento důkaz; nesmazat rollback ani zálohy odhadem.
