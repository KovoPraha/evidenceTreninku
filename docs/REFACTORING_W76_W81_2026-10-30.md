# W7.6 / W8.1 — osobní čtení a časová osa soupisek

Plán před implementací, 30. 10. 2026; základ `c6a260acef86218bb3e429c912d12746f712b75f`.
Navazuje na autonomní zadání dokončit technické vlny včetně ověřeného vydání.

## Zjištění a rozhodnutí

- R03 `moje_osoby.php`: dva přímé seznamové dotazy a pět pevných dotazů
  registračních podmínek. Změřit 0/100/1000 historických žádostí a čerstvou
  revokaci; žádost není schválená vazba. Nepřepisovat odlišný kontrakt rolí.
- `accountPersonCanManage` nemá produkční volající (jen definice a testy).
  Cílenou optimalizaci vyřadit: žádný doložený dopad na aktivní cestu.
- R04 základní karta má cílené ID a limity historie 5/6/8/80. Samostatně
  ověřit kontext editoru. Její roční časová osa ale načítá plány celé aplikace
  a opakuje historické rozvinutí hierarchie i členství pro každé tým/datum.
  Stejnou službu používá rodinná/sportovní stránka soupisek. Proto navazuje W8.1.

## Přijatý kandidát W8.1

1. Zmrazit současnou časovou osu. RED rozpočet pro 1/12/52 týdnů a více týmů.
2. Uvnitř jediného volání načíst relevantní historické hrany a členství jediné
   osoby. Výběr stavů provést v SQL kvůli kolacím a NULL; data držet pouze
   během projekce, nikdy jako oprávnění k zápisu nebo mezi požadavky.
3. Zachovat inkluzivní konce období, odstraněné historické členství, zrušené
   plány, cykly/duplicitní cesty, přímou účast, plán+skutečnost jako jednu položku,
   RSVP, uzavřenou skutečnost a pořadí. Proudově číst plány/skutečné tréninky,
   pokud měření prokáže zmenšení materializace při shodném výsledku.
4. Stejná data pro chybějící volitelné tabulky, změnu členství mezi voláními,
   různé osoby/PDO, kolace a hranice roku. Nezměnit route guard ani writery.
5. Profily s migracemi/indexy, PHPStan/style, plný PHPUnit a obě MariaDB;
   přesný zelený PR/main, chráněný deploy, read-only a HTTP před/po.
6. Doložit R03/R04 rozhodnutí a navázat zbývajícími W8–W15. Kalendář/plánovač,
   rezervace, obchod, finance a další oblasti nejsou tímto plánem uzavřeny.

## Realizace a rozhodnutí

Časová osa načte členství a hrany nejvýše jednou, zpětný průchod hierarchií
pro každé datum zachová historické potomky včetně cyklů. Původní `fetchAll`
plánů/skutečností ponecháno: proudové čtení by vyžadovalo řešit souběžný
aktivní PDO kurzor, zatímco naměřený problém je opakování SQL.

R03 má pro 0/100/1000 historických žádostí stále sedm dotazů; žádné N+1.
Historii záměrně vrací celou, nový limit by změnil zobrazovaný obsah.
Zachována čerstvá revokace a kontrola ověřeného účtu. Pět dotazů na aktuální
podmínky je pevný malý náklad, bez doložené potřeby měnit publikaci verzí.
`accountPersonCanManage` nemá produkční volající, kandidát vyřazen.

R04 základní karta používá ID a omezené poslední záznamy. Editor má pevný
počet dotazů, ale jeho kompletní historické soupisky/platby nemají stránkování.
Finanční čtení a úplnost historie přecházejí do W10; nejsou prohlášeny za
vyřešené tímto měřením. W7 read optimalizace je pro zjištěné N+1 uzavřena,
další kandidáti mají vlastní rozsah a důkazy.

[Měření a ověření](verification/refactoring-w81-20261030/RESULTS.md).
Žádné integrační přepínače ani skutečné záznamy se nemění. Lidské UAT zůstává
samostatné. Nasazení až po úspěšném ověření přesného commitu.
