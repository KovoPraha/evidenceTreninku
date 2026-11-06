# Měsíční členství přes e-shop

Základ implementace: `97b85bb5`, 5. 11. 2026. Implementace a syntetické ověření jsou oddělené od produkčního nasazení a aktivace plánovače.

Aktuální denní časování a přejímku určuje [hostingový plán z 6. 11.](MEMBERSHIP_SCHEDULER_2026-11-06.md),
provozní zastavení a čtecí status jsou v [runbooku](MONTHLY_MEMBERSHIP_RUNBOOK.md#hostingový-denní-plán).

## Kontrakt

- Jednorázový košík, platby za zboží a období kroužků zachovají stávající životní cyklus.
- Správce připojí k variantě produktu explicitní měsíční tarif, cílovou soupisku, cenu, splatnost, podmínky a schválenou účetní metodiku. Existující objednávky ani dohody se nepřepočítávají.
- Přihlášený ověřený zákazník vybere oprávněného sportovce a potvrdí závazek. Registrace účtu a schválení propojení osob používají stávající mechanismy.
- Měsíční členství má vlastní potvrzovací cestu z katalogu, nikoli jednorázovou položku smíšeného košíku. První měsíc vznikne právě jednou při sjednání, další doplní opakovatelný worker. Cena je součástí neměnné dohody.
- Vlastník potvrdil: plná cena prvního kalendářního měsíce, ukončení posledním dnem měsíce. Podmínky se zobrazí před souhlasem.
- Jeden stálý VS plátce; bankovní příjmy se alokují od nejstaršího měsíčního dluhu, zbytek se zachová pro další měsíce. Historické/nejasné smíšené závazky zůstávají k ruční kontrole.
- Částečné platby nesmějí vytvořit nadměrnou úhradu. Identita pohybu je jedinečná napříč automatickými i ručními cestami.
- Měsíční předpis, jeho účetní doklad a přijetí peněz jsou odlišné skutečnosti. Automatické PDF používá schváleného vystavovatele, metodiku a číselné řady; nesmí podvrhovat přihlášení pracovníka.
- Upomínky se generují po splatnosti v intervalech 3/7/14 dní ze skutečného zůstatku. Před odesláním se znovu ověří úhrada, plátce a oprávnění. Platba zruší nepotřebné zprávy.
- Zákazník může bezpečně stáhnout pouze doklady svých předpisů a vidí budoucí závazek, VS, nedoplatek, přeplatek a ukončení.

## Postup

1. Aditivní migrace dohod, tarifů, měsíčních období, bankovních příjmů, alokací a zpráv; aktualizace zálohovacího kontraktu.
2. Doménové služby a transakční zámky: konfigurace tarifu, souhlas, období, výpověď, bankovní alokace a přeplatky.
3. Napojení katalogu a zákaznických/administrátorských stránek; blokace nechtěného jednorázového nákupu měsíční varianty na serveru.
4. Napojení Fio, účetních dokladů, zákaznického stažení a automatického workeru; provozní přepínače jsou při nasazení výchozí vypnuté.
5. Oprava částek připomínek a QR po částečné úhradě.
6. Integrační a regresní testy: první a další měsíce, opakování, více osob, částečná/roční/předčasná platba, chybný VS/měna/účet, storno, chybějící účetní nastavení, upomínka před/po úhradě, izolace rodin a jednorázové nákupy. Ověřit SQLite i MariaDB, lint a příslušné projektové kontroly.

## Produkční brána

Před aktivací připravit konkrétní tarif a schválené dokladové parametry, otestovat dry-run a doručování, ověřit zálohu a naplánovaný heartbeat. Produkční nasazení, vydávání skutečných dokladů, párování skutečných plateb a rozesílky vyžadují samostatné pověření. Tento vývojový požadavek je sám nezapíná.
