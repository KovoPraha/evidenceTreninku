# Q01: přihlášená čtecí kontrola a mobilní vada plánovače

8. 11. 2026, produkční KIS v Chrome; původní doklady vydání zůstávají v
[ROLE01-MOB](../access-mobile-cards-20261107/RESULTS.md),
[pickeru](../attendance-picker-production-readonly-uat-20261107/RESULTS.md),
[zdravotním přehledu](../medical-state-release-20261107/RESULTS.md) a
[plánovači](../planner-hierarchy-release-20261107/RESULTS.md).
Uživatel se sám přihlásil existujícím účtem. Heslo ani cookie se nečetly.
Čtecí ovládání přepínalo pouze aktivní pracovní kontext již přidělených rolí;
nepřidělilo ani neuložilo žádné oprávnění. Snímky s osobními údaji nejsou v Gitu.

| Cesta a role | Výsledek | Důkaz a mez |
| --- | --- | --- |
| Lidé a přístupy, `Superadmin · Systém` | ČÁSTEČNÝ PASS | Desktopová tabulka čitelná. Při 390 i 360 px se zobrazily sémantické karty a plnošířkové odkazy na úpravu; `scrollWidth=clientWidth=345` při 360 px. Filtr oblasti Bar ukázal 6 z 48 účtů. Hledání, prázdný výsledek a otevření editoru #64 nebyly provedeny; žádné oprávnění se neupravilo. |
| Docházka a uzavření, `Superadmin · Trenér` | ČÁSTEČNÝ PASS | Existující trénink z 5. 5. 2026 se otevřel. Výběr osob přešel stranu 1→2→1; hledání `zzzxq` vrátilo 0, obecný dotaz `a` 50 výsledků na straně. Po vymazání dotazu a novém načtení opět strana 1/50, potvrzovací checkbox zůstal nezaškrtnutý. Nebyla použita uzávěrka, omluva ani uložení. Výběr konkrétní osoby/verze a hledání osoby pro omluvu zůstávají NOT RUN; bez DB snapshotu netvrdit globální neměnnost produkčního záznamu. |
| Plánovač, `Superadmin · Trenér` | FAIL při 360 px | Dnešní stránka se načetla, filtry `Moje/Vše` a soupisky jsou vidět. Při 390 px `clientWidth=scrollWidth=375`; při 360 px `clientWidth=345`, `scrollWidth=367`, viditelný spodní vodorovný posuvník. Jediný prvek za pravým okrajem je select `team_id`: pravý okraj 367 px, vypočtená šířka 351 px, inline `width:auto`. Skutečné naplánování ani zápis nebyly zkoušeny. |
| Zdravotní prohlídky, `Superadmin · Členové a KIS` | ČÁSTEČNÝ PASS | Role `Vedoucí sportu` byla na trase oprávněně odmítnuta, registrářská role stránku otevřela. Při 390/360 px jsou filtry a prázdný stav čitelné bez vodorovného přetékání (`345/345` při 360 px). Volba „Platné“ se propsala do URL i vybraného stavu. V této roli bylo 0 záznamů před i po filtru; správnost obsahu a další stavy nebyly ověřeny. |

Po čtecím průchodu byl zahájen návrat k původnímu pracovnímu kontextu
`Superadmin · Systém` a reset dočasného viewportu. Ovládání prohlížeče při
tom vypršelo; konečný kontext a viewport nejsou potvrzené. Při dalším stabilním
připojení je ověřit a případně vrátit. Žádná zapisující akce nebyla spuštěna.

Q2 opravuje pouze konkrétní [šířkový problém](../../WORK_PLAN_CURRENT.md#q01-q2--mobilní-filtr-plánovače).
Původní plná lidská přejímka rolí, docházky a zdravotních záznamů tím není
nahrazena.
