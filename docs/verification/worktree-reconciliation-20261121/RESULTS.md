# Inventura rozpracovaných checkoutů — 21. 11. 2026

Stav: čtecí inventura dokončena; uvolnění produkčního kódu soupisky probíhá samostatně v ROSTER-HISTORY-01. Vlastník: vývoj KIS. Pokyn vlastníka: zjistit původ nečekané rozpracované změny, uzavřít proveditelné části a neztratit historické podklady. Inventura nepovoluje odhadovat provozní údaje ani vydávat starý konflikt za hotový release.

## Metoda a rozsah

Ověřeny `git status`, `git diff`, `git worktree list`, historie větví, otevřené GitHub PR a současné plány na `origin/main` 21. 11. 2026. V repozitáři bylo 201 registrovaných worktrees, sedm s hlášenou změnou sledovaných souborů včetně původního hlavního checkoutu. Počty worktrees nejsou počty běžících vláken. Staré pracovní a soukromé výstupy v `output/`, `outputs/`, `var/` a `.codex-attachments/` nebyly smazány ani přidány do Gitu.

## Co zůstalo v pracovních stromech

| Checkout / oblast | Zjištění | Naložení a další krok |
| --- | --- | --- |
| Hlavní `C:/xampp/htdocs/evidencePavel`, Fio | Stará větev `codex/fio-auto-reconciliation-recovery` na `d1221f35`, 399 commitů za `origin/main`; lokální úprava `kis_rosters_admin.php` z 20. 10. měnila popisky již neaktivní stránky. Současná stránka pouze přesměrovává na `kis_roster_hierarchy_admin.php`. Funkční Fio řešení je v #102 a novější aktivace i dva hostingové importy jsou doloženy v [aktivaci](../live-payments-20261105/ACTIVATION.md) a [navazujícím výsledku](../fio-followup-20261105/RESULTS.md). | Starou úpravu uložit jako lokální patch `.codex-tmp/obsolete-kis-rosters-admin-20261121.patch`, obnovit jediný sledovaný soubor a přepnout hlavní checkout na novou větev `codex/current-workspace-20261121` z aktuálního main. Pro Fio nic znovu nenasazovat; skutečná uhrazená objednávka P07 zůstává neověřená. |
| BAR pilot, `evidencePavel-bar-integration-20261105` | Dva dokumenty z 6. 11. popisují zamýšlené přihlášení obsluhy, fyzickou zásobu, počáteční kasu a první prodej. Novější #440/#443 už nasadily obrazovky a jednorázově převedly 68 skladových zůstatků. | Není to chybějící aplikační deploy. Historické necommitnuté poznámky zachovat jako doklad záměru; aktuální P02 řídit [BAR předáním](../../SESSION_HANDOFF_BAR_2026-11-06.md). Převod zásob neopakovat. Provozní přejímka čeká na skutečná čísla a obsluhu. |
| Staff notifications / členství, `evidencePavel-staff-notifications-20261106` | Jediný necommitnutý řádek v registru tvrdí, že právě probíhá ostrý test sjednání. Současný registr P05/P07 jej výslovně označuje za odložený; navíc uvádí chybějící klubem ověřeného sportovce a reálnou platbu. | Nezveřejňovat nepravdivé „právě probíhá“. Skutečný tok doplnit až s ověřeným účtem, objednávkou, platbou a příjmem PDF. |
| Picker, `evidencePavel-picker-production-readonly-uat-20261107` | PR #378 je uzavřený bez merge a jeho věcný obsah převzal #388. Checkout zůstal v nedokončeném merge cizí optimalizace plánovače: 65 staged souborů a tři konfliktní dokumenty. Dva necommitnuté picker soubory jen zaznamenávají přihlášení a potřebu přepnout dostupný pracovní pohled Trenér; vlastní scénáře nevyhodnocují. | Konfliktní strom nepoužít k vydání. Podklady zachovat. Aktuální [výsledky kvality](../quality-uat-20261108/RESULTS.md) evidují částečné UAT pickeru a otevřené scénáře. Neoznačit je za PASS bez pozorování. |
| Refactoring W85, `evidencePavel-refactoring-w85-20261030` | PR #243 je sloučený; `git status` ukazuje šest upravených souborů, avšak `git diff --raw`, `git diff --numstat` a staged diff jsou prázdné. Git hlásí převod LF/CRLF. | Jde o lokální stav pracovního stromu bez zjištěné obsahové změny. Funkce W6–W15 jsou podle [uzavření](../../REFACTORING_CLOSURE_2026-10-30.md) nasazené; nic nevydávat z tohoto checkoutu. |
| Packeta, `evidencePavel-order-status-system-mail-20261021` | PR #163 už je sloučený. Pět necommitnutých historických dokumentů z 22. 10. zachycuje schválení registrace, prázdné seznamy v omezeném období, uložení veřejného klíče, fungující samostatnou CZ Z-BOX mapu a chybu klienta Packety při zobrazení smluvního ceníku. Nejde o důkaz skutečné zásilky ani její ceny. | Do společné evidence převzít pouze tento neosobní souhrn a ponechat lokální důkazy soukromé. Doprava zůstává vypnutá; smluvní cenu a první skutečný štítek/podání je třeba doložit před provozní aktivací. |
| Dočasný `evidence-security-audit-20261024` | Detached temp checkout s 1 564 sledovanými soubory označenými jako smazané; příčina nebyla prokázána. | Necommitovat hromadné mazání a strom zachovat pro případnou obnovu. |

Původní Fio patch je v lokálním `.codex-tmp/`, nikoli v Gitu; archiv není oprávnění vrátit historickou stránku do provozu. V hlavním checkoutu po přepnutí nejsou sledované lokální změny. Netracked výstupy zůstaly nedotčené.

## Otevřené GitHub PR k okamžiku inventury

Otevřených bylo 12 PR. #503 je dokumentační návaznost aktuální opravy soupisky. Starší nedraftové #417, #402, #401, #399 a #371 jsou dokumentace či UAT z 7.–9. 11.; jejich neaktuální odkazy na release nelze sloučit bez porovnání s dnešním main. #391 obsahuje skutečnou bezpečnostní změnu soukromého úložiště: její původní CI prošlo, ale vůči dnešnímu main má konflikty a vyžaduje nové posouzení kódu, testů a regresí. Drafty #386, #384, #382, #380 a #376 jsou rozpracované audity/testy nebo řetězec ČSC/Sportsoft; zejména draft není souhlas s nasazením. Seznam je [živě ověřitelný na GitHubu](https://github.com/KovoPraha/evidenceTreninku/pulls?q=is%3Apr+is%3Aopen).

Tyto PR nevznikly v současném hlavním checkoutu a samotný otevřený stav nedokazuje běžící vlákno. Jejich věcný obsah a případné nahrazení novějším main se musí vyhodnotit jednotlivě před uzavřením nebo merge. Hromadné nasazení z historických větví by přepsalo současný stav.

## Přesná hranice dokončení

- ROSTER-HISTORY-01: kód a dokumentace v #502 sloučené; aktuální produkční deploy a dokumentační #503 se sledují samostatně. Přihlášené UAT výchozího a historického dne ještě vyžaduje pozorování testerky.
- Fio, BAR obrazovky/sklad, stará Packeta aplikace a W85: aplikace již byla dříve vydaná. Opakovaný deploy z výše uvedených starých stromů by nic potřebného nedokončil.
- BAR fyzická inventura a kasa, skutečné P05/P07 objednání/platba/PDF, smluvní cena a první přeprava Packety jsou provozní kroky, které nelze pravdivě uzavřít z Git historie. Aktuální stav vede [registr](../../WORK_PLAN_CURRENT.md).
- Neaktivní checkouty a soukromé výstupy jsou zachované. Úklid lze řešit až po převzetí hodnotných důkazů a vyřešení otevřených PR; není součástí produkčního vydání soupisky.
