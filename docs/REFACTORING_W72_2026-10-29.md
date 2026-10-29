# W7.2 — celý rodinný přehled

Zahájeno 29. 10. 2026 z `d15db2d0c8ca88ae8bd54ffd9772e9f91945adbc`.
Větev `codex/refactoring-w72-family-overview-20261029`, izolované worktree
`C:/Users/Marek/Documents/Codex/evidencePavel-refactoring-w72-20261029`.
Vlastník výslovně autorizoval autonomní implementaci, sloučení a nasazení
po ověření. Původní checkout a cizí WIP zachovat.

## Postup a kontrakt

1. Změřit celou datovou sestavu stránky `booking/sportovni_prehled.php`
   na syntetickém migrovaném schématu, rodiny 0/1/5/20 osob. Rozlišit
   hlavní profil, finance, kalendář, RSVP, týdenní/roční souhrn a úkoly.
   Včetně skutečného renderu stránky a jejího existujícího sync náhrad;
   jen syntetická data, žádné produkční přihlášení ani aktivace transportů.
2. Kandidát: `familyPortalPersonOverview` při každém profilu znovu načítá
   všechny členy a jejich membership pole. Nejprve zmrazit původní funkce,
   doložit shodný výstup a nevyhovující rozpočet; potom cílit opakovanou
   kontrolu a načtení na jediný profil. Bez cache oprávnění, předávání
   důvěryhodných seznamů z volajícího či změny W7.1b writerů.
3. Zachovat celé výstupy, pořadí, role, volitelné tabulky, chybové větve,
   členství, sportovní skutečnost a finanční význam. Ověřit revokaci mezi
   voláními i uprostřed sestavování přehledu. Žádné omezení historie či UI.
4. Oddělit instrumentaci SQL od měření času/paměti na běžném PDO. FK,
   nativní prepares, EXPLAIN, MariaDB 10.3/11.4; případné další nálezy
   pojmenovat a nerozšiřovat opravu bez důkazu.
5. Plná sada, PHPStan, styl, dokumentace, zelený PR i přesný main. Před/po
   čtecí produkční audit a HTTP smoke, záloha a živá identita. Deploy
   `uat_schvaleno=false`, `importovat_krouzky=false`. Aktualizovat stav,
   archiv, předání a důkazy; lidská přejímka a aktivace služeb samostatné.

## Stav

Plán uložen před implementací. RED doložil 420 místo 40 membership dotazů.
Implementováno cílení na profil při zachování čerstvé autorizace. Celý GET
20 osob: 2706 → 2326 SQL a 2953 → 2193 řádků; shodná data i normalizované HTML.
Šest nových testů / 23 assertions PASS. Místní časové měření je kolísavé
s horším mediánem current pro 20 osob; před sloučením ověřit CI a klidový běh.
Úplné testy a PHPStan běží, merge/deploy dosud neprovedeny.
[Důkazy a meze](verification/refactoring-w72-20261029/RESULTS.md).
