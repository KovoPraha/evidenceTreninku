# Produkční vydání funkčních oprav — 2. 11. 2026

Vlastník výslovně povolil review, sloučení PR #272, produkční nasazení,
ověření a dokumentaci. Změna hesla a T07 jsou **SKIPPED** dle jeho pokynu.
Časy workflow jsou UTC; aktivace 00:11 UTC odpovídá 01:11 Europe/Prague.

## Review, merge a CI

[PR #272](https://github.com/KovoPraha/evidenceTreninku/pull/272), head
`0cc2081921d1973b3981d805a43701d7baadd939`, prošel všech 11 kontrol.
[CI přesného headu](https://github.com/KovoPraha/evidenceTreninku/actions/runs/41364178432):
Linux **1902 testů / 38239 assertions**, JavaScript **19/19**, MariaDB
10.3/11.4 smoke a objemové profily, PHPStan, styl, dokumentace a CodeQL PASS.

Review bez blokujícího nálezu. Skutečný runtime rozdíl proti předchozí produkci
je pouze `assets/app-ui.css`, `formular.php`, `edit_trenink.php`:

- F01: veřejná navigace má vrstvu 1020 nad lištou kategorií 1010; účtové
  odkazy již nejsou zakryté.
- F02: dynamická pole všech šesti typů měření mají explicitní přístupné názvy
  při zadání i úpravě včetně přidaného řádku.
- F03: předvyplněná poznámka Kolo je při úpravě viditelná a upravitelná.

Ostatní PHP změny jsou mechanické formátování a opravy testů. Bez změny
autorizace, databázové migrace či účetního runtime. Regrese dynamických měření
byla ověřena i očekávaným selháním na předchozím zdroji; aktuální cílená sada
9/9 PASS. Původní [syntetické browser ověření](../functional-uat-20261102/RESULTS.md)
dokládá 36 konkrétních PASS a tři opravené nálezy, nikoli úplné produkční UAT.

Squash merge `c1686b4895c2d45428a4ca492b3c56fc4d0fe286` má přesně stejný
strom jako ověřený head. Ochrana main zachována. Také
[CI sloučeného main](https://github.com/KovoPraha/evidenceTreninku/actions/runs/41365436620)
SUCCESS. [Metadata PR](pr272.json).

## Nasazení a stav produkce

[Chráněný deploy 41365449270](https://github.com/KovoPraha/evidenceTreninku/actions/runs/41365449270)
**SUCCESS**, source `c1686b4895c2d45428a4ca492b3c56fc4d0fe286`.
`potvrzeni=NASADIT`, `uat_schvaleno=false`, `importovat_krouzky=false`.
Deploy zopakoval 1902/38239; kontrola zálohy, preflight, migrací, zachování
soukromého úložiště, rsync aktivace, HTTP a úklid dočasného configu uspěly.
HTTP smoke použil curl a vrátil 200, bez náhradní cesty.

- Ověřená nová záloha: **337 tabulek / 2 triggery**,
  SHA-256 `20c06aba5e72f1e1587c63f445eaa3ec50e42f2ebd0cdd6d7432afff137a3a42`.
  Nová generace v tomto běhu nebyla izolovaně obnovována.
- Schéma current, pending `[]`, ledger 143; katalog 142. Dosavadní variabilní
  symboly beze změny. Bez nové aplikační migrace.
- Konfigurace, integrační flags, kandidáti soukromých příloh, zákaz kanonických
  KIS zápisů a počet ledgeru jsou před/po shodné. Fio import/auto-confirm,
  SumUp, Stripe a Velocota false; Packeta nedefinovaná. UAT/import false.
  Dvě dosud chybějící soukromé přílohy jsou předchozí otevřený stav.

[Předchozí audit 41365136475](https://github.com/KovoPraha/evidenceTreninku/actions/runs/41365136475)
a [následný audit 41365953921](https://github.com/KovoPraha/evidenceTreninku/actions/runs/41365953921)
SUCCESS. Audity používají čtecí konzistentní databázový snapshot.
Jejich `deployment_ready=false` ponechává lidskou/účetní přejímku otevřenou;
jejich starý manifest F1–F3 není inventurou tohoto vydání.
[Porovnání](release-comparison.json), [před](pre-audit.json), [po](post-audit.json),
[souhrn deploye](deployment.json), [kroky workflow](deploy-run.json).

## Ověření po aktivaci

Anonymní HTTP před i po **26/26 PASS**: veřejné stránky, známé produkty,
přesměrování chráněných cest a zákaz přístupu k soukromým souborům.
[Před](pre-http.json), [po](post-http.json), [opakovatelný helper](http-smoke.py).
Žádné přihlášení, objednávka, rezervace, trénink ani jiný uživatelský zápis.

Skutečný Chrome načetl produkční CSS: před vrstva veřejné navigace `auto`,
po **1020**, lišta kategorií stále **1010**. Získaný veřejný CSS soubor má
přesný SHA-256 očekávaného Git blobu
`34300685642cb3269e39e46c76a2bfcf9df6c0ffa52b220537fcae66c9805bd4`.
[Runtime CSS](css-runtime.json), [helper](css-runtime.py),
[očekávané soubory](release-manifest.json), [browser před](pre-browser.json).
Log aktivace potvrzuje rsync všech tří změněných runtime souborů; živá metadata
potvrzují SHA/run. PHP hashe v manifestu jsou očekávané z Gitu, nikoli přímo
změřené na serveru. Nejde o úplnou inventuru hashů runtime.

| Kontrola v produkčním prohlížeči | Výsledek |
|---|---|
| Knihy, vzestupné ceny | PASS: Zábavně k výkonu 250 Kč před Když trénink dává smysl 450 Kč |
| Hledání Zábavně v kategorii Knihy | PASS: jeden odpovídající výsledek |
| Anonymní `formular.php` | PASS: přesměrování na `login.php` |
| Anonymní `edit_trenink.php?id=1604` | PASS: přesměrování na `login.php` |

[Browser doklad](post-browser.json), [produkční snímek](post-shop-sorted.png).
Při jedné prodlevě DOM nástroje byl stav ověřen dostupným AX a screenshotem;
samotná prodleva nebyla označena za chybu aplikace. Testovací karta zavřena,
původní uživatelské karty ponechány. Viewport v tomto běhu neměněn.

## Hranice ověření

Produkční relace byla odhlášená. Přihlášený produkční průchod účtovým menu
a měřicími formuláři **nebyl zopakován**; podkladem jejich funkčního chování
zůstávají konkrétní místní syntetické browser testy a automatické regrese.
Produkční důkazy potvrzují dodání zdrojů, skutečně načtené CSS, veřejné
ovládání a ochranu soukromých cest. Nepředstírají úplnou přejímku aplikace.

Heslo a T07 vynechány. Žádný nový reset, zpráva, rozšíření oprávnění, skutečná
platba, import, rozesílka ani nové uživatelské produkční záznamy. Standardní
ověřovací a zálohovací kroky autorizovaného deploye proběhly. Původní WIP
v htdocs zůstal nedotčen. Dokumentační dokončení následuje samostatným PR;
dokumentace nevyžaduje další produkční nasazení.
