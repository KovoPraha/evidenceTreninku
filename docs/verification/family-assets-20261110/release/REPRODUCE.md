# Reprodukce a hranice ověření ASSET7

Finální native [42903018197](https://github.com/KovoPraha/evidenceTreninku/actions/runs/42903018197)
používá immutable baseline4d1e99412d83c7bb70da77694f32e31b93ff8284 a finální
headb65137a06de5b4caafa6e066938b13f83495cd2b. Reprodukovat na Linuxu z tohoto
checkoutu podle `.github/workflows/family-assets-performance.yml`: MariaDB10.3/11.4,
zamčené Composer závislosti, skutečný Apache a Chrome/CLI0.1.23. Povinné
`EVIDENCE_PERFORMANCE_CONFIRM=SYNTHETIC-ONLY`, explicitní localhost/schema;
nepouštět fixture proti produkci. Source/HTTP/Browser/Profile kód je v
`tests/Support/FamilyAssets*`, sdílený základ ve FullHttpReadFixture/WorkspaceAssetsApache.

Native JSON/GZ obsahují všechna jednotlivá měření, role/CSRF/revokační otisky,
souhrnné mediány, source/shared-context a cleanup. Browser20kombinací má po34
měřených vzorcích a8warmupech,40funkčních záznamů a10GD pixelových párů. Cold
síť/CDP a decoded CSS/SRI jsou skutečné odpovědi, žádné mocky/umělá latence.
Nemaskuje se HTML/JS obecně: pouze konkrétní CDNtag a request nonce/64hexCSRF.
Source oracle připouští jen4vyjmenované guard závorky proti pinned baseline.
Původní registrační wiringtest i guard beze změny.

`live-http-probe.py.txt` je bezpečný archiv produkčního čtecího probe. Pro nové
měření jej zkopírovat do `output/family-live-http.py` v rootu checkoutu a spustit
`python output/family-live-http.py before` / `after` ve Windows/XAMPP. Používá
TLS-ověřený systémový curl, nepřeskakuje redirecty a neodesílá formulář. Export
obsahuje jen safelist hlaviček/normalizované otisky,5canonical assetpayloadů a
čas capture; rawHTML/headers zůstávají ve vlastním privátním output adresáři.
Historický before nelze nahradit novým capture po release. Aktuální HTML a
payloady se porovnávají s konkrétním immutable Gitblobem, ne CRLF worktree bajty.

Produkční čtyři anonymní redirecty302+login200 a5canonical payloadů jsou shodné.
Anonymní redirect neprovádí soukromou šablonu: **přihlášené produkční UAT a live
soukromý waterfall NOT RUN**. Dostupný uživatelský Chrome končí na loginu;
žádné heslo nebylo zadáno ani reálný formulář odeslán. Native není náhrada za
produkční UAT. Fyzický telefon a obnova této nové zálohy také NOT RUN.
Chráněný deploy potvrzuje aktivaci všech4PHP souborů; runnerHTTP0 a předepsaný
serverový curl fallback200 jsou odlišené v receipt. UAT/importfalse, další
produkční nasazení kvůli samotným dokladům není potřeba.
