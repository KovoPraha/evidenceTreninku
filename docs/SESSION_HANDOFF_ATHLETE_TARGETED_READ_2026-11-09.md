# Předání P11-RIX — 9. 11. 2026

K OVĚŘENÍ, ownership YEAR implementace v draftu #425.
[Plán](ATHLETE_PROFILE_TARGETED_READ_2026-11-09.md),
[důkazy](verification/athlete-profile-targeted-read-20261109/RESULTS.md).

Base mainb66cc059 / runtime17110a7f, větev
codex/athlete-profile-targeted-read-20261109, stejný izolovaný worktree mimo
htdocs. Původní WIP7a7afd75 a větev fio zachované.

Zbylý dominantní profilový souhrn/header/historie závisí na indexech. CI
fixture nemá idx_sportovec/idx_mereni z referenčního schématu; skutečnou
produkci nelze odhadnout z jejího plánu. Nejprve bezpečná metadatová
inventura přes chráněný read-only workflow. Podpůrný CLI/workflow PR se
po testech/CI sloučí bez deploye aplikace. Potom odpovídající syntetická
fixture a nativní diagnóza obouDB; výběr konkrétního nejdražšího čtení.

Aktuální pokyn dovoluje při plné shodě/párovém přínosu/aktuálních CI
commit/merge/protected deploy optimalizace. Gate20% a kontrolní1.2+20ms,
žádné role/importy/platby. Podpůrný audit sám úkol neuzavírá. Další krok:
CLI metadata nástroj/testy a workflow. Nový app merge/deploy NOT RUN.


Support #424/ff182971 a metadata42704641179 SUCCESS, úklid PASS. App deploy
neproběhl. Inventura je v důkazech; indexy vlastníka/reverse v produkci existují,
odhady měření jen17. Nová větev codex/athlete-targeted-read-optimization-20261109
z ff182971. Probíhá syntetická nativní diagnóza s doplněnými shodnými indexy
před oběma variantami. Samotný prostý ownership UNION není vybraný (SQLite
regrese hustého profilu). Další krok nativní náklad parent/history/MZ pro4SID.

Diagnóza42705957545/c9 PASS oběDB a cleanup, raw v diagnostic-c9. Největší
souhrn288/1021ms; plainJOIN/IN historie nevybrané. Další krok grouped summary
join order + narrow materializace, stále bez app změny/prod DDL. První dva
přípravné FAIL zůstávají v RESULTS, nejsou PASS ani produkční incident.

SQL order/narrow/HEX a automatický nový cover index nepřinesly požadované
zrychlení (42706823562/42706996238/42707779079), raw uložené a nevybrané.
Další krok pouze syntetický FORCE cover probe; dosud žádná app/migrační
implementace. Žádný nový app merge/deploy. Zápisový probe bude čistý INSERT
VALUES, starý INSERT SELECT zahrnoval i čtení. Všechny WIP zachované.

FORCE index42708406091 nevybraný (10.3 regrese,11.4 pod20%). Žádná migrace
nevznikne. Další koncept ownership historie se stejným filtrem roku; základní
SQL aktuální rok230/293ms, all SQL zůstane původní. Další krok nativní probe.

42709050977 PASS: filtered YEAR historie382.575→160.521/386.842→171.171ms,
12scénářů přesná shoda. Vybraný jednoduchý YEAR bez intervalů/indexů; reader
implementace a fresh ff182971 oracle. Další krok full parity +17paired HTTP
1k/10k/30k, všechny kontrolní gates,52Chrome, celá aktuální CI. Žádný app
merge/deploy dosud. Syntetické sparse/empty kontroly nově skutečné12/0historií.

První úplný běh42709994135/b9 běží,14HTTPscénářů ×17párů ×3velikosti/oběDB,
52Chrome. Všech5tabulek má čerstvé společné statistiky. Lokální3/285 PASS,
nový signed64/partial-zero/extreme-year test půjde do potvrzujícího checkpointu.
Další krok: přečíst všechny výsledky a případné regrese; nic neslučovat bez
celé aktuální22CI sady a finálního ověření stejných runtime zdrojů.

Aktuálně NEPŘIJATO:42709994135/b9 celéHTTP10.3 při30k36.221→157.399ms,
11.4 PASS239.224→106.124ms. Shoda a security/cleanup PASS nestačí. Žádný
merge/deploy. Probe větev codex/athlete-targeted-probe-20261109; další krok
1k/10k/30k/fresh5tables a anti attendance/CASE. Raw first-b9 zachované.

SQL anti/CASE/IN/derived nevybrané po45scénářích/3velikostech; proti levnému
10.3plánu nebo measurement-only regresují. Probe reader vrácen přesněff182971,
PR425 zůstává b9 bez merge. Další krok42713608614/b6: DATE covering index
(datum,id,delka) pouze v syntetické DB OFF/ON, původní YEAR/range/CASE.
Velké staršíJSON jsou lossless gzip s SHA manifestem v důkazech.

RIX diagnóza uzavřená bez přijaté app varianty; header také pod20%.
Reader přesněff182971, žádný nový index/migrace. Aktivní implementace
[P11-ASSET](BOOTSTRAP_ASSET_PERFORMANCE_2026-11-10.md), další krok odstranění
3duplicitních CDN linků + Chrome/HTTP. RIX není deploy úspěch.
