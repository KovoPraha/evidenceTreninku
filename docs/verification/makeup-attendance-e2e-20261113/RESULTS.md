# MAKEUP-AUTO-01 — blokátor funkčního testu (13. 11. 2026)

Základ: `origin/main` `bc8da209` po `git fetch` 13. 11.; vydání kódu
automatických náhrad dokládá [předchozí protokol](../makeup-auto-20261112/RESULTS.md).
Dokumentační změna vznikla na větvi `codex/makeup-attendance-blocker-20261113`
v odděleném worktree `C:\xampp\evidencePavel-makeup-attendance-blocker-20261113`.
Tento záznam zachycuje hlášení vlastníka a stav ověření, nikoli nově provedený
produkční test. Osobní údaje, přístupy ani skutečná docházka se neukládaly.

| Krok / důkaz | Stav | Poznámka |
| --- | --- | --- |
| Rodičovská omluvenka se zobrazuje jako čekající na potvrzení | HLÁŠENO VLASTNÍKEM | V tomto průchodu znovu nezávisle netestováno. |
| Dostupný trenérský zápis skutečné docházky konkrétního tréninku | BLOKÁTOR HLÁŠENÝ VLASTNÍKEM | Kód obsahuje starší cestu `formular.php?plan_id=…`; její dostupnost a dokončení v požadovaném přihlášeném postupu nejsou prokázané. |
| Nasazení dokončené trenérské evidence dle E4-06 | NOT RUN | Samostatné zadání a jeho akceptace. |
| Uzavřená absence propojená s omluvenkou a právě jeden nárok | NOT RUN | Vyžaduje skutečný zápis docházky po proběhnutí lekce. |
| Nárok zobrazený rodiči a rezervace vhodného náhradního tréninku | NOT RUN | Vyžaduje předchozí krok a vhodné testovací účty/termín. |
| Úplné funkční ověření automatických náhrad | BLOKOVÁNO | Technické testy, samotná omluvenka a nasazení kódu tento krok nenahrazují. |

Po dokončení a nasazení trenérské evidence zopakovat přihlášený scénář na
oddělených účtech: rodič omluví zaplacenou lekci včas → nárok čeká → trenér po
lekci zapíše skutečnou absenci a uzavře docházku → vznikne právě jeden nárok
→ rodič jej vidí → rezervuje kompatibilní volný termín. Doložit nastavení,
role, časovou posloupnost, počet nároků, stav rezervace a úklid testovacích dat.
Zvlášť ověřit přítomné dítě, pozdní omluvu a nepřípustný nebo plný cíl.

[Tematický plán a blokátor](../../TRAINING-RSVP-AND-MAKEUPS-2026-10-14.md#blokující-závislost-úplného-funkčního-ověření-13-11-2026),
[registr práce](../../WORK_PLAN_CURRENT.md#makeup-auto-01--automatické-náhradní-lekce-12-11-2026).
