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

- [ ] Commit vyjmenovaných souborů a začlenění aktuálního `origin/main`;
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
