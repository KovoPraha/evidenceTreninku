# Opravy podle produkčního UAT z 21. 9. 2026

Stav dokumentu: implementováno; produkční release probíhá výhradně chráněným workflow z větve `main`.

## Co se mění pro uživatele

- Platba kartou přes Stripe má krátký síťový timeout, bezpečnou chybovou zprávu s dohledatelným kódem a při opakování znovu použije otevřenou Checkout Session. Expirovanou relaci umí bezpečně nahradit.
- Stránka `booking/krouzky.php` se jmenuje **Akce** a zobrazuje jen jednorázové bezplatné a placené akce. Pravidelné kroužky se nabízejí pouze v e-shopu.
- „Bezplatné kroužky“ se mění na **Bezplatné akce a nábory** a stránka vysvětluje, co sem patří.
- E-shop používá při zobrazení programu stejnou kontrolu prodejnosti jako checkout. Kroužek bez platných storno podmínek a souhlasu se veřejně nenabídne.
- Průvodce prvním kroužkem už nekončí slepou hláškou. Pokud ještě neexistují schválené klubové podmínky, správce je vyplní a potvrdí přímo v průvodci.
- Pokročilá stránka programů vysvětluje pojmy stabilní program, nabízené období, soupiska a účast z objednávky.
- Stránka dlouhodobých názvů kroužků vysvětluje, že hodnoty jako „500+1“ a „K“ nejsou předvolby, ale dříve uložená data. Zobrazuje počet nabízených období.
- Při založení klubové akce je interní kód nepovinný a systém ho vytvoří automaticky. Chybové zprávy oddělují chybný typ, oprávnění a formát kódu.
- Cena veřejné hodiny velodromu se zadává v Kč; na haléře se převádí až na serveru.
- Veřejné hodiny velodromu a individuální lekce mají nový datový kontext. Po migraci se nezobrazují ve stejné nabídce a nelze je upravovat přes nesprávnou administraci.
- „Klubový kalendář“ se jmenuje **Kalendář klubových akcí** a výslovně obsahuje závody, soustředění, školení a schůze, nikoli individuální lekce a velodrom.

## Databázová změna

Migrace `20260921120000_individual_lesson_context.php` přidává do `individualni_lekce` sloupec `booking_context`:

- `individual_lesson` – individuální lekce,
- `public_velodrome` – veřejná hodina velodromu.

Existující veřejné termíny se označí podle vazby na velodrom, názvu vytvořeného původním formulářem, výhradního režimu nebo existující položky košíku/objednávky velodromu. Migrace je idempotentní a deploy ji musí aplikovat před aktivací PHP release.

## Co kód záměrně nedělá

- Nezapíná Stripe a neukládá žádné API klíče.
- Nemění produkční data mimo verzovanou migraci.
- Nespouští deploy.

Ostré Stripe klíče, webhook endpoint a skutečná testovací platba jsou samostatný provozní krok po schválení této změny. QR / bankovní převod zůstává nezávislou platební cestou.
