# W13 — společné UI, 30. 10. 2026

Základ `d3d6002846be36a75c442dd0c946830c9d70e2ab` (W11).
`ensureAccessibleFieldNames` nyní při prvním chybějícím ID pořídí množinu
existujících ID a přidává nová. Identifikátory, pořadí, kolize i přístupná
jména jsou stejné. Množina žije pouze během synchronní inicializace.
1000 polí: 1002 úplných DOM skenů → 1; veřejná pole nepotřebují žádný.
Nejde o měření latence všech administrátorských stránek.

- RED zachycen před změnou; 4 behaviorální Node testy PASS včetně dvojího
  odeslání a opakovaného download formuláře. Nové testy jsou součástí CI.
- Chromium přes Playwright CLI: čtyři role × 1440/390 px, 8/8 PASS.
  Skutečné `publicShellNav`, `appUiAssets` a `publicShellFooter`, syntetický
  obsah formuláře; autentizaci ani celé produkční controllery tento test nenahrazuje.
- Mobilní menu a účet otevřeny klávesnicí Enter, správné role/URL/aktivní odkaz,
  žádný horizontální overflow, vstupní HTML vykreslené jako text, labely a ID
  bez kolizí. GET filtr i místní syntetický POST prošly. Chybový a prázdný stav
  zobrazeny. Screenshoty desktop/mobil viz tento adresář.
- Prohlížeč hlásil pouze chybějící favicon testovacího routeru (404), žádnou
  JS výjimku ani selhání aplikačního assetu. Router zpřístupňuje jen fixture
  HTML a assety, ostatní cesty vrací 404; neposílá zprávy ani nečte databázi.

Reprodukce: PHP CLI vygeneruje čtyři `output/playwright/{role}.html` pomocí
`tests/Support/UiShellBrowserFixture.php {role}`. Server se spouští na
`127.0.0.1:18413` s `UiShellBrowserRouter.php`; Playwright CLI spustí
`run-code --filename tests/Support/UiShellBrowserCheck.js` ve vlastní session.
Žádné nové závislosti, změny URL/rolí ani odstraněné assety. Shell už byl
sjednocen před W13, další mechanické přesuny šablon nemají doložený přínos.
