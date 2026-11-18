# P11-CATREAD — index potomků a sdílení uzlů

18. 11. 2026 Europe/Prague. **NASAZENO #496/e846e5a5afa5aaaa14516f2cdd353f6f58a22e40,13CI PASS.**
[Plán](../../CATALOG_READ_PERFORMANCE_2026-11-07.md#p11-catread--další-čtení-kategorií-18-11-2026),
[PR#496](https://github.com/KovoPraha/evidenceTreninku/pull/496),
[párový běh44364589030](https://github.com/KovoPraha/evidenceTreninku/actions/runs/44364589030).
Testovaný head `fc4efb64885f5e1eef007dd887903005e6a0094f`, immutable baseline `d92fd79067ea9e63da183088d8d806b86e6f9760`.

## Změna a přesná shoda

shopCategoryDescendants: typed index rodič→děti, BFS cursor místo
array_shift a typed visited místo striktního in_array. Stejné pořadí vložení,
visited až při dequeue, numericstring/int klíče/rodiče a cyklické opakování
stejného klíče s rozdílným typem. Normalizace/výjimky/chybějící cesty stejné.
Menu navíc mimo transakci používá již načtené uzly jednoho Select:6→3SQL.
Uvnitř transakce přesně původní čtení/6SQL. Veřejná PDO funkce menu zůstává
wrapperem se stejnou signaturou/3SQL, čisté tělo menu beze změny/0SQL.
Bez cache/limitu/cen/prodejnosti/writer změn; nové uzly při každém volání.
Mimo transakci záměrně tutéž lokální inventuru pro menu i filtr, bez záruky
původních dvou verzí při editaci mezi dvěma čteními. Freshness/rollback PASS.
Source contract připíná oba moduly mimo přesný helper, wrapper/extrakci a
jediný callsite s transakčním guardem. Native před používá strict frozen
helper, celé menu a celý Select z baseline; HTTP oba původní celé moduly.

Lokální testy včetně původní kompatibility/CATTOP/menu/popisků:
BFS pořadí/cykly, číselné klíče/typy rodičů, Unicode/mezery/chybné/prázdné
cesty i přesné výjimky,60deterministických grafů ze všech počátků a opačné
pořadí uzlů, celá filtrace/hledání/řazení/stránka/searchMatches, čerstvé
metadata a rollback. Původní test celé transakční SQL sekvence zachovaný.
PHPStan/styl/source contract/odkazy PASS. [Počty/příkazy](local-checks.json).

## Místní volba kandidáta

SQLite/PHP8.2.12, celý Select,2warmup+11střídavých trojic se shodnými celými
stavy/typy/hash. Samostatný index měl největší místní zisk. Na stanici jsou velké
výkyvy nezměněných kontrol, až+28% v přehledu, který nový helper nevolá.
Toto je diagnóza, ne nativní přejímka či produkční výkonový profil.
[Vzorky](local-probe.json), [sonda](local-probe.php.txt), [prototyp](local-candidate.txt).
Reprodukce: kopie sondy do output/category-read-probe.php.txt v odděleném
checkoutu baseline d92fd790, nikoli aktuálního optimalizovaného main.

| Tvar/uzly/režim | Původní ms | Sdílené uzly ms | Index potomků ms |
| --- | ---: | ---: | ---: |
| flat/30/přehled | 1.75 | 2.01 | 2.03 |
| flat/30/filtr | 2.17 | 1.51 | 3.09 |
| flat/1000/přehled | 130.18 | 86.77 | 117.26 |
| flat/1000/filtr | 104.22 | 82.58 | 106.45 |
| tree/500/přehled | 67.64 | 49.46 | 84.89 |
| tree/500/filtr | 187.89 | 148.11 | 77.55 |
| tree/1000/přehled | 145.18 | 97.49 | 186.14 |
| tree/1000/filtr | 623.78 | 659.77 | 107.87 |
| chain/250/přehled | 190.84 | 174.22 | 221.59 |
| chain/250/filtr | 185.49 | 219.50 | 171.29 |
| chain/500/přehled | 944.05 | 725.11 | 708.60 |
| chain/500/filtr | 911.20 | 600.62 | 681.15 |
| mixed/1000/přehled | 783.99 | 593.90 | 627.02 |
| mixed/1000/filtr | 947.76 | 738.31 | 901.27 |

## Odmítnutý index a kombinovaný kandidát

[První běh](rejected-first/RESULTS.md) na7b0e0bc7 správně zastavil HTTP tree500
pod20% (10.3 anonym18,7%,11.4 zákazník15,5%). Nejde o schválený release ani
o zamlčenou regresi; bezpečné první artefakty zachované. Teprve potom
rozsah rozšířen o původně profilované sdílení uzlů, původní cíle/brány stejné.
Kombinovaná lokální sonda:2warmup+11párů, přesný celý stav/hash,6→3SQL.
[Data](local-combined-probe.json), [sonda](local-combined-probe.php.txt);
kopie do output/category-read-combined-probe.php.txt na testovaném headu.
Sonda proběhla před přidáním transakčního guardu a měří jen outsideTX;
rozhodující native/HTTP/Chrome výše v odkazovaném běhu je finální plný kód.

| Tvar/uzly/režim | Celý Select ms před → po |
| --- | ---: |
| flat/30/přehled | 2.40 → 1.66 |
| flat/1000/filtr | 126.86 → 83.96 |
| tree/500/filtr | 180.75 → 47.15 |
| tree/1000/filtr | 692.54 → 85.80 |
| chain/500/filtr | 937.68 → 765.11 |

## Nativní celý Select na MariaDB

10profilů/DB: flat30/500/1000,tree30/500/1000,chain250/500,mixed80 s filtrem
LOCALHOST-0 a flat1000 bez filtru. Jeden přiřazený produkt, syntetické DTO.
Každý vzorek čerstvý proces,2warmup+11střídavých párů, oba algoritmy zahřáté
včetně strict frozen oracle kompilace. QuerycacheOFF,6→3SQL mimo transakci, přesný celý stav/
typy/pořadí a serializedhash, checksumy3tabulek i úklid shodné.
Předem stanovené tree500/tree1000 ≥20% zisku, ostatní≤20%+5ms beze změny.
Paměť je přírůstek PHPpeak včetně mysqlnd, nikoli DB/OS; index má režii.

| MariaDB | Tvar/uzly/režim | Celý Select ms před → po | PHPpeak MiB před → po |
| --- | --- | ---: | ---: |
| 10.3 | flat/30/filtr | 2.20 → 1.17 | 0.068 → 0.052 |
| 10.3 | flat/500/filtr | 5.91 → 3.99 | 0.735 → 0.483 |
| 10.3 | flat/1000/filtr | 10.22 → 7.33 | 1.465 → 0.961 |
| 10.3 | tree/30/filtr | 2.32 → 1.24 | 0.070 → 0.054 |
| 10.3 | tree/500/filtr | 15.41 → 4.38 | 0.856 → 0.599 |
| 10.3 | tree/1000/filtr | 46.65 → 7.78 | 1.704 → 1.194 |
| 10.3 | chain/250/filtr | 17.72 → 12.97 | 3.139 → 3.007 |
| 10.3 | chain/500/filtr | 57.82 → 46.72 | 10.073 → 9.815 |
| 10.3 | mixed/80/filtr | 2.92 → 1.67 | 0.192 → 0.145 |
| 10.3 | flat/1000/přehled | 10.26 → 7.58 | 1.465 → 0.961 |
| 11.4 | flat/30/filtr | 1.47 → 0.78 | 0.068 → 0.052 |
| 11.4 | flat/500/filtr | 4.12 → 2.85 | 0.735 → 0.483 |
| 11.4 | flat/1000/filtr | 7.05 → 5.31 | 1.465 → 0.961 |
| 11.4 | tree/30/filtr | 1.53 → 0.82 | 0.070 → 0.054 |
| 11.4 | tree/500/filtr | 11.46 → 3.13 | 0.856 → 0.599 |
| 11.4 | tree/1000/filtr | 35.19 → 5.73 | 1.704 → 1.194 |
| 11.4 | chain/250/filtr | 13.75 → 10.13 | 3.139 → 3.007 |
| 11.4 | chain/500/filtr | 44.38 → 36.06 | 10.073 → 9.815 |
| 11.4 | mixed/80/filtr | 2.02 → 1.18 | 0.192 → 0.145 |
| 11.4 | flat/1000/přehled | 7.02 → 5.30 | 1.465 → 0.961 |

## Celé Apache/PHP HTTP

Vlastní úplně migrovaná DB355tabulek, reálné publikované zboží a program ze
standardní Checkout fixture, jeden produkt přiřazený listu stromu. 20scénářů
při flat30 (anonym/zákazník,přehled/filtry/hledání/prázdné/řazení/stránka a
nesprávný CSRF), pak4scénáře přehled/kategorie×oba kontexty pro flat1000,
chain500,tree500,tree1000. Celkem36scénářů/DB,4warmup+17střídavých párů
pozitivů/1pár negativů. Fixture před časováním asertuje objemy uzlů i
vybraných potomků, uložené přímo v HTTP datech. Tree filtr vybírá kořen0,
nikoli jediný list. Žádné request mocky, stejné355checksumy po každé fázi i Chrome.
Celé HTML/bezpečnostní hlavičky/raw délky shodné, žádné PHPchyby. Normalizovány
jen request nonce,64hexCSRF/32hexcheckout_key a testovací origin. Jeho CSP
hash nejprve ověřen proti skutečnému handleru a pak přeložen na stejný
handler se srovnávacím originem. Žádné ignorované bezpečnostní chyby.
Neplatný POST action=add nic nemění, nevznikají objednávky. Každý category
tree500/tree1000 ≥20% zisku, ostatní≤20%+20ms. Všechny směry zveřejněné.

| MariaDB | Fáze/scénář | Celé HTTP ms před → po | Zrychlení |
| --- | --- | ---: | ---: |
| 10.3 | matrix/overview-anonymous | 16.40 → 15.61 | +4.8% |
| 10.3 | matrix/category-anonymous | 15.98 → 15.56 | +2.6% |
| 10.3 | matrix/missing-anonymous | 16.17 → 15.57 | +3.7% |
| 10.3 | matrix/invalid-anonymous | 15.86 → 15.27 | +3.8% |
| 10.3 | matrix/search-anonymous | 15.88 → 15.38 | +3.1% |
| 10.3 | matrix/empty-anonymous | 16.53 → 16.20 | +2.0% |
| 10.3 | matrix/sort-price-anonymous | 16.49 → 16.08 | +2.5% |
| 10.3 | matrix/sort-name-anonymous | 16.63 → 15.85 | +4.7% |
| 10.3 | matrix/page-anonymous | 16.44 → 15.87 | +3.5% |
| 10.3 | matrix/overview-customer | 24.75 → 24.15 | +2.4% |
| 10.3 | matrix/category-customer | 24.57 → 23.90 | +2.7% |
| 10.3 | matrix/missing-customer | 24.79 → 23.99 | +3.2% |
| 10.3 | matrix/invalid-customer | 24.76 → 24.59 | +0.7% |
| 10.3 | matrix/search-customer | 24.71 → 23.88 | +3.4% |
| 10.3 | matrix/empty-customer | 24.87 → 24.03 | +3.4% |
| 10.3 | matrix/sort-price-customer | 24.76 → 23.68 | +4.4% |
| 10.3 | matrix/sort-name-customer | 24.56 → 24.12 | +1.8% |
| 10.3 | matrix/page-customer | 24.62 → 23.73 | +3.6% |
| 10.3 | matrix/csrf-customer | 24.62 → 23.89 | +2.9% |
| 10.3 | flat-1000/overview-anonymous | 23.98 → 21.32 | +11.1% |
| 10.3 | flat-1000/category-anonymous | 23.97 → 20.96 | +12.5% |
| 10.3 | flat-1000/overview-customer | 31.86 → 29.68 | +6.8% |
| 10.3 | flat-1000/category-customer | 32.48 → 30.50 | +6.1% |
| 10.3 | chain-500/overview-anonymous | 50.33 → 49.44 | +1.8% |
| 10.3 | chain-500/category-anonymous | 54.96 → 48.50 | +11.7% |
| 10.3 | chain-500/overview-customer | 56.97 → 55.21 | +3.1% |
| 10.3 | chain-500/category-customer | 62.25 → 55.84 | +10.3% |
| 10.3 | tree-500/overview-anonymous | 20.91 → 18.80 | +10.1% |
| 10.3 | tree-500/category-anonymous | 26.04 → 18.96 | +27.2% |
| 10.3 | tree-500/overview-customer | 28.63 → 26.28 | +8.2% |
| 10.3 | tree-500/category-customer | 34.24 → 27.23 | +20.5% |
| 10.3 | tree-1000/overview-anonymous | 25.39 → 21.14 | +16.8% |
| 10.3 | tree-1000/category-anonymous | 45.05 → 21.76 | +51.7% |
| 10.3 | tree-1000/overview-customer | 33.58 → 29.66 | +11.7% |
| 10.3 | tree-1000/category-customer | 53.51 → 30.05 | +43.8% |
| 11.4 | matrix/overview-anonymous | 11.84 → 10.83 | +8.6% |
| 11.4 | matrix/category-anonymous | 11.14 → 10.69 | +4.0% |
| 11.4 | matrix/missing-anonymous | 11.17 → 10.85 | +2.8% |
| 11.4 | matrix/invalid-anonymous | 11.02 → 10.80 | +2.0% |
| 11.4 | matrix/search-anonymous | 11.38 → 10.80 | +5.1% |
| 11.4 | matrix/empty-anonymous | 11.33 → 11.04 | +2.6% |
| 11.4 | matrix/sort-price-anonymous | 11.29 → 10.89 | +3.6% |
| 11.4 | matrix/sort-name-anonymous | 11.27 → 10.87 | +3.6% |
| 11.4 | matrix/page-anonymous | 11.34 → 10.78 | +5.0% |
| 11.4 | matrix/overview-customer | 16.99 → 16.57 | +2.4% |
| 11.4 | matrix/category-customer | 16.94 → 16.48 | +2.7% |
| 11.4 | matrix/missing-customer | 17.01 → 16.67 | +2.0% |
| 11.4 | matrix/invalid-customer | 16.86 → 16.66 | +1.2% |
| 11.4 | matrix/search-customer | 17.13 → 16.50 | +3.7% |
| 11.4 | matrix/empty-customer | 16.88 → 16.53 | +2.1% |
| 11.4 | matrix/sort-price-customer | 17.11 → 16.82 | +1.7% |
| 11.4 | matrix/sort-name-customer | 16.95 → 16.76 | +1.1% |
| 11.4 | matrix/page-customer | 17.55 → 16.90 | +3.7% |
| 11.4 | matrix/csrf-customer | 17.15 → 16.75 | +2.3% |
| 11.4 | flat-1000/overview-anonymous | 16.51 → 14.81 | +10.3% |
| 11.4 | flat-1000/category-anonymous | 16.52 → 14.76 | +10.7% |
| 11.4 | flat-1000/overview-customer | 22.36 → 20.77 | +7.1% |
| 11.4 | flat-1000/category-customer | 22.26 → 20.62 | +7.4% |
| 11.4 | chain-500/overview-anonymous | 37.27 → 36.35 | +2.5% |
| 11.4 | chain-500/category-anonymous | 40.59 → 35.06 | +13.6% |
| 11.4 | chain-500/overview-customer | 42.53 → 40.58 | +4.6% |
| 11.4 | chain-500/category-customer | 45.93 → 40.71 | +11.4% |
| 11.4 | tree-500/overview-anonymous | 14.60 → 13.09 | +10.3% |
| 11.4 | tree-500/category-anonymous | 18.06 → 13.23 | +26.8% |
| 11.4 | tree-500/overview-customer | 20.66 → 18.89 | +8.6% |
| 11.4 | tree-500/category-customer | 24.29 → 19.21 | +20.9% |
| 11.4 | tree-1000/overview-anonymous | 17.98 → 15.23 | +15.3% |
| 11.4 | tree-1000/category-anonymous | 32.20 → 15.84 | +50.8% |
| 11.4 | tree-1000/overview-customer | 23.67 → 20.88 | +11.8% |
| 11.4 | tree-1000/category-customer | 38.26 → 21.18 | +44.6% |

## Skutečný Chrome

Tree1000, přehled a filtr kořene0 s produktem, desktop1036×703/mobil390×844,
cold/warm8případů,176měřených+48zahřívacích navigací,4identické full-page
pixelové páry. Stejné formuláře/styly/geometrie/overflow/BootstrapJS, bez
console/request chyb. CDP skutečný CSS payload/hash, cold232803decodedB a
stejný gzip přenos, warm0B. FCP/ready≤20%+20ms beze změny. Všechny směry:

| Cesta/profil | Cache | FCP ms před → po | Hotový obsah ms před → po |
| --- | --- | ---: | ---: |
| eshop-overview/light | cold | 68 → 68 | 95.4 → 95.5 |
| eshop-overview/light | warm | 56 → 52 | 71.3 → 71.6 |
| eshop-overview/dark | cold | 64 → 64 | 94.7 → 90.5 |
| eshop-overview/dark | warm | 52 → 52 | 70.6 → 68.6 |
| eshop-category/light | cold | 84 → 64 | 107.7 → 93.7 |
| eshop-category/light | warm | 68 → 52 | 86.7 → 68.1 |
| eshop-category/dark | cold | 80 → 68 | 111.7 → 96.1 |
| eshop-category/dark | warm | 68 → 52 | 88.5 → 69.1 |

Light/dark jsou preference profilu; veřejný shell má vlastní světlý vzhled.
[Browser data](mariadb-11.4/browser-summary.json), [úklid](mariadb-11.4/cleanup.json).
Historická CATLABEL lane je zachovaná pro manuální replay původního
28496cca7f6ff97b37e91aac7f2587dd9516de1d; starý source lock se neoslabuje.
Aktuální2CATDESC joby nahrazují tuto lane s plnou paritou a novými cíli.
CATTOP je také historický replay na d66cbcd8. Všechny původní unit a obecné
CI zachované; aktuální lane pokrývá oba změněné moduly při stejných branách.

Produkční srovnání zahrnuje také skutečný veřejný filtr kategorie.

## Vydání a WIP

[Chráněný deploy44367469594](https://github.com/KovoPraha/evidenceTreninku/actions/runs/44367469594) SUCCESS,
2512testů/59480kontrol, záloha394tabulek/2triggerů.
Přesný testovaný/sloučený strom `a578c0f29a3197d0d69b7185adfcb1b3a06c616b`. Config zachovaný bez doplnění,
pending migrace[], VS/zdravotní index shodné, aktivace/HTTP200/úklid PASS.
Přímý runner HTTP200. UAT/importfalse, import přeskočený.
Živé anonymní GET katalogu (celé normalizované HTML/hlavičky), administrace
redirect a canonical CSS před/po stejné: [před](production-before.json), [po](production-after.json).
Produkční výkonový profil a přihlášené/lidské UAT NOT RUN; žádné skutečné
produkty/objednávky/platby/role/rozesílky tímto ověřením neměněné.
Doklady vlastním docs PR bez redeploye. [Receipt](release.json).

Původní worktree/HEAD `d1221f3594a965096d98da5e86a27097f66cd380` a
`kis_rosters_admin.php` SHA256
`7a7afd75bfa34f736f158096ab46a671b7fee6c6faedc3701f989c0cf64733e5`
zachované. Souběžná práce ani staré prototypy nepřebírané.
[Integrita](artifact-integrity.json): SHA-256 skutečných Git blobů.
