# Q01-Q2 — vydání mobilního filtru plánovače

8. 11. 2026, produkční KIS. Vlastník vydání: vývoj. Pokyn vlastníka:
`NASADIT` v tomto chatu po [Q1/Q2 protokolu](../quality-uat-20261108/RESULTS.md)
a sloučení [#390](https://github.com/KovoPraha/evidenceTreninku/pull/390).

## Rozsah a předlet

- Cílový `main`: `755b20a22a4401f54fd8117d8098a89e8603b77c` (#390 a dokumentační #393).
- Poslední doložený produkční release před vydáním: `519ff098961943f1aa58a70e40fed3ffba37cc96`, [běh 42477866185](https://github.com/KovoPraha/evidenceTreninku/actions/runs/42477866185) SUCCESS.
- Mezi oběma SHA jsou změny plánovače/testového profilu a sdílené dokumentace; žádný další import nebo změna rolí nebyly zadány.
- [#393](https://github.com/KovoPraha/evidenceTreninku/pull/393) na přesném stromu `755b20a2` má všech 11 CI PASS včetně MariaDB 10.3/11.4.
- Před akcí: žádný jiný `deploy-production.yml` neběžel. Anonymní GET `index.php` a `booking/eshop.php` HTTP 200, chráněný `planovac.php` HTTP 302 na `login.php`.
- Workflow `deploy-production.yml`: `potvrzeni=NASADIT`, `uat_schvaleno=false`, `importovat_krouzky=false`; [běh 42483838259](https://github.com/KovoPraha/evidenceTreninku/actions/runs/42483838259) na přesném cílovém SHA.

## Výsledek

| Kontrola | Stav | Důkaz / mez |
| --- | --- | --- |
| Nová ověřená záloha před aktivací | PASS | Krok `Vytvořit a ověřit produkční zálohu` skončil SUCCESS před aktivací v běhu 42483838259. Obnovu této nové generace tento průchod neprováděl. |
| Syntaxe a testy | PASS | Produkční workflow: kontrola PHP syntaxe SUCCESS; PHPUnit 2389 testů / 48273 assertions PASS. |
| Migrace, VS, aktivace, standardní smoke | PASS | Kroky migrací, zachování dosavadních variabilních symbolů, aktivace, zákazu HTTP přístupu ke klíči a HTTP smoke skončily SUCCESS. Import produktů kroužků byl podle vstupu přeskočen. Celý běh je terminálně SUCCESS na cílovém SHA. |
| Veřejné HTTP po nasazení | PASS | Nezávislý anonymní GET: `index.php` 200, `booking/eshop.php` 200, chráněný `planovac.php` 302 na `login.php`, stejně jako před akcí. |
| Přihlášený plánovač při 360/390 px a desktopu | PASS v cíleném rozsahu | Skutečný produkční Chrome, `Superadmin · Trenér`, po aktivaci: režim `Vše` ukázal všechny tři filtry. Při 360 px `clientWidth=scrollWidth=345`, pravý okraj `trener_id`/`team_id` 329 a `skupina_id` 234; při 390 px `clientWidth=scrollWidth=375`, okraje 330/359/234; desktop 1536 px `clientWidth=scrollWidth=1521`. Ve výchozím režimu `Moje` při 360 px také 345/345. Všechny tři selecty ve vydané DOM mají `width:auto;max-width:100%`. Původní pracovní kontext `Superadmin · Programy` a běžný viewport obnoveny. Bez zápisu tréninku. |

Žádné heslo, cookie, osobní údaje, zdravotní obsah ani produkční dumpy sem
neukládat. Původní místní WIP `kis_rosters_admin.php` zůstává mimo vydání.
Tento cílený čtecí průchod nepotvrzuje celou funkční přejímku plánování ani jiné role;
`uat_schvaleno=false` je záměrný stav workflow.
