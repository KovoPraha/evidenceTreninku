# Předání účetních podkladů — izolovaná technická přejímka

24. 10. 2026. Navazuje na [předchozí přejímku v Chrome](../linux-browser-20261024/RESULTS.md).
**Dříve blokovaný bod stažení je nyní ověřený.** V Chrome byly skutečně staženy
revize 8, 9 a 10 a znovu revize 9 a 10. Soubory na klientském disku odpovídají
neměnným archivům. Proběhla simulace předání, vrácení a převzetí nové revize.
Nebyla potvrzena nová chyba aplikace; kód aplikace ani nastavení ochran se neměnily.

## Rozsah a izolace

- Pracovní kopie `C:\xampp\htdocs\evidencePavel-finance-b1-20261022`, základ `a0fcfbc8`.
- Chrome, pouze `http://127.0.0.1:18086`; syntetická databáze `evidence_b1`, port 33426.
- [Čtecí kontrola izolace](isolation-probe.json): PASS, 327 čitelných tabulek,
  soukromé soubory mimo webroot a vypnuté vnější transporty PHP webového serveru.
  Závěrečné opakování v 22:41:43 CEST rovněž PASS.
- V prohlížeči vznikly pouze lokální revize #37/r9 a #38/r10 a jejich auditní události.
  HTTP sady vytvořily další výslovně syntetické účty, rozhodnutí, přílohy a exporty.
- Produkce nebyla změněna ani nasazena. Nic nebylo zasláno skutečné účetní.

## Stažení a historie

Při původním průchodu Chrome ukázal `ERR_BLOCKED_BY_CLIENT`, ačkoli server vrátil 200.
Nyní byl před aktivací běžného odkazu registrován dokumentovaný download event;
jeho `path()` vrátil skutečný klientský soubor. Původní blokace se neopakovala.
To není průkaz přesné příčiny původního blokování ani tvrzení, že byl opraven prohlížeč.
Nebyly měněny bezpečnostní volby, rozšíření, CSP ani hlavičky odpovědi.

Některé automatizované kliky v dlouhé stránce neposunuly formulář nebo nevyvolaly
download event. Po kontrole skutečného stavu byl stejný ovládací prvek aktivován
klávesou Enter. Nebyl obcházen download endpoint ani zabezpečení prohlížeče.

| Revize / ID | Provedený scénář | Výsledek |
|---|---|---|
| 8 / 36 | Stažení dříve blokovaného archivu | Soubor na disku, SHA shodný se serverem a původním důkazem; bez předání |
| 9 / 37 | Vytvoření → stažení → předáno → vráceno k doplnění | Oba kroky viditelné v UI i auditu; původní ZIP zachován |
| 10 / 38 | Nová revize po vrácení → stažení → předáno → převzato | Vazba na #37, samostatná historie, stále `incomplete` |
| 9 a 10 | Opakované stažení po převzetí r10 | Oba klientské soubory bitově shodné s původními |

Všechny poznámky obsahují `LOCALHOST NEPLATIT` a výslovné označení simulace.
Převzetí zaznamenal kontrolní pracovní účet jako simulaci doloženého kroku;
nešlo o přihlášení nebo podpis skutečné účetní.
[Čtecí audit databáze a klientských souborů](browser-batches.json) ověřuje přesné
pořadí událostí, vazby revizí, kontrolní otisky a zachování stavu `incomplete`.
[Snímek výsledku](browser-accepted.png) zachycuje převzetí i upozornění na neúplnost.

| Revize | SHA-256 ZIP |
|---|---|
| 8 | `cae8e9faac9cfd4baacc7bedd26c60baa6febbdc4913090f0240bf7131bf2f0f` |
| 9 | `42e8fe289ab0a2335f917f4f71ab7abb21dee21cb523b211a00e6d88eada1d3f` |
| 10 | `bcb8287ed4b022f03bd34fdf8ae4c34f83812024ef43e6d1998288e8ed466248` |

## Obsah pro účetní

[Revize 9](rev9-verification.json) a [revize 10](rev10-verification.json):
každá **647 kontrol PASS**, celkem 1 294 dílčích ověření. Nejde o počet samostatných
uživatelských scénářů. Každý ZIP má 45 členů včetně manifestu, 1 XLSX a 33 PDF.

- CRC, bezpečné názvy členů, velikost a SHA všech 44 členů uvedených v manifestu.
- Deset listů XLSX, typy buněk, číselné peníze a textové VS, absence vzorců a
  chybových buněk, filtry a zmrazené záhlaví.
- 150 peněžních pohybů bez duplicit, částky v haléřové přesnosti a označení období.
  Zahrnuté historické vazby nejsou automaticky obratem aktuálního měsíce.
- 21 účetních dokladů: identita, částky, položky, VS a vazba na neměnné PDF.
- Salda ověřených účtů proti počátkům a skutečným pohybům; neověřené počátky
  zůstávají označené, nejsou nahrazené nulou.
- Všech 33 PDF otevřeno parserem a textově přečteno po stránkách. Vizuálně posouzeny
  vybrané výřezy šesti XLSX listů a první stránky faktury, opravy a klubového souhrnu.
  Netvrdí se vizuální přejímka všech stran nebo ovládání Microsoft Excelu.

[Porovnání revizí](revision-comparison.json): finanční obsah r9/r10 shodný;
liší se pouze číslo revize, předchůdce, otisk předchozího snapshotu a rozhodný okamžik.
Všechna archivovaná původní PDF jsou bitově shodná. Souhrn `klubove-vyporadani.pdf`
se pro novou revizi generuje znovu; jeho binární shoda mezi revizemi není požadována.

Vizualizace: [Manifest](xlsx-Manifest.png), [Peníze](xlsx-Penize.png),
[Doklady](xlsx-Doklady.png), [Saldo](xlsx-Saldo.png), [Výdaje](xlsx-Vydaje.png),
[Sklad](xlsx-Sklad.png), [faktura](pdf-invoice.png), [oprava](pdf-correction.png),
[souhrn](pdf-statement.png). Snímky obsahu pocházejí z r9; r10 byl znovu celý
programově validován a srovnán s r9.

Čtecí kontrola je reprodukovatelná pomocí `verify_archive.py <ZIP> <nový_adresář>`
(Python s pypdf). Prvotní předpoklady kontrolního skriptu byly upraveny pro český
stav `NEÚPLNÝ`, přípustný prázdný VS a běžný subhaléřový šum IEEE čísel v XLSX;
nešlo o chyby aplikace ani změnu účetních částek.

## Ochrany a návaznosti

- `tests/Support/AccountingExportHttpAcceptance.php`: **65 kontrol PASS** —
  finance versus ostatní role, přímé stažení, CSRF, potvrzení, dvojí odeslání,
  revize a audit downloadů. [Výsledky](accounting-http.json).
- `tests/Support/ClubAccountingHttpAcceptance.php`: **34 kontrol PASS** —
  soukromé přílohy, původní PDF, klubové podklady, deset listů XLSX, předání,
  odmítnutí zastaralého formuláře, převzetí bez přepisu ZIP a okamžité odebrání
  přístupu po odebrání finanční role. [Výsledky](club-http.json).
- HTTP klientské sady byly spuštěny běžným CLI PHP s cURL; jejich guard omezuje
  databázi a URL na izolované prostředí. První spuštění klientské sady s webovým
  ini skončilo na chybějícím `curl_init()`. Webový server zůstal po celou dobu na
  ini s vypnutými vnějšími transporty; jeho ochrany nebyly změněny.

## Ukázka předání a zbývající rozhodnutí

[Ukázkový ZIP r10](LOCALHOST-NEPLATIT-ucetni-podklady-r10.zip) je nezměněná kopie
skutečného souboru staženého v Chrome. Obsahuje pouze syntetické testovací podklady.
[Postup pro účetní](PREDANI_UCETNI.md) popisuje kontrolu a zpětnou vazbu.

Technický průchod je dokončen. Skutečné převzetí účetní, účetní metodika,
odsouhlasení historických zdrojů, počátečních stavů, ocenění a rozdělení odpovědností
zůstávají podle [D01–D20](../../CLUB_COMPLETION_DECISIONS.md) otevřené.
Převzetí technického souboru nesmí potlačit seznam výjimek ani předstírat kompletní
účetní měsíc. Tato přejímka není nasazení, produkční pilot ani potvrzení importu
do konkrétního účetního programu.
