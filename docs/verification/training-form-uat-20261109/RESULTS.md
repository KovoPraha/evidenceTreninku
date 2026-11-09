# Q2-TIME-UAT — přihlášený produkční formulář

9. 11. 2026, Chrome na Windows, produkční KIS, kontext **Superadmin · Trenér**.
Po předchozí blokaci vlastník výslovně zadal nový pokus: chybný čas, zachování
textu po obnovení stránky a po zavření okna. **Tyto tři zkoušky PASS.**
[Plán](../../QUALITY_NEXT_SLICES_2026-11-08.md#q2-time-uat--nový-pokus-9-11-2026),
[původní vydání a meze](../training-form-resilience-release-20261109/RESULTS.md).

## Průběh a důkazy

Vlastník se ručně přihlásil. Prázdný nový formulář původně nenabízel koncept.
Použit výhradně syntetický text `TEST Q2-TIME 2026-11-09 — NEUKLÁDAT`,
poznámka, datum 9. 11. 2026 a existující testovací sportovec
`TEST-COACH-20261031 TEST Alfa`, typ Kolo, vzdálenost 200 m.
Žádný skutečný sportovec nebyl vybrán. Plán nevyžadoval platné odeslání.

| Zkouška | Výsledek | Pozorování |
| --- | --- | --- |
| Chybný čas `13,456` a tlačítko Uložit trénink | PASS | Nativní validace zastavila odeslání, fokus přešel na čas, zobrazila se česká nápověda s `00:13.456`. Formulář zůstal otevřený s textem a měřením. |
| Obnovení stejné stránky | PASS | Přesně se vrátila náplň včetně nového řádku, poznámka, datum, testovací sportovec, typ Kolo, 200 m i neplatný čas. |
| Zavření samostatného okna a nové otevření zápisu | PASS | Vlastní testovací karta byla přesunuta do samostatného okna; obsahovalo jen tuto kartu. Jejím zavřením okno zaniklo, ověřeno inventářem oken. Nový formulář nabídl Obnovit koncept a původní text/poznámku; po kliknutí obnovil všechna uvedená pole a měření. |
| Opravený čas `00:13.456` | PASS | Čtecí DOM kontrola: `validity.valid=true`, `validationMessage=''`. Platný formulář nebyl odeslán. |

[Pozorování v JSON](observations.json),
[hodnoty po reloadu](after-reload-fields.txt),
[hodnoty po zavření a obnově](after-window-close-fields.txt).
Textové výřezy pocházejí ze skutečného accessibility stromu, nikoli z fixture.
[Stav konceptu](concept-status.png) a [stav po reloadu](after-reload-status.png)
ukazují uložený koncept a datum. Export obrázku zachytil horní část formuláře;
nepoužívat tyto PNG jako obrazový důkaz chybové bubliny nebo hodnot textových polí.
Chybová bublina i texty byly viditelné při interaktivní kontrole; jejich
závěry výše dokládají pozorování a výřezy accessibility stromu.

## Úklid a omezení

Po ověření byl otevřen další nový formulář a nabídka obsahovala pouze vlastní
označený koncept. Stisknuto Zahodit koncept a přijato potvrzení. Následná
kontrola stránky opakovaně nedostala odpověď CDP; potvrzovací dialog už nebyl
aktivní. Náhradní čtecí kontrolu okna nástroj zastavil, protože nedokázal
bezpečně určit aktuální URL. Další ovládání bylo ukončeno.
**Výsledek odstranění konceptu NEOVĚŘEN**; při příštím otevření ověřit, zda
se ještě nabízí tento konkrétní TEST Q2-TIME, a případně uklidit jen jej.
Původní pracovní kontext Systém nebyl obnoven; poslední potvrzený kontext Trenér.
Přidělení rolí/oprávnění se neměnilo, ostatní karty ani okna se nezavíraly.

Počáteční prodlevy nástroje a ruční login byly překonány. Závěrečná blokace
nastala až po úspěšném ověření tří požadovaných scénářů, nezneplatňuje je.
**NOT RUN:** ukončení celého procesu Chrome, restart OS, reálný výpadek sítě
při platném POST, skutečné uložení do DB a následné odstranění úspěšného konceptu,
editace existujícího tréninku, jiná trenérská role, přílohy a jiný účet/zařízení.
Nebyl proveden přímý audit DB ani záznam síťových POST; nativní blokaci potvrzuje UI.
Nejde o garanci proti smazání úložiště prohlížeče nebo všem výpadkům.

## Souborové předání

Větev `codex/training-form-uat-20261109`, vlastní worktree
`C:/Users/Marek/Documents/Codex/evidencePavel-training-resilience-20261109`.
Pouze dokumentace a neosobní důkazy; bez úpravy kódu, migrací nebo nasazení.
Původní hlavní checkout a cizí WIP zachované.
