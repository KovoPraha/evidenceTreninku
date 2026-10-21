# Produkční předání: stavy objednávek, Zásilkovna a systémové e-maily

Datum: 21. 10. 2026, Europe/Prague. Vlastník výslovně schválil commit, push
a nasazení. Skutečné e-maily při ověřování ani fakturaci neschválil.

## Vydání a automatické kontroly

- [PR #156](https://github.com/KovoPraha/evidenceTreninku/pull/156), aplikační SHA
  `574328421fff748b48f7d744a3ce454b9b50dd1e`.
- [CI 39603132938](https://github.com/KovoPraha/evidenceTreninku/actions/runs/39603132938):
  PHPUnit a MariaDB 10.3/11.4 úspěšné, včetně nového smoke souběhu editorů.
  [CodeQL 39603127501](https://github.com/KovoPraha/evidenceTreninku/actions/runs/39603127501)
  také úspěšný. Lokální úplná sada 1 178 testů / 26 530 kontrol.
- Lokální záloha/obnova 190 tabulek a souběh editorů prošly i po začlenění
  vlastních registračních souhlasů z PR #155. Kombinovaný kontrakt `.4` zachovává
  oba datové modely; obsah existujících migrací se neměnil.
- [Deploy 39603396933](https://github.com/KovoPraha/evidenceTreninku/actions/runs/39603396933)
  je úspěšný na shodném SHA. `uat_schvaleno=false`, `importovat_krouzky=false`.
- Předlet: `ok=true`, PHP 8.2.33, `warnings=[]`.
- Před aktivací ověřena záloha `evidence_2026-10-21_125550_473628ab.sql.gz`
  s manifestem a SHA-256, **236 tabulek a 2 triggery**, mimo webroot.
- Migrační apply i následný check: `current=true`, katalog 110, `pending=[]`.
  Aktivace 14:56:27, konečný přímý HTTP smoke 200 v 14:56:39. Kopie produkčního
  configu z release byla odstraněna standardním workflow.

## Živá prohlídka po aktivaci

Přihlášený Chrome, pracovní pozice Zákaznická péče a objednávky. Pouze čtení
a tlačítko syntetického náhledu; žádné ukládání produkčních šablon, přepnutí
stavů, příjem platby, vytvoření objednávky, podání zásilky ani ruční opakování
fronty. Přerušená kontrolní karta byla nahrazena novou kartou stejného prohlížeče.

| Povrch | Důkaz |
|---|---|
| [Stavy a e-maily](https://kis.kovopraha.cz/eshop_order_statuses_admin.php) | Nesmazatelné systémové stavy, jejich spouštěče a skutečné účinky na poštu; platba a vlastní upřesnění samostatně. Vlastní existující stav zachován. |
| Seznam objednávek | Vytvořená objednávka, Vydáno a Stornováno odpovídají platebním sloupcům; dokončený výdej nemá znovu výdejovou akci, vratka je samostatný finanční úkol. Žádná akce provedena. |
| [Editor odběrového e-mailu](https://kis.kovopraha.cz/eshop_order_statuses_admin.php?email=shop_order_ready#email-shop_order_ready) | Šest zpráv ve výchozím znění, žádná lokální editace přenesena. Předmět, text, proměnné a historie dostupné. Náhled ukázal `LOCALHOST-NEPLATIT` a výslovné „nic nebylo odesláno“. Důvod a potvrzení změny zůstaly prázdné; nová verze se neukládala. |
| [Zásilkovna](https://kis.kovopraha.cz/eshop_packeta_admin.php) | Běžná produkční obrazovka, žádný simulátor. Cena 0,00, veřejný klíč prázdný, nabídka vypnutá. Konfigurace nezměněna. |
| [Fronta](https://kis.kovopraha.cz/eshop_notifications_admin.php) | 3 pending, 0 processing/failed/sent, všechny pokusy 0. Čitelná označení typů zpráv a náhled původního obsahu. Žádný worker ani opakování nespouštěny. |

Browser konzole kontrolované stránky neměla chyby ani varování.

## Neměnnost existující fronty

Před vydáním byla ve frontě zpráva #1. Její předmět + LF + tělo mají před
i po migraci/náhledu stejný SHA-256:

`a6cb0ad2a10944e8ec55cd091ad93322a7586b66385e93bb8c8ca6421a82562b`.

Migrace jí zpětně nepřiřadila nepravdivou verzi nové šablony. Během čekání
na vydání přibyly zprávy nákupu bez účtu a přijaté platby z nezávislého testu
hotovosti. Časy 14:51:55 a 14:53:58 předcházejí aktivaci v 14:56:27.
Závěrečné 3 pending tedy nejsou důsledkem migrace ani náhledu. Kódy objednávek,
příjemci a soukromé přístupové odkazy se do tohoto reportu nekopírují.
Poštovní transport se v tomto průchodu nespustil a počet sent zůstal 0.

## Simulace, další provoz a omezení

- `SHOP_PACKETA_LOCAL_DEMO=false` v ignorovaném lokálním configu. Produkce má
  neaktivní striktní loopback guard. Lokální config, testovací objednávky,
  fronta pěti lokálních zpráv a obrázky nejsou součástí Gitu ani release.
- Ostré nabízení Zásilkovny vyžaduje cenu, veřejný 16znakový klíč widgetu
  a explicitní povolení v administraci. Samotný deploy je nezapnul.
- Skutečné štítky se stále zakládají ručně v Klientu Zásilkovny; KIS eviduje
  tracking a dočasný podací kód, který po podání odstraní. Nevznikla integrace
  vystavování štítků přes API ani fakturace.
- Úplné nákupy a výdeje jsou doloženy lokálním syntetickým B09 PASS a testy.
  Tato produkční kontrola nedokazuje skutečné doručení e-mailu nebo balíku
  a není blanketní schválení všech UAT scénářů.
- Snímky zůstávají místně v `var/order-status-release-20261021/`:
  `production-statuses.jpg`, `production-email-preview.jpg`,
  `production-packeta-disabled.jpg`, `production-queue.jpg`. Poslední obsahuje
  metadata fronty; nedávat bez revize do veřejného Gitu. Deploy log je místní
  `var/local-status-mail-review/deploy-39603396933.log`.

Další krok: případná samostatně schválená konfigurace ostré Zásilkovny a později
rozhodnutí o fakturaci podle [trvalého plánu](ORDER_STATUS_EMAIL_PLAN_2026-10-21.md).
Dokumentační doplnění tohoto předání nevyžaduje opětovné nasazení aplikace.
