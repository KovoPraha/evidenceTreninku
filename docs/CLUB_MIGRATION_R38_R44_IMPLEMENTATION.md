# R38–R44 — migrace a technická příprava přechodu

Místní balík navazuje na `67121f0b` (R31–R37). Větev
`codex/migration-cutover-r38-r44-20261023`, pracovní kopie
`C:\xampp\htdocs\evidencePavel-finance-b1-20261022`.
Všechny zápisy a zkoušky probíhají výhradně nad syntetickými daty.

## R38 a R41: opakovatelný převod

`bin/club-migration.php` poskytuje postup stage → review → apply → report.
Režim vyžaduje vlastní izolovanou MariaDB na portu 33426, správný datadir,
syntetický příznak a aktuální pracovní oprávnění. Nemá produkční přepínač.
Souborový vstup se přijímá jen ze soukromé složky importů; úplný zdrojový
obsah a původní bajty se uchovávají s otisky a časovým rozsahem snímku.

Každá položka má stabilní zdrojové ID, samostatnou revizi a rozhodnutí.
Schválení váže konkrétní zdroj, cílový stav, návrh převodu a verzi kódu.
Změněný podklad, novější cíl nebo změna převodníku vyžadují nové posouzení.
Jednotlivé aplikace jsou transakční a opakovatelné; přerušená dávka zachová
protokol již provedených položek. Souběh nesmí vytvořit druhý sportovní
záznam ani změnit dříve schválenou identitu na jiný cíl.

Osoby se vážou na ověřenou mapu identity. Rodinná vazba, účet, soupiska
a program vyžadují kontrolu konkrétních stran a rozsahu; samotné cílové ID
nebo poznámka nejsou důkazem shody. Podporované sportovní převodníky zachovají
skutečný čas a obsah tréninku, akce, výsledku nebo měření. Kandidát duplicitní
aktivity vyžaduje výslovné propojení s ověřením obsahu. Historický převod
neodesílá zprávy, nevyplácí peníze a sám neuděluje přístupy.

Neznámý typ, neověřená jednotka nebo nejasná identita zůstává v karanténě.
Výmaz ve zdroji nemaže cíl. Archivovaná položka sama neznamená dokončený
převod; otevřený finanční případ nelze prohlásit za vypořádaný archivací.
Poškozený zdrojový archiv nebo změněný kanonický cíl se projeví v reportu
jako problém integrity.

## R39: finanční počátky a otevřené případy

Adaptér umožňuje výslovně schválený počáteční zůstatek nového účtu a
ověřený počáteční sklad včetně rezervací. Počátek účtu není novým příjmem.
Číslo dokladu, VS, měna, protistrana a otevřený stav se zachovávají.
Vazby rezervací se nesmějí vytvořit podle pouhé shody počtu kusů.

Objednávky, doklady, příspěvky, výdaje, zálohy a odměny se v tomto adaptéru
porovnávají s již existujícími kanonickými případy. Chybějící otevřený starý
případ se nevytváří odhadem: je v karanténě nebo v explicitním seznamu
dokončení ve starém systému s vlastníkem a blokací přepnutí. Pro převzetí
konkrétního dosud nepodporovaného exportu zbývá připravit a ověřit adaptér
podle skutečného zdroje. Technická implementace není dokončenou migrací
Shoptetu ani potvrzením skutečné inventury. Podrobnosti:
[R39](verification/r38-r44/R39_FINANCE.md).

## R40 a R42–R44: příprava přechodu

Systémový správce má administraci **Příprava přechodu**. Uchovává verzovaný
registr zdrojů, historie, agend a jediného plánovaného zapisujícího systému,
veřejných URL, soukromých příloh, integrací, vlastníků a přejímacích důkazů.
Soukromá příloha musí odpovídat konkrétnímu kanonickému záznamu a skutečnému
souboru. Přístupové parametry a credentials do veřejné mapy URL nepatří.

Kontrolní snímek zachytí schéma a řádky katalogu vlastněných tabulek včetně
čekajících plateb a front, stav registru a otisk PHP kódu. Porovnání ukáže
nové, změněné i odstraněné řádky. Chybějící tabulka je neověřená, nikoli
prázdná. Nová platba po kontrolním bodě vyžaduje zachování nové operace;
nástroj neobnovuje starou DB přes novější data. Skutečná obnova DB a
soukromých souborů se ověřuje samostatným místním nácvikem.

Brány vždy uvádějí `production_authorized=false` a nepotvrzenou lidskou
přejímku. R43 a R44 zůstávají provozně neprovedené: nebyl živý pilot,
přepnutí, skutečný měsíc přijatý účetní ani odstavení starých služeb.
Podrobnosti: [R40/R42–R44](verification/r38-r44/R40_R44.md).

## Zpřesnění výdajů, odměn a exportu po křížové kontrole

Vrácená bankovní výplata je skutečný příjem s vazbou na konkrétní původní
příděl. Obnovuje neuhrazenou část schváleného závazku; nevyžaduje umělé
snížení nároku. Částečný návrat, další výplata a opakované či souběžné
potvrzení nesmějí překročit původně vyplacené peníze. Opravné snížení nároku
a vrácení neúspěšné platby zůstávají odlišné důvody.

Účetní export uvádí vazbu původní a vrácené výplaty bez dalšího peněžního
obratu. Pohledávky objednávek nyní používají stejný kanonický typ
`shop_order` jako jejich platební příděly. Starší již vytvořené exportní
archivy se nepřepisují.

Pozdě zaevidovaný podklad výdaje, zálohy nebo odměny se v dřívějším období
neztratí, pokud na něj odkazuje skutečný pohyb z tohoto období. Export jej
označí jako pozdě zadaný a upozorní na neověřené historické schválení;
nedatuje schválení zpětně a netvrdí, že dnešní stav platil v minulosti.

## Schéma a ověření

Nové migrace: `20261023080000_club_migration`,
`20261023090000_club_cutover`, `20261023100000_club_payment_returns`.
Celkem 136 migrací v místní instalaci. Zálohovací kontrakt `2026-10-23.3`
zahrnuje deset nových vlastněných tabulek tohoto balíku.

[Výsledky přejímky](verification/r38-r44/RESULTS.md) obsahují přesné počty,
nezávislou revizi, skutečné HTTP/CLI a MariaDB souběhy, otisky záloh
a výsledek obnovy. [Společné předání](CLUB_IMPLEMENTATION_HANDOFF.md)
shrnuje R01–R44 a [registr D01–D16](CLUB_COMPLETION_DECISIONS.md) odložené
odpovědi, které se nepovažují za automaticky odsouhlasené.
