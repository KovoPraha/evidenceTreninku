# Vydání ručního modulu Zásilkovny — 22. 10. 2026

## Autorizovaný rozsah

Vlastník výslovně požádal o commit, push a nasazení připravených úprav.
Tím nahrazuje starší zákaz vzdálených kroků pro tento rozsah. **Doprava
i lokální simulace zůstávají vypnuté.** Nevkládat produkční veřejný klíč,
nezakládat objednávky či zásilky a nespouštět poštovní worker. Fakturace
zůstává mimo rozsah. Lokální config, DB a screenshoty se necommitují.

Rozsah: checkbox fyzického produktu mimo kroužky/služby, výchozí dopravné
150 Kč a stávající administrace ceny, CZ Z-BOX widget/aktuální validace,
expediční přehled, auditovaná oprava štítku, ochrana před duplicitou
a potvrzení ručního storna zásilky. Nezavádí privátní API pro podání.

## Postup a kontrolní body

- [x] Commit vyjmenovaných souborů a začlenění aktuálního `origin/main`;
  zachovat číselné řady, variabilní symboly, všechny migrace a zálohovací kontrakt.
- [x] PHP syntaxe, PHPUnit a MariaDB záloha/obnova, potřebné integrační smoke.
- [x] Push, PR a zelené CI včetně MariaDB 10.3/11.4 a CodeQL; sloučení.
- [x] Nasazení `deploy-production.yml` na přesném schváleném main SHA,
  `potvrzeni=NASADIT`, `uat_schvaleno=false`, `importovat_krouzky=false`.
- [x] Ověřit release SHA, zálohu, migrace, zachování VS a HTTP smoke.
- [x] Živě zkontrolovat administraci dopravy/ceny, checkbox produktu,
  objednávky a frontu bez zápisů do objednávek a bez odesílání.
- [x] Zapsat produkční důkazy a konečný stav do plánu a obou handoffů.

## Výchozí důkazy

[Plán a lokální reálná mapa](PACKETA_MODULE_AUDIT_2026-10-21.md): skutečný
CZ Z-BOX #22312, callback a aktuální serverová validace PASS. Lokálně
enabled=0, cena 150 Kč, délka klíče 16, simulace false; fronta 5 pending /
0 pokusů, 2 objednávky. Tyto počty nejsou produkčním důkazem.

Při revizi doplněno aktuální zamykací čtení kontroly duplicitního čísla
zásilky: nesmí použít starý transakční snapshot po čekání na společný zámek.

## Příprava vydání

Implementace `300cf993`, integrace main `886c07b2` v commitu `55fdf973`.
Sloučení zachovává číselné řady, všechny dosavadní migrační položky a jejich
checksummy, zálohovací tabulky i nový produktový atribut. Společný kontrakt
zálohy `2026-10-22.1`. Nová migrace má výslovné LF v `.gitattributes`.

Lokální kontroly: **1 198 testů / 26 816 kontrol PASS**, 17 změněných PHP
souborů syntaxe PASS, Composer validace
a audit bez známých zranitelností, MariaDB záloha/obnova **198 tabulek PASS**,
souběžný checkout/potvrzení platby/poslední místo programu PASS, lokálně
**112 migrací aktuálních**.
Historické varování `20260922190000_member_fee_standing_orders.php:13`
o `columns` zůstává beze změny; smoke prošel.

Výchozí produkční UI před vydáním: doprava **vypnutá**, klíč prázdný,
cena **0 Kč** (migrace ji má předvyplnit na 150 Kč). Fronta **3 pending,
0 processing/failed/sent, všechny pokusy 0**, žádné opakování/worker.
Otisk tabulky fronty SHA-256:
`b8a0744fcaea8d6575441dae9e30268bee0c8bb52fe60646f627ebc927e1e29e`.
Původní pracovní pozice Finance; po ověření ji obnovit.

První CI `39775992411`: PHPUnit, CodeQL a MariaDB 11.4 prošly, MariaDB 10.3
odmítla `INSERT ... SELECT` bez FROM před WHERE v nové migraci Packety.
Dosud nenasazená migrace opravena na přímý INSERT po kontrole existence
pod migračním zámkem. Historické produkční migrace se nemění. Před vydáním
se zopakovalo CI; lokální ledger tohoto jediného nového kroku byl odstraněn
a opravena migrace znovu aplikována běžným runnerem (bez přepisování checksumu
a bez resetu lokálních obchodních dat).

## PR, CI a nasazení

[PR #162](https://github.com/KovoPraha/evidenceTreninku/pull/162) sloučen
na aplikační SHA **`b6b4251548fc6a99a4c654dc00a70589cc8d2386`** po opravě
MariaDB 10.3 v `acb191ff`. Úspěšné
[CI 39776280900](https://github.com/KovoPraha/evidenceTreninku/actions/runs/39776280900)
a [CodeQL 39776275878](https://github.com/KovoPraha/evidenceTreninku/actions/runs/39776275878).

Úspěšný chráněný [deploy 39776493246](https://github.com/KovoPraha/evidenceTreninku/actions/runs/39776493246)
na přesném SHA výše, `potvrzeni=NASADIT`, `uat_schvaleno=false`,
`importovat_krouzky=false`. Aktivace 22. 10. 2026 přibližně 14:16
Europe/Prague; konečný HTTP smoke přibližně 14:18.

- Produkční záloha **246 tabulek / 2 triggerů**, kontrakt `2026-10-22.1`.
- Migrace **112 aktuálních**, `current=true`, `pending=[]`.
- Zachování VS před/po: **unchanged=true**, všech osm původních plateb
  se shodným otiskem. Tabulky stálých příspěvkových VS prázdné a nezměněné.
- Aktivace ověřeného release a závěrečný HTTP smoke **ok=true, http=200**.
- Testy deploy runneru **1 198 / 26 799 PASS**; počet kontrol se mírně
  liší od lokálního prostředí. Testy výsledného main
  [39776484081](https://github.com/KovoPraha/evidenceTreninku/actions/runs/39776484081)
  a [CodeQL 39776483802](https://github.com/KovoPraha/evidenceTreninku/actions/runs/39776483802)
  rovněž zelené.

## Živá čtecí kontrola po aktivaci

- **Doprava vypnutá, cena 150 Kč, veřejný klíč prázdný, simulace vypnutá.**
  Admin nemá localhost simulační banner; klíč zůstává jen v lokálním KIS.
  Formulář nastavení se při ověření neodesílal.
- Produkt #296 „TEST - Hotovost a vratka 21.10.2026 - NEPLATIT“ zůstává
  **Stažený**. Checkbox **Lze zaslat Zásilkovnou** je přítomný a zaškrtnutý
  migrací fyzického zboží; PPL má samostatnou volbu „PPL nenabízet“.
  Produkt ani jeho zveřejnění nebyly při kontrole měněny.
- Přehled objednávek a stránka stavů/e-mailů se načetly bez chyby.
  V přehledu nejsou žádné Packeta objednávky; formuláře opravy/podání/storna
  proto nebyly na produkci prováděny. Jejich důkaz tvoří zdrojové změny
  a automatické lokální/CI testy, nikoli skutečné podání zásilky.
- Fronta před/po má **3 pending, 0 processing, 0 failed, 0 sent, pokusy 0**.
  Celá tabulka má shodný SHA-256 otisk uvedený výše. Žádná položka nebyla
  vrácena do fronty; worker, skutečný e-mail ani zásilka se nespouštěly.
- Původní pracovní pozice **Hospodář a platby** obnovena. Změny pracovního
  kontextu sloužily jen k přístupu k příslušným čtecím stránkám.

Lokální (neverzované) důkazy `var/packeta-release-20261022/`:
`deploy.log`, `production-before.json`, `production-after.json`,
`production-packeta-disabled.png`, `production-product-checkbox.png`,
`production-queue.png`. Screenshoty neobsahují klíče ani API hesla.

Výsledek je **nasazení připraveného ručního modulu při vypnuté dopravě**.
Aktivace, ostré ruční podání a ověření doručení zůstávají samostatným krokem
po rozhodnutí vlastníka. Fakturace nadále odložena. Navazující dokumentační
větev `codex/packeta-release-proof-20261022` nemění nasazený aplikační SHA
a nevyžaduje další nasazení.
