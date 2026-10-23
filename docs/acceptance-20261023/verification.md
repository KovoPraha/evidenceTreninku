# Ověření připraveného balíčku

23. 10. 2026. Tato kontrola ověřuje dokumenty, jejich funkčnost a původ ukázkových
souborů. **Není provedením lidských UAT scénářů, účetním převzetím ani pilotem.**

| Oblast | Výsledek |
|---|---|
| Scénáře | 43 unikátních ID, 188 kroků, všechny výchozí stavy NEPROVEDENO; existující zdrojové soubory a cesty obrazovek, platné návaznosti bez cyklu. |
| HTML | 12 kontrol PASS v novém headless Chrome: výchozí stav, hledání, role, priority, místní uložení, export/import JSON, nepovolení produkce, čistý reset, desktop, mobil bez vodorovného přetékání, účetní stránka bez JS chyb. HTTP(S) blokované. |
| PDF průvodce | 53 stran, vložený český font, render Poppler; vizuálně kontrolované stránky, tabulky, mobilní/desktopové HTML a opravené zalomení F10. |
| Účetní export | 17 read-only kontrol PASS; původní ZIP, všech 20 položek manifestu, XLSX a 18 PDF zachované. Detail v accountant-sample.json. |
| XLSX | 10 listů, ověřeny počty/obsah z XML, bez chybových buněk a vzorců. Toto není důkaz otevření v účetním programu; to je UC01/UC16. |
| Nativní PDF | 18 souborů / 30 stran renderovaných a vizuálně kontrolovaných agentem pro účetní ukázku. |
| Přenosný ZIP | 47 souborů, platný ZIP a SHA-256 všech zabalených položek; bez přístupového souboru a výsledků dočasného browser testu. |
| Odkazy | 16 statických relativních HTML odkazů ověřeno, žádný chybějící cíl. Aplikační odkazy vedou pouze na localhost. |
| Prostředí | Read-only probe PASS, 326 čitelných vlastněných tabulek; místní katalogové podklady vytvořeny přes UI, bez skladových pohybů a objednávek. |

Průvodce byl zkontrolován na desktopu 1440 px a mobilu 390 px.
Testovací hodnoty stavu v automatickém browser testu byly vymazány a jeho JSON
zůstal jen v `tmp/acceptance-20261023`, mimo přenosný ZIP.

## Kontrolní otisky

- Celý přenosný ZIP: `0b850c4d2968f061a29dae7fa367ca49ace33353add5ac9cf9a497d32b29a304`.
- Původní účetní ZIP #22: `b7ca30348c812e584df076d5c8d2b6bd32f45fb430d3c2d7ae781f44c2428f66`.
- Nativní XLSX: `6903d4ffc7d218f4f23aa7a4c0b271c7530bb0680403c3d27c01e18340ec7850`.

Strojové souhrny této kontroly: [build-verification.json](build-verification.json),
[html-verification.json](html-verification.json),
[portable-verification.json](portable-verification.json).

Předchozí PHPUnit/HTTP/souběhové testy v `technicke-dukazy` balíčku jsou historické
protokoly předchozí implementace. V tomto dokumentačním balíku se aplikační kód
nezměnil a celá aplikační sada se znovu nespouštěla. Obnova B1 a její historické
omezení zůstávají popsané v předchozím recovery protokolu.

Nic nebylo nasazeno, importováno do produkce ani odesláno účetní.
