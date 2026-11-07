# Kalibrace a oddělení důkazů, 7. 11. 2026

- První inicializace MariaDB dostala chybně sestavený PowerShell argument a
  vytvořila v novém vlastním runtime adresář `data --port=33459 --silent`.
  Nikdy se nespouštěl. Správný nový datadir `data` byl poté inicializovaný
  samostatnými argumenty, port i datadir ověřené nástrojem před každou operací.
  Oba adresáře jsou ponechané, žádné rekurzivní mazání.
- První otevření loginu před dokončením seedu vrátilo místní 503. Nejde o
  aplikační regresi ani časový vzorek; finální standardní loginy byly úspěšné.
- První DOM anotace v HTTP měřiči používala neexistující `article.lesson-choice`.
  Počet lekcí byl opraven mimo časování přes skutečný `#lesson-work` a ověřený
  původní DOM checker (9 případů před změnou). Časové vzorky se tím neměnily.
- První porovnání vybraných osob chybně vyžadovalo pořadí stejnojmenných ID.
  Původní SQL je neurčovalo; stránkování nyní používá stabilní ID tie-breaker.
  Výběr je množina, kanonický writer ID normalizuje. Opravené srovnání ověřilo
  všech 21 aktuálních hashů/statusů bez opakování nebo nahrazení časových vzorků.
  Distinktní jména, preference, kolace a všechny osoby mají samostatnou paritu.
- `Get-NetTCPConnection` jednou nevrátil živý PHP posluchač. Proces se proto
  zastavoval podle přesného příkazového řádku s vlastním náhodným runtime;
  úklid ověřuje skutečné TCP odmítnutí obou portů přes socket, nikoli jen CIM.
- Výřezy `picker-form-*` překryla pevná horní lišta. Jsou zachované jako
  kalibrace elementového snímku, nikoli důkaz vadného nového pickeru.
  Pro vizuální kontrolu jsou celé stránky JS/noJS desktop/mobil. Další pokusné
  viewport snímky ze smooth scrollu se do protokolu nepoužívají.
- Surové měření, oddělené SQL/PHP diagnostiky, nativní čtecí browser UAT a
  následné záměrné syntetické zápisy jsou odlišné úseky. Revize 53 / 201 osob
  a osobní omluva vznikly až po časování; nefalšují součty čtecího páru.
- Login hesla byla jen v místních credentials a dočasných skriptech/logách,
  které se odstranily. V protokolu nejsou credentials, cookies, config ani HTML.
