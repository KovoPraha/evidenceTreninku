# Omezená agregace vzorů kopírování

P11-RHC2, 12. 11. 2026. Base 7d452b30. [PR #457](https://github.com/KovoPraha/evidenceTreninku/pull/457)
sloučen jako 87171a67. Git tree b1503a74c85bd51c3cfd84841bc65985fe296b17 je shodný s testovaným
7052add9613dbf5ccc5b91999afd702faec5b0db. Všech 13 kontrol PASS. [Párový běh](https://github.com/KovoPraha/evidenceTreninku/actions/runs/43213298697),
[kontroly](application-checks.json).

## Změna a shoda

ID a data způsobilých tréninků se načtou před detailními agregacemi.
Pracovní rozsah, skupina, období a kanonický filtr historické hierarchie
zůstávají součástí výběru. Skupiny, podskupiny a účastníci se agregují
nejvýše pro 100 vybraných tréninků. Původní predikáty se opět kontrolují.

Prvních 101 kandidátů kontrolujeme kvůli remízám dat a hranici sté položky.
Při remíze zachová přesný původní dotaz i dosavadní výběr. Časový přínos
závisí na rozložení dat. Remízy zjišťujeme také před týmovým filtrem, aby
se omezilo jeho opakované vyhodnocení. Kandidáty před původním dotazem uvolníme.

Na každé MariaDB 10.3/11.4 prošlo 44 kombinací velikosti 100, 1 000 a
10 000 tréninků, filtrů a remíz. Největší sada ověřuje celý a týmový
výběr. Menší sady zahrnují skupinu, dítě hierarchie, nulový a neexistující
tým, prázdnou skupinu, období a trenérský rozsah. Každá kombinace má
4 zahřívací a 17 střídavých měřených párů. Přesně se shodují všechna
pole, typy, pořadí a výběr. SQL cache je vypnutá, měření probíhá ve
čtecí transakci a kontrolní součty zůstaly shodné.
[Profil 10.3](mariadb-10.3/native.json), [profil 11.4](mariadb-11.4/native.json).

Celé HTTP má 14 scénářů na databázi. Jedenáct pozitivních cest má
17 měřených párů, tři kontrolní páry ověřují 403 vedoucího sportu
a registráře a anonymní 302. Ověřeny jsou oba trenérské účty a skrytí
cizích tréninků. HTML a sledované bezpečnostní hlavičky se shodují po
normalizaci nonce. Databázové kontrolní součty zůstaly shodné.
[HTTP 10.3](mariadb-10.3/http.json.gz), [HTTP 11.4](mariadb-11.4/http.json.gz).

Chrome provedl 88 měřených a 24 zahřívacích navigací. Desktop 1036 × 703
ve světlém režimu a mobilní rozlišení 390 × 844 v tmavém režimu mají
dvě pixelově shodné dvojice snímků. Hledání skrývá a znovu zobrazuje
stejný vzor. Přenos Bootstrapu je shodný.
[Prohlížeč](mariadb-11.4/browser-summary.json).

## Naměřené mediány v ms

Na 10 000 trénincích znamená unique různá data a tied opakovaná data.
Výběr all je celý seznam, root je filtr kořenové soupisky.

| MariaDB | Data | Výběr | Před | Po |
| --- | --- | --- | ---: | ---: |
| 10.3 | tied | all | 505.48 | 508.58 |
| 10.3 | tied | root | 564.00 | 573.43 |
| 10.3 | unique | all | 498.86 | 8.87 |
| 10.3 | unique | root | 586.68 | 80.84 |
| 11.4 | tied | all | 385.51 | 390.70 |
| 11.4 | tied | root | 428.39 | 429.85 |
| 11.4 | unique | all | 386.44 | 6.26 |
| 11.4 | unique | root | 459.93 | 64.18 |

[Vzorky a paměť](mariadb-11.4/native.json),
[celé HTTP na 1 000 trénincích](http-medians.json). Jde o syntetické CI měření.

Paměť u seznamu bez týmu s různými daty narostla ze 111 928 na
181 976 B, tedy o 70 048 B. U týmového výběru klesla ze 17 759 368 na
15 176 552 B, tedy přibližně o 2,6 MB. Výchozí 90denní HTTP seznam má
prakticky stejný čas před a po. U remíz je zachována původní agregace.

## Vydání

[Deploy 43215069511](https://github.com/KovoPraha/evidenceTreninku/actions/runs/43215069511) SUCCESS. 2456 testů a 56296 kontrol.
Záloha 389 tabulek a 2 triggerů ověřena.
Aktivace, konfigurace, migrace, variabilní symboly, index a úklid PASS.
Přímý runner HTTP 200, výsledný smoke HTTP 200. Import a příznak UAT false.
Produkční anonymní GET bez filtrů, s celou historií a s týmem mají stejné
302 před i po, místní CSS shodný SHA-256. Přihlášené produkční UAT NOT RUN.
[Doklad](release.json), [produkce před](production-before.json), [po](production-after.json).

Původní fio checkout a WIP zachované. Pomocný místní server na portu 33317
byl vypnut a testovací schéma odstraněno. Automatická kontrola odmítla
odstranění pomocných datových souborů z místního output hlášením blocked
by policy. Soubory zůstaly v samostatném worktree v output/owned-copy-db
a output/owned-copy-temp.
