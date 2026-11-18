# P11-CATTOP — průběžné ověření

18. 11. 2026, Europe/Prague. Lokální implementace, nativní/HTTP/Chrome,
merge a deploy zatím NOT RUN. [Plán](../../CATALOG_READ_PERFORMANCE_2026-11-07.md#p11-cattop--počty-kořenových-kategorií-18-11-2026).
Base `a1bf2ba28a6e248ae99eb403d06b75749dcc04cf`, větev
`codex/catalog-top-counts-performance-20261118`, vlastní oddělený worktree.

Pouze blok topCategories uvnitř shopCatalogPageSelect. Počty prvních
doslovných segmentů před „ > “ jedním průchodem, set za záznam produktu,
nikoli za ID. Vytvoření výsledných položek zůstává v původním pořadí menu,
se stejnými názvy a nad všemi produkty před filtrem/stránkou. U nepolových
kategorií či neřetězcové cesty zcela původní smyčka; bez nového předčasného
TypeError/varování. Žádné SQL, cache, limity, ceny, prodejnost či writery
se nemění. Source contract: celý prefix/suffix modulu byteově stejný,
uvnitř povolen jen připnutý blok. Šest SQL celého výběru zůstává.

SQLite/PHP8.2.12, celý shopCatalogPageSelect,2 warmup+11 střídavých párů,
celý stav/hash přesně shodný. [Vzorky](local-probe.json), [původní sonda](local-probe.php.txt),
[kandidát](local-candidate.txt). Reprodukce sondy: kopie do output/ s názvy
cattop-probe.php.txt a cattop-candidate-block.txt, oddělený checkout tohoto
base, nikoli aktuálního optimalizovaného main. Diagnostická eval sonda má
jen platné řetězcové DTO; typové kontrakty ověřuje strict oracle níže.

| Produkty × kořeny | Celý výběr ms před → po |
| --- | ---: |
| 12 ×6 | 0,53 →0,52 |
| 1000 ×100 | 181,28 →63,27 |
| 5000 ×500 | 1012,89 →162,53 |
| 10000 ×1000 | 4897,82 →444,11 |
| 10000 ×1 | 348,23 →399,35 |

Single-root režie14,7% je zveřejněná; není to rychlejší každý katalog.
Tohle je místní diagnóza, nikoli MariaDB ani produkční měření.

Lokálně12 testů/1864 kontrol PASS včetně původní kompatibility katalogu:
doslovné mezery/case/Unicode/neplatné
cesty, duplicitní podkategorie i ID záznamů, technické cesty proti metadata
rodičům/skrytí, pořadí, filtr/hledání/stránka a searchMatches, rollback a
druhé spojení. Fallback nepolových kategorií (null/string/ArrayIterator),
přesný typ/message výjimky i počty/kódy/texty varování. Oracle explicitně
kompiluje declare(strict_types=1) jako původní modul. První typový test
odhalil implicitní slabý režim eval bez tohoto deklarování; opraven oracle,
aplikační algoritmus ani tolerance se neměnily. Původní menu testy pokračují.
Číselně vypadající kořeny01/+1/1.0/velké číslo mají přesné řetězcové prefixy.
PHPStan/styl/source contract PASS.

Brány před výsledkem:5000×500 a10000×1000 ≥20% zisku nativního celého
výběru i přehledového HTTP anonym/zákazník na obou MariaDB10.3/11.4.
Ostatní ≤20%+5ms funkce/+20msHTTP. Šest nativních sad v čerstvých procesech,
2+11 střídavých párů, query cacheOFF, stav/hash/6SQL/checksumy beze změny.
Reálné Apache/PHP nad355 migrovanými tabulkami:36 scénářů/DB (20matrix +
4large5000 +4large10000 +4single10000 +4browser120). Skutečné publikované
syntetické zboží a dostupné varianty, celé HTML/hlavičky/CSRF/filtry/řazení/
stránka,4warmup+17párů pozitivů/1pár negativů. Nonce/CSRF/checkout_key a
explicitní testovací origin normalizovány, odvozený CSP hash nejprve ověřen.
355checksumů po každé fázi i Chrome. Browser8 cold/warm/desktop/mobil
případů při120produktech/12kořenech,176měřených+48zahřívacích navigací,
4pixelové páry, formuláře/geometrie/styly/JS/CSS/console. FCP/ready gate20%+20ms.
Light/dark jsou preference profilu, veřejný shell má vlastní světlý vzhled.
Produkční profil a přihlášené/lidské UAT NOT RUN.

Původní WIP/HEAD zachovaný: kis_rosters_admin.php SHA256
`7a7afd75bfa34f736f158096ab46a671b7fee6c6faedc3701f989c0cf64733e5`.
Při úplném PASS chráněný deploy se zálohou/config/migracemi/VS/indexem/
HTTP/úklidem, UAT/importfalse. Žádné skutečné objednávky/role/rozesílky.
