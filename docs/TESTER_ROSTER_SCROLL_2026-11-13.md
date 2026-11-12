# ROSTER-SCROLL-01: podklad pro testerku — pozice ve správě soupisek

Aktualizováno 13. 11. 2026. Toto je aktuální testovací scénář k nasazené opravě [#462](https://github.com/KovoPraha/evidenceTreninku/pull/462) na stránce `https://kis.kovopraha.cz/kis_roster_hierarchy_admin.php`. [Technické ověření a vydání](verification/roster-scroll-20261112/RESULTS.md) jsou PASS; **přihlášené produkční UAT je NOT RUN**. Následující kroky nejsou záznamem výsledku.

## Co se má změnit

Po odeslání formuláře a případném přesměrování se stránka vrátí ke svislé pozici, odkud testerka akci spustila. Musí zůstat vybraná soupiska, rozhodné datum a hledání osoby. Jednorázový parametr `scroll_y` se po obnovení odstraní z adresy. Po samostatném novém otevření stránky se stará pozice nemá samovolně obnovit.

## Příprava

- Přihlásit se účtem s oprávněním ke správě skupin a soupisek. Použít dlouhý seznam, na kterém je možné posunout stránku alespoň o několik obrazovek.
- Pro zápisové scénáře použít jen osoby a soupisky, na nichž má testerka schváleno provést skutečné změny; jinak tyto scénáře označit `BLOCKED`. Předem zaznamenat výchozí členství a plán návratu testovacích dat. Skutečné členství neměnit jen kvůli ověření posunu bez takového podkladu.
- Test provést na počítači a na telefonu nebo v mobilním prohlížeči. Zapsat prohlížeč, přibližnou šířku okna, roli a datum. Osobní údaje ani snímky členů neukládat do veřejného repozitáře.

## Scénáře

| ID | Postup | Očekávaný výsledek |
| --- | --- | --- |
| S01 | Vybrat soupisku, datum a hledání. Sjet doprostřed dlouhého seznamu a provést schválení nebo potvrzení připraveného členství. | Po potvrzení akce je vidět stejná část seznamu, vybraná soupiska, datum i hledání zůstávají. Stránka neskočí nahoru. |
| S02 | Bez ručního posunu zpět provést další dvě povolené akce u jiných připravených osob. | Po každém odeslání lze pokračovat v místě poslední akce; pozice se nevleče do další nezávislé návštěvy. |
| S03 | U připravené osoby provést přidání do soupisky a u jiné přestup mezi soupiskami. | Po každém POST/redirect/GET zůstane pracovní místo přibližně stejné; výběr a hledání se zachovají. Zkontrolovat i potvrzovací/náhledový krok, pokud se zobrazí. |
| S04 | Ukončit připravené členství ve spodní části dlouhého seznamu. | Potvrzení je viditelné, stránka zůstane u původní oblasti. Pokud záznam ze seznamu zmizí, místo se může mírně posunout podle nové výšky obsahu. |
| S05 | Vyvolat bezpečnou validační chybu, například neúplným formulářem bez provedení zápisu. | Chybová zpráva je viditelná v původní části stránky, filtr a soupiska se neztratí. Nevznikne změna členství. |
| S06 | Opakovat S01 až S05 v mobilním zobrazení, zvlášť akci na konci seznamu. | Stránka se po akci nevrátí na začátek; ovládací prvky jsou použitelné a filtr zůstává. |
| S07 | Po úspěšné akci zkontrolovat adresní řádek, pak stránku otevřít samostatně v nové kartě. | Po obnovení už v adrese není `scroll_y`; nová nezávislá návštěva nepřebírá starý posun. |

## Zápis výsledku

U každého ID uvést `PASS`, `FAIL`, `BLOCKED` nebo `NOT RUN`, prostředí, roli, výchozí a výslednou oblast seznamu, zachování soupisky/data/filtru a případnou zprávu. Při `FAIL` zaznamenat přesné kroky a anonymizovaný důkaz v klubovém soukromém úložišti. U zápisových kroků potvrdit stav členství a úklid připravených testovacích dat. Výsledek připojit k [protokolu opravy](verification/roster-scroll-20261112/RESULTS.md); technický PASS sám neuzavírá přihlášené UAT.
