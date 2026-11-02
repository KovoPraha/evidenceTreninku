# Podklady pro provozní přechod evidence členů

Aktualizováno 3. 11. 2026 (Europe/Prague), po potvrzeném produkčním převodu. Navazuje na [pracovní plán](MEMBER_EVIDENCE_WORK_PLAN_2026-11-02.md)
a [implementační protokol](MEMBER_EVIDENCE_IMPLEMENTATION_2026-11-02.md).
Vlastník následně v tomto chatu **potvrdil převzetí 435 aktivních zařazení,
odstranění 560 historických a počáteční den 2. 11. 2026**. Po tomto dni proběhne
ještě jedna synchronizace. Datum je pevné i při pozdějším technickém přechodu.
Schválený převod proběhl v běhu 41542954307; [protokol a neosobní důkazy](verification/member-evidence-cutover-20261102/RESULTS.md). Níže uvedená původní inventura je historický podklad.

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

## Rozhodnutý rozsah a konkrétní manifest

1. **D1 uzavřeno:** vlastní členskou historii začít **2. 11. 2026** (Europe/Prague).
   Převzaté intervaly mají tento začátek, ID osoby i členství zůstává. Zdrojový
   import je doložen auditem a `source_ref=kis-opening:ID`; další běžné změny
   spravuje registrář. Zpětné předpisy ani minulou docházku nevytvářet.
2. **D2 uzavřeno:** vlastník schválil [úplnou mapu](MEMBER_EVIDENCE_APPROVED_MAP_2026-11-02.md).
   Sedm provozních skupin / 41 soupisek a osmá skupina pro tři testovací kolektivy.
   Rexonix je závodní oddíl; KDC znamená kurz dráhové cyklistiky a společně s Bikrosem
   tvoří dvě samostatné placené skupiny typu `other`, mimo Rexonix. Žádné členské
   záznamy se neslučují. Názvy a vazby jsou výslovné, dosavadní správci a rozsahy zůstávají.
3. **Výchozí členství potvrzeno:** převzít 435 aktivních importovaných intervalů
   a odstranit 560 ukončených intervalů včetně jejich importních událostí.
   Nástroj odmítne jiný počet nebo změněná data. Převodní vazby na odstraněné
   historické intervaly odpojí přesně určeným `member_id=NULL`; vazby na osoby
   a současné členství zůstanou pro poslední synchronizaci. Technické převodní
   tabulky se definitivně odstraní až v E8, po této synchronizaci.
4. Všech 8 objednávek a 8 plateb ponechat beze změny. Nulové počty nároků při
   inventuře nejsou povolení zrušit případný novější nárok.
5. Rozsah manifestu nastavit na `application_backup`. Cizí tabulky, BAR,
   výsledkové agendy a archivní tabulky se nemění ani nezamykají. Nová FK přes
   tuto hranici převod odmítne. Aplikační finanční tabulky zůstávají v otisku.

## Jedna poslední synchronizace

- Staré importy a sezonní rozhodovací cesty zůstávají po převodu zablokované.
  Poslední synchronizace je samostatná, jednorázová operace nad konkrétním novým
  exportem; neotevírá obecný import historie.
- `bin/member-evidence-cutover.php --sync-preview=delta.json --source=export --output=manifest.json`
  ověří skutečný otisk exportu a sestaví náhled. Delta obsahuje `source_sha256`,
  `reason` a výslovné `operations`: `add` s existující osobou, trvalou soupiskou
  a `valid_from/valid_to`, nebo `end` s ID převzatého intervalu a posledním dnem.
  Chybějící řádek v exportu sám nezpůsobí mazání; neznámou identitu musí nejprve
  vyřešit registrář. Automatický parser budoucího neznámého formátu se nepředpokládá.
- Datum nové platnosti nesmí být před 2. 11. 2026. Existující nárok nesmí vzniknout
  podruhé. Placené a novější vlastní nároky se neukončují tímto importem.
- `--sync-apply=manifest.json --source=export` používá stejné parametry otisku,
  správce, zálohy a pozastavených zápisů jako běžný apply. Přepočte data a práva
  v transakci. Jednu dávku lze bezpečně opakovat; jiná druhá dávka se odmítne.
- Provedení eviduje `member_roster_operations`; počáteční den v původním ledgeru
  se neposouvá. Dokud nový export není předán a zpracován, poslední synchronizace
  zůstává čekající. Nesmí být zaměněna za již provedenou synchronizaci.

## Co je již ověřeno a co ještě zbývá

- [PR #285](https://github.com/KovoPraha/evidenceTreninku/pull/285) implementuje schválenou mapu skupin,
  kontrolu původní identity a auditované dohledání přejmenované soupisky. Všech 11 kontrol PASS,
  Linux 1953 testů / 38293 assertions. [Nácvik přesné mapy 41532405191](https://github.com/KovoPraha/evidenceTreninku/actions/runs/41532405191)
  prošel: 8 skupin, 44 hran, 435 převzatých intervalů, 560 odstraněných historických intervalů
  a 560 událostí, shodné skupinové členství, zachovaný pilot a úplná obnova DB.
  Úplné doklady jsou v [protokolu](verification/member-evidence-20261102/RESULTS.md).

- Aplikační implementace je sloučená v PR #279, navazující nácvik a omezení
  rozsahu v #280–#282. [PR #283](https://github.com/KovoPraha/evidenceTreninku/pull/283)
  přidává schválený počáteční stav a jednu poslední synchronizaci; merge
  `1d0a562194aefa66711dd6da3b0558babf68d33e`. Poslední úplná sada: **1 947 testů / 38 243
  assertions**, všech 11 kontrol PASS na headu #283.
- [Technický nácvik skutečné zálohy](https://github.com/KovoPraha/evidenceTreninku/actions/runs/41500616687)
  převedl 44 soupisek, zachoval data a opakování bylo idempotentní. Následná
  obnova vrátila shodné otisky 337 tabulek a 2 triggery. Nic se nemazalo.
- Cílová mapa D2 i konkrétní manifest cleanupu byly aplikovány: 8 skupin / 44 hran,
  435 počátečních zařazení a 560 odstraněných historických intervalů i událostí.
  Technická identita převodu má odebraná oprávnění, provoz byl obnoven.
  Zbývá přihlášené UAT, vypořádání chybějících příloh a poslední synchronizace.
- [Rozšířený nácvik 41512931635](https://github.com/KovoPraha/evidenceTreninku/actions/runs/41512931635)
  ověřil také konkrétní rozsah úklidu: převzal 435 intervalů od 2. 11., odstranil
  560 historických intervalů a 560 importních událostí, odpojil 560 technických vazeb.
  Parita chráněných dat, opakování a obnova 337 tabulek / 2 triggerů PASS.
  Cílové názvy/zaměření D2 stále nahrazovaly technické kódy pro nácvik; živá DB se neměnila.
- E8, tedy destruktivní odstranění starého schématu a backendů, navazuje až na
  ověřený E7 a uzavřený bod návratu. Samotné sloučení kódu tuto podmínku nenaplňuje.

Provozní vydání určuje [CURRENT_STATE](CURRENT_STATE.md). Datový převod a schválený cleanup jsou potvrzené; poslední synchronizace, E8 a úplná přejímka nikoli. Běh převodu selhal až na HTTP timeoutu runneru po commitu. Následné nezávislé HTTP 40/40 a inventura PASS; čerstvá záloha čeká na samostatnou izolovanou obnovu. Importy a rozesílky se nezapnuly.
