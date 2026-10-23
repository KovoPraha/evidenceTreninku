# Rozšířená technická přejímka rodinného nákupu – 23. 10. 2026

## Výsledek a rozsah

Uživatel nyní nemůže web osobně testovat. Technická přejímka proto pokračovala
autonomně nad izolovanými databázemi. Změny v tomto balíku rozšiřují regresní
testy; nemění běhový kód, databázové migrace ani produkční konfiguraci.
V ověřených scénářích se neprokázala nová chyba aplikace.

Výchozí zdrojový stav: `4d7aacf84d75639ccf0c202c2b05b4d824d44347`.
Poslední doložený produkční release z předchozí přejímky zůstává
`43a825e01235e66baa1601c3bc957365df011009`; v tomto kole nebyl nově ověřen
z prohlížeče a neproběhlo nasazení.

## Provedené kontroly

| Scénář | Ověřený výsledek |
| --- | --- |
| Dvě děti, stejný kurz, dva kusy zboží | Tři samostatné položky; dva účastníci, jedna objednávka a platba. |
| Zboží bez příjemce | Bez výslovného finančního profilu je objednávka odmítnuta, bez změny skladu. |
| Kupón jen na kurzy | Sleva se rozdělí mezi kurzy; cena zboží zůstane zachována. |
| Kupón jen na zboží / celý košík | Rozdělení podílů odpovídá rozsahu kupónu a celkové ceně. |
| Procentní sleva a zaokrouhlení | Přesné celočíselné částky, včetně nulové ceny druhého kurzu po rozdělení pevné slevy. |
| Dvě děti, poslední jedno místo | Odmítnutí vrátí celou transakci: objednávku, sklad, kupón; košík zůstane. |
| Postupné úhrady | Samotná úhrada jednoho dítěte ani částečná úhrada druhého neaktivuje kurzy předčasně. |
| Přeplatek | Zůstane pouze dítěti, ke kterému patří přijatá platba. |
| Účetní podklad | Zboží, oba účastníci a sleva mají odlišné klíče řádků; součet odpovídá objednávce. |
| Výdej zboží | Odečte fyzický sklad právě jednou; opakování stejného úkonu nezpůsobí další výdej ani změnu účastí. |
| Storno smíšeného nákupu před výdejem | Ukončí oba kurzy a obnoví původní sklad v testu staršího skladového modelu. |

### Konkrétní peněžní průchod na MariaDB

- Dva kurzy po 150 Kč a dvě trička po 125 Kč: před slevou 550 Kč.
- Kupón 10,01 Kč pouze na kurzy: celkem **539,99 Kč**.
- První dítě: **145 Kč**. Druhé dítě včetně zboží: **394,99 Kč**.
- První úhrada 145 Kč: objednávka čeká, obě účasti neaktivní.
- Druhá úhrada 100 Kč: zbývá 294,99 Kč, účasti stále neaktivní.
- Doplatek 300 Kč: obě účasti aktivní, přeplatek **5,01 Kč** pouze druhého dítěte.
- Fyzický sklad začíná čtyřmi kusy, z toho jeden rezervovaný starší objednávkou.
  Nová objednávka rezervuje dva další; po jejich výdeji zbývají dva fyzické kusy,
  jeden z nich nadále rezervovaný původní objednávkou.

## Důkazy a opakovatelnost

- B1 sonda: PASS, 327 čitelných vlastněných tabulek, externí transporty vypnuté,
  soukromé cesty mimo webroot. Tři nepřítomné volitelné historické tabulky.
- Cílené PHPUnit: **38 testů / 960 assertions PASS**.
- Rozšířený nativní MariaDB smoke: **PASS, exit 0**. Zahrnuje též původní
  souběžné checkouty, idempotenci platby a závod o poslední místo.
- PHP lint obou změněných testovacích souborů a `git diff --check`: PASS.
- Lokální MariaDB vypisuje již známé upozornění starší migrace
  `20260922190000_member_fee_standing_orders.php:13` na klíč `columns`;
  test dokončil úspěšně. Nasazená migrace se v tomto balíku nemění.
- Kompletní Linux testy a MariaDB 10.3/11.4 jsou povinnou kontrolou příslušného PR.
  Lokální Windows protokol je v `output/family-followup-full-windows.txt`;
  jeho známý POSIX scanner nelze zaměňovat za Linux výsledek.

Regresní scénáře jsou v `tests/Integration/ClubProgramPaymentLifecycleTest.php`
a `tests/Support/ShopCheckoutMariaDbSmoke.php`. MariaDB smoke vytváří pouze
vlastní dočasnou databázi s povinným testovacím prefixem a v `finally` ji odstraní.
V B1 aplikační databázi v tomto kole neproběhly doménové zápisy. Produkce se neměnila.

## Odložené kontroly

Prohlížeč se při převzetí otevřené testovací karty odpojil. Mobilní rozložení
nebylo ověřeno, nevznikl nový screenshot ani nový browser průchod.
Z téhož důvodu nebylo možné přes standardní UI připojit výsledek do
`testovaci_scenare.php` ani stáhnout nový Markdown export. Tento protokol je
podkladem k následnému doplnění B15; stav zůstává PARTIAL.

Uživatelské přijetí, převzetí účetní a ověření samostatného sportovního přihlášení
zůstávají otevřené. Částečné storno pouze jednoho dítěte z uhrazené společné
objednávky nebylo přidáno. Souběh dvou příjmů pro různé děti téže objednávky
není tímto sekvenčním testem dílčích úhrad doložen.
