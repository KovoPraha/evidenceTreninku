# Kalibrace společného Windows měření, 7. 11. 2026

- Nové checkouty a datadir e999e34b; oba Composer instalované dle shodného locku.
  Žádný cizí runtime/config se nepoužil a původní pracovní kopie nebyla přepnutá.
- První kontrola odmítla ještě nehotový seed před kopírováním configu/měřením.
  Seed vznikl jednou. Rozšířený seznam doménových tabulek původně obsahoval
  legacy sportovec_podskupina, kterou minimální fixture nemá; preflight odmítl
  neúplný snapshot před časováním. Použit skutečný kanonický coach_scope_grants.
- První neúplný browser běh role sports_lead skončil na networkidle20s timeoutu.
  Neukládal kompletní report, celá role se zopakovala. Finální úplné role mají
  všech 117 pozorování na variantu, jednotlivé pomalejší vzorky se nevyřazovaly.
- Viewport explicitně1036×703, DPR přibližně1 na všech rolích/variantách.
  Browser CLI0.1.23 / Chrome157; neznámé accessibility launch flags se neodvozují.
  Networkidle500ms není load metrika, nativní CDP kalibrace se zde znovu neměřila.
- Všechny 404 ve finálních samples jsou obecné browser resource404; serverové
  logy se kontrolují zvlášť. Nejde o nový důkaz produkční chyby. Neúplný timeout
  není zařazený mezi 234 časových pozorování ani vydávaný za aplikační regresi.
- Instrumentace je samostatný běh, stejné celé HTML a nulové aplikační zápisy;
  general log se používá pouze v novém vlastním MySQL a vždy se vypne.
- Záměrné zápisové UAT až po všech měřeních a 20-tabulkové read-only paritě.
  Post-UAT má revizi53 a omluvy; jeho stav se nevydává za původní read-only checksumy.
- Celostránkové JS/noJS screenshoty jsou UI důkaz. Elementové výřezy picker-form
  překrývá sticky header a mají jen kalibrační význam.
- Nezávislá relace #372 měla svůj neúspěšný Windows datadir b96f611b a následný
  úspěšný Linux pár. Tento e999e34b byl zdravý; žádné cizí systémové tabulky se
  neopravovaly. Stejné aplikace mají jiné stroje a jiné kontrolní verze.
- Zdrojový kód aplikace zůstal faae75e8. Novější #370/#372 jsou dokumentace,
  testovací runner/workflow a cizí provozní předání; naše app zdroje se nemění.
- Soukromé callbacky/logy s testhesly byly odstraněné. Archivuje se whitelist,
  nikdy config, cookies, credentials, rawHTML nebo přihlašovací logy.

[Výsledky](RESULTS.md), [vyčištěné prostředí](cleanup.json).

Serverové logy původního i společného prostého běhu: všechny404 pouze /favicon.ico; žádná jiná404cesta. Logy zůstávají místní, nearchivují se.
