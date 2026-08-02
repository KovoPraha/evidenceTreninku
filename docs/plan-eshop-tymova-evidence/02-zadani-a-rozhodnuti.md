# 02 – Zadání a rozhodnutí

Stav: produktový návrh k potvrzení
Pravidlo: pracovní vlákno nesmí samo změnit rozhodnutí označené jako blokující.

## Produktová vize

Evidence se má rozšířit z interní evidence tréninků na klubovou platformu se dvěma
větvemi, které sdílejí identitu, platby a kredit:

1. **Klubový e-shop** pro členy i veřejnost, který postupně nahradí Shoptet.
2. **Členská a týmová evidence**, která převezme soupisky, předpisy, přihlášky na
   výjezdy a soustředění a po přechodném období může nahradit KIS.

Výsledkem nemá být jen sada obrazovek. Musí vzniknout dohledatelný systém, ve
kterém lze vysvětlit každou změnu člena, objednávky, platby i kreditu.

## Uživatelé a jejich potřeby

| Aktér | Potřeba |
|---|---|
| člen / sportovec | vidět svůj profil, kredit, platby, objednávky a přihlášky |
| rodič / zákonný zástupce | spravovat jedno nebo více dětí bez sdílení hesel |
| trenér | pracovat se soupiskou a účastí jen v přiděleném rozsahu |
| administrátor klubu | spravovat osoby, sezóny, akce, produkty a výjimky |
| ekonom | párovat platby, řešit vratky a uzavírat období |
| sklad / výdej | připravit objednávku a potvrdit osobní odběr nebo odeslání |
| veřejný zákazník | nakoupit bez členství a případně si založit účet |
| provozovatel | bezpečně nasadit verzi, obnovit data a dohledat chybu |

## Funkční rozsah

### A. E-shop

- produkty, varianty, fotografie, ceny, dostupnost a jednoduchý sklad,
- košík, slevový kupón a objednávka,
- osobní odběr jako první způsob převzetí,
- bankovní převod s platebními údaji a QR kódem,
- Stripe Checkout pro platbu kartou,
- později automatické párování Fio a doprava přes Packetu,
- jednorázový, opakovatelný import produktů ze Shoptetu,
- historie stavů, storno, vratka a audit ručního zásahu,
- možnost zaplatit povolenou část klubovým kreditem.

### B. Členská a týmová evidence

- osoby, kontakty, zákonní zástupci a vztahy v rodině,
- sezóny, týmy, soupisky, platnost členství a klubové role,
- bezpečný staged import z KIS s preview, konflikty a rollbackem,
- exporty potřebné po přechodnou dobu pro KIS,
- předpisy a evidence členských či jiných plateb,
- členské akce: výjezd, soustředění, kemp a další typy,
- přihlášení, kapacita, čekací listina, souhlasy, termín a storno,
- samoobsluha člena/rodiče z jednoho účtu.

### C. Společné funkce

- účet a oprávnění,
- společný platební záznam pro objednávku, akci, předpis a dobití,
- účetní ledger kreditu,
- notifikace a retry chyb,
- audit, export osobních dat a retenční pravidla,
- idempotence externích volání a provozní dashboard.

## Rozhodovací log

| ID | Rozhodnutí | Doporučení | Stav / co jej uzavře |
|---|---|---|---|
| D-001 | Tvar aplikace | modulární monolit v Evidence; adaptéry na hranicích | doporučeno |
| D-002 | Kde bude e-shop | interní modul Evidence, ne nový samostatný backend | doporučeno |
| D-003 | Shoptet | jednorázový import podle stabilního SKU; žádný dlouhodobý master | potvrdit dostupný export |
| D-004 | KIS | nejdříve shadow mode a paritní report; cutover až samostatným rozhodnutím | blokuje ostré nahrazení |
| D-005 | Účet člena | pro MVP Evidence `verejni_uzivatele`; účet se aktivuje postupně | doporučeno |
| D-006 | Rodina | vazební tabulka účet ↔ osoba s rolí a platností; ne jediný `sportovec_id` | blokuje návrh identity |
| D-007 | Budoucí sdílení uživatelů | Evidence zůstává samostatná; případně lze později federovat nebo propojit pouze identitu, bez sdílení doménových dat | potvrzeno 2. 8. 2026; mimo MVP |
| D-008 | Klubové akce | nová doména; nepoužívat účetní `ucto_udalosti` | doporučeno |
| D-009 | Kredit | neměnný ledger; reward a peněžní dobití jako oddělené kapsy | vyžaduje účetní/právní potvrzení |
| D-010 | Platby | jedna společná platební vrstva, ale oddělený stav objednávky a platby | doporučeno |
| D-011 | Částky | ukládat v haléřích jako integer, vždy s měnou | doporučeno |
| D-012 | První checkout | právě jedna platební metoda na objednávku; kombinaci kredit + karta odložit | doporučeno |
| D-013 | Doprava | osobní odběr v MVP, Packeta až po stabilní objednávce | doporučeno |
| D-014 | Hranice vůči Velocotě | žádná provozní/doménová integrace se neplánuje; případné sdílení uživatelů je jediný samostatný budoucí kontrakt | potvrzeno 2. 8. 2026 |
| D-015 | Migrace | číslované migrace s ledgerem, CLI kontrolou a zálohou před spuštěním | blokuje finanční schéma |

## Otevřené otázky pro vlastníka produktu

| Otázka | Proč je důležitá | Nejpozději před |
|---|---|---|
| Jaké exporty a stabilní ID poskytuje KIS? | určuje matching, historii a cutover | Fází 2 |
| Kdo bude zdrojem pravdy pro osobu a soupisku během přechodu? | brání obousměrnému přepisování | Fází 2 |
| Může veřejnost nakoupit bez registrace? | mění checkout, GDPR a podporu | návrhem e-shopu |
| Kdo smí claimnout dítě a jak to klub ověří? | bezpečnost nezletilých | implementací účtu |
| Je tréninková odměna převoditelná, vratná nebo expirovatelná? | určuje ledger a obchodní podmínky | návrhem wallet |
| Lze peněžní dobití vracet na účet? | účetní a regulatorní dopad | návrhem wallet |
| Jaká jsou pravidla storna akce a objednávky? | určuje stavy a vratky | návrhem plateb |
| Jsou Stripe a Fio účty společné pro všechny typy příjmů? | určuje párování a oprávnění | integrací plateb |
| Jaký rozsah Packety je nutný: boxy, pobočky, domů, zahraničí? | určuje UX a ceník | Fází dopravy |
| Jaké souhlasy a dokumenty se evidují u nezletilých? | privacy a audit | členskými akcemi |
| Jaké jsou DPH, dokladové a retenční povinnosti? | ovlivní model objednávek a exporty | finančním MVP |

## Nefunkční požadavky

- Každý webhook, importní řádek a bankovní pohyb má stabilní externí ID a lze jej
  bezpečně přijmout opakovaně.
- Finanční historie se neopravuje přepisem; chyba se vyrovná novým pohybem.
- Žádný uživatel nesmí získat přístup k dítěti pouze shodou e-mailu.
- Citlivé tokeny se ukládají hashované, mají expiraci a neposílají se v běžných
  query parametrech.
- Každá důležitá administrátorská změna má kdo/kdy/proč a původní hodnotu.
- Nasazení nesmí pokračovat bez zálohy; migrace má samostatný výsledek a lze
  provést obnovu podle ověřeného runbooku.
- Změna společného schématu bez migračního testu a rollback/forward-fix plánu
  nesmí být sloučena.

## Co není první MVP

- marketplace nebo více prodejců,
- více skladů a složitá logistika,
- automatické dynamické ceny dopravy,
- věrnostní program nad rámec klubového kreditu,
- mobilní aplikace,
- současné zrušení Shoptetu a KIS bez přechodného provozu,
- mikroslužby a provozní/doménové propojení Evidence s Velocotou,
- sdílené cookies nebo přímé sdílení aplikačních tabulek s Velocotou.

## Definition of Ready pro implementační úkol

Úkol se smí předat pracovnímu vláknu, pouze když má vlastníka, schválený rozsah,
vstupy a testovací data, dotčené tabulky/soubory, akceptační scénáře, bezpečnostní
omezení, migrační dopad a výslovně uvedené věci mimo rozsah.
