# Vydání PR #300 — 5. 11. 2026

Vlastník výslovně schválil kontrolu PR #300, při úspěchu merge, produkční nasazení,
ověření a aktualizaci dokumentace. Heslo, obnovovací odkaz a T07 vynechat.

1. Zkontrolovat kód a sjednotit PR s čerstvým main; nově přítomné TV programy #301
   jsou už nasazené (deploy 41977082925), jejich stav zachovat.
2. Ověřit všechna CI na přesném headu, čtecí produkční audit a anonymní GET před vydáním.
3. Sloučit schválený head, ověřit shodný Git strom a nasadit přes chráněné workflow:
   NASADIT, uat_schvaleno=false, importovat_krouzky=false.
4. Ověřit přesné živé SHA, aktivaci opraveného souboru, konfiguraci, přepínače,
   schéma, variabilní symboly, veřejný HTTP průchod a obnovu čerstvé zálohy v izolaci.
5. Dostupným browserem ověřit přihlášené čtecí scénáře, pokud existuje relace.
   Její absenci zaznamenat bez nahrazování produkčního UAT místním výsledkem.
6. Dokončit protokol a aktuální dokumentaci, prověřit a sloučit dokumentační PR.

Žádná rozesílka, aktivace integrace, import, změna oprávnění ani úprava skutečných
rodinných vazeb. Původní WIP v hlavním checkoutu zůstává nedotčené.
Stav: PR sloučený, chráněné vydání a technické ověření dokončené. [Výsledky](RESULTS.md)
obsahují 11 zelených kontrol aktualizovaného PR, CI main, deploy 1997/38644,
HTTP před/po 42/42, shodné provozní přepínače a izolovanou obnovu přesné nové zálohy.
Přihlášené produkční UAT bez relace NOT RUN. Dokumentace se předává samostatným PR;
jeho kontrola/merge nejsou nové nasazení ani schválení úplného UAT.
