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
- [ ] PHP syntaxe, PHPUnit a MariaDB záloha/obnova, potřebné integrační smoke.
- [ ] Push, PR a zelené CI včetně MariaDB 10.3/11.4 a CodeQL; sloučení.
- [ ] Nasazení `deploy-production.yml` na přesném schváleném main SHA,
  `potvrzeni=NASADIT`, `uat_schvaleno=false`, `importovat_krouzky=false`.
- [ ] Ověřit release SHA, zálohu, migrace, zachování VS a HTTP smoke.
- [ ] Živě zkontrolovat administraci dopravy/ceny, checkbox produktu,
  objednávky a frontu bez zápisů do objednávek a bez odesílání.
- [ ] Zapsat produkční důkazy a konečný stav do plánu a obou handoffů.

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

Dosavadní kontroly: 17 změněných PHP souborů syntaxe PASS, Composer validace
a audit bez známých zranitelností, MariaDB záloha/obnova **198 tabulek PASS**,
souběžný checkout/potvrzení platby/poslední místo programu PASS, lokálně
**112 migrací aktuálních**. PHPUnit v tomto checkpointu ještě běží.
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
se opakuje CI; lokální ledger tohoto jediného nového kroku bude odstraněn
a opravena migrace znovu aplikována běžným runnerem (bez přepisování checksumu
a bez resetu lokálních obchodních dat).
