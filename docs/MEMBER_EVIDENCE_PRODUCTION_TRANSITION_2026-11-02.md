# Podklady pro provozní přechod evidence členů

Stav k 2. 11. 2026, 18:41 UTC. Navazuje na [pracovní plán](MEMBER_EVIDENCE_WORK_PLAN_2026-11-02.md)
a [implementační protokol](MEMBER_EVIDENCE_IMPLEMENTATION_2026-11-02.md).
Nejde o provedený převod ani schválený manifest mazání.

## Co potvrdila produkční inventura

[Čtecí workflow 41502903619](https://github.com/KovoPraha/evidenceTreninku/actions/runs/41502903619)
proběhlo úspěšně v READ ONLY transakci. Výstup neobsahuje osobní identity ani kontakty.

| Oblast | Zjištěný stav |
|---|---|
| Databáze | 367 tabulek, všechny InnoDB; 337 v nynějším aplikačním backup rozsahu, 30 cizích/archivních. |
| Hranice aplikace | Žádné databázové FK přes hranici ownership. Aplikační odkazy a soubory tím nejsou ověřené. |
| Organizace | 77 soupisek: 44 aktivních sezonních, 33 uzavřených. Aktivní sada obsahuje i testovací kolektivy. |
| Současný import | 435 intervalů `kis_shadow/active`, všechny nyní časově platné. Nejde nutně o 435 různých osob. |
| Historický import | 560 intervalů `kis_shadow/removed`, všechny s ukončenou platností. Jsou kandidáty na úklid po kontrole vazeb. |
| Vlastní/testovací členství | 1 ukončený ruční interval a 2 aktivní intervaly pilotu ze zdroje `admin`; samostatná dispozice. |
| Osoby | 1 507 karet. Žádné mazání osob se tímto převodem nenavrhuje. |
| Nabídky | 82 záznamů; identitu produktu, nabídky a objednávky zachovat. |
| Finanční návaznosti | 8 objednávek a 8 plateb; všechny ponechat. Aktuálně 0 členských předpisů, 0 programových přijetí, 0 kreditů a 0 rezervací náhrad. |

Čísla jsou snapshot, nikoli trvalé provozní předpoklady. Před převodem se musí
načíst znovu za pozastavených dotčených zápisů a porovnat s manifestem.
Podrobná mapa skutečných ID zůstává mimo Git.

## Doporučené zadání konkrétního manifestu

1. **D1:** vlastní členskou historii začít dnem skutečného provozního přechodu.
   Zpětné předpisy ani minulou docházku nevytvářet. Datum dosud není uzavřeno.
2. **D2:** zachovat identitu současných kolektivů a vazby, odstranit roční suffix
   z trvalého názvu, neslučovat podle podobnosti názvů. Zaměření zadat výslovně.
   Závodní oddíly jsou dnes také navázané na `school_year`; toto pole samo
   nedokazuje, že jde o kroužek. Tři testovací kolektivy vypořádat samostatně.
3. **D3:** doporučeno převzít 435 aktivních importovaných intervalů jako výchozí
   členství a připravit odstranění 560 ukončených importovaných intervalů
   s jejich přesně určenými importními vazbami. Vlastník musí upřesnit, zda
   požadavkem „stará data nepotřebujeme“ myslí historii, nebo i toto aktivní
   výchozí členství. Volba prázdného začátku je jiná datová operace; dnešní nástroj
   ji úmyslně nepředstírá jako smazání expirované historie.
4. Všech 8 objednávek a 8 plateb ponechat beze změny. Nulové počty nároků při
   inventuře nejsou povolení zrušit případný novější nárok.
5. Rozsah manifestu nastavit na `application_backup`. Cizí tabulky, BAR,
   výsledkové agendy a archivní tabulky se nemění ani nezamykají. Nová FK přes
   tuto hranici převod odmítne. Aplikační finanční tabulky zůstávají v otisku.

## Co je již ověřeno a co ještě zbývá

- Aplikační implementace je sloučená v PR #279, navazující nácvik v #280 a oprava
  katalogových identifikátorů v #281. Poslední úplná sada: 1 936 testů / 38 191
  assertions, všech 11 kontrol PASS na headu #281.
- [Technický nácvik skutečné zálohy](https://github.com/KovoPraha/evidenceTreninku/actions/runs/41500616687)
  převedl 44 soupisek, zachoval data a opakování bylo idempotentní. Následná
  obnova vrátila shodné otisky 337 tabulek a 2 triggery. Nic se nemazalo.
- Před ostrým přechodem zbývá uzavřít výše uvedený výchozí stav, vytvořit
  **konkrétní manifest ID včetně cleanupu**, ověřit jeho závislosti a nácvik,
  obnovu příloh a přihlášené UAT. Zatím neexistuje schválený otisk tohoto manifestu.
- E8, tedy destruktivní odstranění starého schématu a backendů, navazuje až na
  ověřený E7 a uzavřený bod návratu. Samotné sloučení kódu tuto podmínku nenaplňuje.

Provozní vydání určuje [CURRENT_STATE](CURRENT_STATE.md). Tato práce dosud
nespustila deploy, nezměnila živá data ani nezapnula importy či rozesílky.
