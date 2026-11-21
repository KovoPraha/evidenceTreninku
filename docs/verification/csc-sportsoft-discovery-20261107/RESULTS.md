# P15 — průzkum ČSC / Sportsoft a dokumentační ověření

Datum průzkumu: 7. 11. 2026, Europe/Prague.
[Implementační plán](../../CSC_SPORTSOFT_READ_SYNC_PLAN_2026-11-07.md),
[registr](../../WORK_PLAN_CURRENT.md), [rozhodnutí](../../DECISION_LOG.md#d2026-11-07-09).
Bezpečný strukturovaný záznam: [discovery.json](discovery.json).
Jde o historický průzkum, nikoli implementovanou či zapnutou integraci.

## Metoda a rozsah důkazu

Playwright CLI 0.1.23, samostatné headed okno Chrome na stanici uživatele.
Uživatel ručně přihlásil obě domény. Prohlídka četla skutečný DOM, atributy
polí a navigaci; nebyla odvozena jen ze screenshotů.
ČSC a Sportsoft byly přihlášené pro vlastní klub/tým. Aktuální uživatelské
profily/cookies nebyly exportovány do repozitáře.
V tomto dokumentačním kroku se weby znovu neměnily ani nesynchronizovaly do KIS.

| Kontrola | Výsledek a přesná mez |
| --- | --- |
| Sportsoft: seznam závodů | PASS: 4 existující přihlášky, 5 tlačítek vytvoření; vybraný přehled JANEV CUP |
| Sportsoft: existující přihláška Hlinsko 31. 10. 2026 | PASS: UCI ID, kategorie, zaplaceno, filtr a samostatná úhrada; závod již proběhl, není to doklad postupu před uzávěrkou |
| Sportsoft: seznam členů | PASS: 130 datových řádků, role a source api/manual; web uvádí jen platné licence aktuální sezony |
| Sportsoft: přidání člena | PASS pouze otevření formuláře /private/addracer.aspx: jediné UCI ID, maxlength 11, bez vyplnění/podání |
| ČSC: přehled vlastního oddílu | PASS: přehled oznámil 254 položek; přečtena první stránka a struktura stránkování. Úplné načtení všech 254 řádků NOT RUN |
| ČSC: detail licence | PASS: externí ID licence v URL, tabulka výsledků; žádný import výsledků |
| ČSC: editace licence a Nová Licence | PASS: pole, typy, name atributy, označení povinnosti, nabídky a upload accept; oba dialogy uzavřeny Zrušit bez změny |
| ČSC: Žádost | PASS: prázdný košík, Odeslat žádost disabled; žádná položka nebyla přidána |
| Konec průzkumu | PASS: ČSC 0 dialogů, Žádost (0), přehled stále 254; Sportsoft stále 4 existující / 5 nevytvořených |
| Nová závodní přihláška / Získat licenci | NOT RUN: kliknutí může už založit záznam či položku žádosti |
| Odeslání, úhrada, storno, změna identity | NOT RUN; nejsou součástí povoleného čtecího rozsahu |
| Export, všechny výsledky a kompletní stránkování | NOT RUN; přítomnost tlačítka/tabulky není dokončený import |
| Headless / restart nonstop stanice / délka relace | NOT RUN; dnešní headed prohlídka toto neprokazuje |
| Implementace, nasazení, aktivace P15 | NOT RUN |

Počty řádků nejsou počty unikátních osob. Rozdílné rozsahy seznamů ČSC a
Sportsoftu nepovažovat automaticky za chybějící členy. Nulový počet mutačních
akcí je záznam provedeného postupu a kontroly UI; nebyl k dispozici nezávislý
audit databází poskytovatelů.

## Ověřená zjištění ovlivňující plán

1. Sportsoft je ASP.NET Web Forms s navigačními postbacky, ViewState a
   EventValidation. Čtecí ovládání proto nelze omezit jen na GET.
2. ČSC načítá Blazor/Radzen obsah opožděně i po změně URL; čekat na obsah.
   ID typu 0HN… se mění, ale name atributy polí Entity.* jsou čitelné.
3. ČSC má duplicitní názvy kategorií: kadeti M(15 - 16) s ID 90, 109, 129,
   158. Konkrétní vazba na disciplíny neověřena; není dovoleno ji odhadnout.
4. Nová Licence má 20 popisků označených form-label-required včetně
   fotografie, nouzového kontaktu a UCI role. HTML required=false není
   důkaz nepovinnosti. Úplná serverová validace bez uložení neověřena.
5. Formulář má prohlášení o podkladech/písemném souhlasu. Jejich text je
   popsán v JSON jako pozorovaný obsah, nikoli právní výklad; nic nezaškrtnuto.
6. Sportsoft přidává člena přes UCI ID. Source api naznačuje přenos dat, ale
   dostupnost API pro klub, jeho smluvní podmínky ani zpoždění přenosu neověřeny.
7. Sportsoft má session cookies expires=-1. ČSC Identity cookie má budoucí
   expiraci. Cookie metadata neurčují serverový timeout ani možnost keepalive.
8. Zaplaceno ve Sportsoftu je externí stav; nelze jím zakládat úhrady v KIS.

## Napojení na repozitář

Inspekce zdrojů na base 8e9cdce1:
- sportovec_editor.php má textové uciid do 80 znaků, bez záruky uniqueness.
- kis_match_lib.php dovoluje silnou shodu i jméno + datum narození.
  P15 proto navrhuje nový striktní resolver; starý import se nemění.
- staff_workspaces.php přiděluje evidenci sportovců registráři.
- Automatické služby P15, machine endpoint a nové tabulky nejsou hotové.

## Kontroly dokumentace

Ověřeno místně před předáním:
- php tools/check-current-docs.php — PASS po převzetí main 2ef20aed: 4453 místních odkazů,
  108 archivních bloků, 0 chyb, CURRENT/HANDOFF každý 80 řádků.
- git diff origin/main --check — PASS.
- Obsah discovery.json ručně porovnán s průzkumem; žádné osobní hodnoty,
  hesla, hodnoty cookies ani URL digitálních karet. Údaje v JSON jsou
  metadata formulářů a souhrn, nikoli export členů.

První mezikontrola zachytila chybějící registraci nového plánu v katalogu;
po doplnění JSON katalogu a indexů opakovaná kontrola výše prošla.

Runtime testy aplikace NOT RUN: změna je pouze dokumentační.
Větev publikována, [PR #371](https://github.com/KovoPraha/evidenceTreninku/pull/371) otevřené;
merge/deploy/aktivace nejsou tímto dokumentačním krokem prováděny.
