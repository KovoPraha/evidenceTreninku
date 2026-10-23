# Doplňující kontrola účetního exportu R36 při R38–R44

- Objednávky používají kanonický typ přídělu `shop_order` i v nominativním přehledu pohledávek.
- `club_payment_returns` propojuje původní a návratový příděl, případ a skutečný peněžní pohyb. Export filtruje datum skutečného návratového přídělu k hranici období. Vazba je ve snapshotu, změnovém protokolu, listu Vydaje a klubovém PDF; nepřidává další peněžní obrat.
- Pozdě evidované výdaje, zálohy a verze odměn s dřívějším skutečným vypořádáním jsou dohledatelné a výslovně označené v kontrolách a XLSX. Žádné historické schválení se nedovozuje zpětně.
- 8 integračních testů / 24 assertions PASS pro projekce, datumové hranice, návraty, PDF a explicitní neověřené stavy.
- Skutečné HTTP vytvoření a stažení účetního ZIP: 34 scénářů PASS, ověřeny všechny hashe manifestu, PDF a deset listů XLSX. Důkaz `r36-return-export-http.json`.

Výstupy zůstávají technickým lokálním ověřením syntetických dat; nejsou potvrzením převzetí skutečné účetní ani produkčního provozu.
