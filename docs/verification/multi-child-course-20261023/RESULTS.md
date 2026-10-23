# Stejný kurz pro více dětí — 23. 10. 2026

## Rozsah

Rodič přidá kurz pro první dítě, v košíku zvolí „Přidat kurz pro další dítě“
a vybere dalšího účastníka. Jedna objednávka a jeden variabilní symbol,
samostatné řádky, souhlasy, finanční podíly a přihlášky jednotlivých dětí.
Jména účastníků jsou vidět i v detailu objednávky.

Každou položku lze v košíku samostatně odebrat nebo změnit její dítě.
Stejné dítě nelze v témže období přidat dvakrát. Aktuální oprávnění se kontroluje
při změně i při odeslání objednávky. Nedostatečná kapacita vrátí celý zápis.

Storno již vytvořené objednávky a plná vratka nadále pracují s celou objednávkou.
Částečné storno zaplaceného kurzu není součástí této změny. Samostatné řádky
jsou předpokladem pro budoucí rozšíření, nikoli tvrzením, že již existuje.

## Implementace a migrace

- Migrace `20261023210000_family_course_cart`: unikátní klíče košíku a objednávky
  zahrnují účastníka. Zboží zachovává jednu řádku varianty s měnitelným množstvím.
- `shop_order_finance_shares` uchovává neměnné podíly společné objednávky.
  Zohledňuje kategorie kupónu, přesné haléřové zaokrouhlení a přiřazení zboží bez dítěte.
  Tabulka je zahrnuta do záloh. Starší objednávky si zachovávají dosavadní přiřazení.
- Zaplacení podílu jednoho dítěte nesmí potvrdit celou objednávku; aktivace nastane
  až po pokrytí celého předpisu. Jeden běžný bankovní převod za celou objednávku
  nadále používá dosavadní potvrzovací tok.
- Účetní podklad má samostatné `line_key` a příjemce; řádky se neslučují podle varianty.
- Nasazení vyžaduje zálohu a migraci před aktivací kódu. Po vytvoření rodinných
  objednávek nelze obnovit starý unikátní klíč ani provozovat původní zapisovací kód;
  případná oprava musí zachovat nové řádky a jejich finanční podíly.

## Izolovaný browser B1

Pouze `http://127.0.0.1:18086`, DB `127.0.0.1:33426/evidence_b1`, syntetická data
LOCALHOST/NEPLATIT, externí transporty vypnuté. Žádný skutečný převod ani e-mail.
Záloha před testem: `evidence_2026-10-23_185106_1e1cc1f1.sql.gz`, SHA256
`595a5a7ebf0e78f7bcaeb17f9105087b52f608275c9bb902931f5350be88ea39`.

Standardním UI založena osoba93 Bára TEST a schválena její vazba k testovacímu
účtu72, který již spravuje Annu91. Průvodcem založen produkt48/varianta55,
kapacita2, cena150Kč, období 1.11.2026–31.7.2027.

PASS: dvě položky, odmítnutí změny druhé položky na stejné dítě, odebrání pouze
Báry a její opětovné přidání, dva samostatné souhlasy, jedna objednávka33
`KP2610238684A82F7F` za300Kč. Po simulovaném potvrzení bankovní platby:
položky33/34, aktivní účasti12/13, dvě zařazení do soupisky, finanční podíly150+150Kč.
Read-only doklad: [local-readonly-proof.json](local-readonly-proof.json).

Obrázky: [košík](local-cart.png), [objednávka](local-order-pending.png),
[obě aktivní děti](local-active-children.png).
Hub B15 doplněn, ponechán PARTIAL; [stažený a zkontrolovaný export](acceptance-hub-export.md).
Technická přejímka není lidským převzetím celého systému ani schválením účetní.

## Automatické ověření

Regrese pokrývají samostatné položky, opakované přidání/odeslání, cizí účet,
odvolání vazby druhého dítěte, samostatné souhlasy, nedostatek kapacity a rollback,
odstranění jen jednoho řádku, přesné rozdělení kupónu, aktivaci obou soupisek,
úplné storno a idempotentní vratku, migraci existujícího košíku i uhrazené objednávky.

Lokální MariaDB smoke prošel s nativními prepared statements: souběžný checkout,
poslední místo, rodinná objednávka, dvě postupné úhrady podílů a následná aktivace.
Stará migrace standing orders vypisuje na lokální MariaDB varování `columns`;
smoke dokončil s exit0. Produkční kompatibilitu dále ověřuje CI MariaDB10.3/11.4.

Přesné finální CI a produkční SHA budou doplněny po dokončení chráněného nasazení.
