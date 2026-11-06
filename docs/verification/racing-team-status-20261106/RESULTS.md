# Závodní tým – aktuální schválení a nabídka

Ověřeno 6. 11. 2026 přibližně 12:00–12:03 Europe/Prague ve skutečném přihlášeném Chrome na produkci. Zadání: ověřit současný stav a doplnit dokumentaci. Provedena čtecí kontrola, nikoli nové schválení či zveřejnění.

## Výsledek

**Závodní tým je schválený, aktivní a zveřejněný v e-shopu za 2 200 Kč měsíčně.** Z detailu vede funkční odkaz na sjednání členství. Dokončení sjednání vyžaduje zákaznický účet s klubem ověřenou vazbou na sportovce. U použitého účtu tato vazba chybí, proto se správně ukazuje výzva k propojení; skutečný nový závazek ani platba při ověření nevznikly.

| Kontrola | Pozorovaný výsledek |
| --- | --- |
| `monthly_memberships_admin.php`, sekce Schválené tarify | `KP-CLEN-ZAVODNI-MESIC · active`, 2 200,00 Kč / měsíc; dostupná akce „Zastavit nový prodej“ |
| Fakturační údaje | Uložený vystavovatel Tělovýchovná jednota KOVO, z.s., IČ 00541753; sídlo Nad kapličkou 3324/15, Strašnice, 100 00 Praha 10; bankovní pole vyplněné. Údaje nebyly znovu ukládány. |
| Nastavení tarifu v administraci | Měsíčně, splatnost 15. den, podmínky plné první ceny a ukončení ke konci měsíce; vystavovatel TJ KOVO, druh „Doklad k členskému příspěvku“, schválené členění „Členství v klubu · contribution_confirmation“; nabízeny MTB, Silnice a Dráha |
| Kategorie „Členství v klubu“ | Jeden zveřejněný výsledek: Závodní tým, 2 200,00 Kč / měsíc; výběr více disciplín bez příplatku uveden v popisu |
| [Detail produktu 297](https://kis.kovopraha.cz/booking/produkt.php?id=297) | Měsíční varianta 951, SKU `KP-CLEN-ZAVODNI-MESIC`, cena a tlačítko „Sjednat členství“ |
| [Sjednání varianty 951](https://kis.kovopraha.cz/booking/mesicni_clenstvi.php?variant_id=951) | Zobrazen název, cena, splatnost, podmínky a trvalý příkaz; žádná hláška o pozastaveném prodeji. U účtu bez propojené osoby výzva „Nejprve propojte sportovce v Mých osobách. Propojení ověřuje klub.“ |

Zobrazení zákaznické nabídky zároveň odpovídá serverovým kontrolám v [mesicni_clenstvi.php](../../../booking/mesicni_clenstvi.php): zapnutý prodej, aktivní tarif, produkt, varianta i publikace. Nejde pouze o viditelnost tlačítka v katalogu. Samotný formulář bez oprávněné osoby se podle stejného kódu nevykreslí. Kontrola kódu je podpůrná; skutečné dokončení sjednání tím není simulováno.

## Důkazy a meze

- [Snímek detailu produktu](product.png), [snímek zákaznické nabídky a podmínky propojení](customer-offer.png). Bez údajů sportovců a bez bankovních údajů či tajemství.
- Předchozí [protokol založení konceptu](../membership-catalog-release-20261105/RESULTS.md) zůstává historicky platný k 5. 11.; jeho tehdejší nedokončené schválení již nevystihuje současný stav.
- Použit existující přihlášený zákaznický účet. Samostatná anonymní relace ani účet s již ověřeným sportovcem nebyly součástí tohoto průchodu. Skutečné sjednání, první předpis/PDF, úhrada a doručení k tomuto členství zůstávají samostatnou přejímkou.
- Během kontroly žádná publikace, tarif, fakturační údaj, soupiska ani zákaznická vazba nezměněny. Již existující jednorázový košík ponechán nedotčený. Pracovní kontext po kontrole vrácen na původního Administrátora baru.
- Drobné názvosloví k pozdější úpravě: katalog používá štítek „kroužek“, „Detail a zájem“ a detail blok „Nevyhovuje vám termín?“ i pro členství. Funkční odkaz „Sjednat členství“ je dostupný; tento audit texty neupravoval.

Navazuje [průběžný plán](../../WORK_PLAN_CURRENT.md), [obsluha členství](../../MONTHLY_MEMBERSHIP_RUNBOOK.md) a [aktuální provozní stav](../../CURRENT_STATE.md). Produkt znovu nezakládat ani neschvalovat kvůli starému zápisu o konceptu.
