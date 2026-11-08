# Doklady a komunikace jednorázových objednávek

Stav: HOTOVO pro implementaci, nasazení a aktivaci, 7. 11. 2026. Vlastník výslovně zadal dokončení N1/N2,
ověření celého toku, dokumentaci, sloučení a nasazení.
Navazuje [kontrola nákupu](verification/one-time-course-uat-20261107/RESULTS.md)
a [P07](WORK_PLAN_CURRENT.md). Větev/worktree `codex/one-time-course-uat-20261107`
/ `C:/Users/Marek/Documents/Codex/evidencePavel-one-time-course-uat-20261107`, PR #355.

## Plán

1. Transakční zprávy při vytvoření, stornu a potvrzené vratce; zachovat stávající
   potvrzení úhrady a rychlé/hostovské přihlášky bez duplicit.
2. Účetní doklad po potvrzené úhradě, ve workeru přes existující účetní služby;
   neměnné doklady, bezpečné stažení vlastníkem objednávky a doručení zprávy.
3. Fakturační údaje zadané zákazníkem, schválený vystavovatel a oddělená řada;
   opravný doklad při stornu, samostatná skutečná vratka, žádné vymyšlené údaje.
4. Testy identity, idempotence, výpadku PDF/pošty a celé služby; místní browser,
   dokumentace, povinné CI, merge a chráněný deploy s následnou kontrolou.

Měsíční členství, skutečné bankovní příjmy a cizí WIP zachovat. Historické
objednávky se nesmějí hromadně rozeslat jen instalací nové verze.
## Průběh 7. 11. 2026

- Implementováno v pracovní větvi: verzované pověření ve Fakturačních údajích klubu,
  fakturační údaje účtového/hostovského/rychlého checkoutu, fronta původního a
  opravného PDF, přílohy v e-mailu, přístup z autorizovaného detailu objednávky.
- Zdroj dokladu se zmrazí při objednání; CLI worker jej smí použít pouze u
  registrované a prokazatelně zaplacené objednávky. Zachytí i storno před během
  workeru. Platba není závislá na vykreslení PDF. Ruční konfliktní doklady
  vyžadují kontrolu hospodáře místo přepsání.
- Zapojeno do stávajícího minutového staff workeru; zpracuje pouze nové
  registrované objednávky. Nejasné odeslání se drží k ručnímu prověření.
- První cílené ověření: 8 testů / 158 assertions PASS; regresní výběr
  ShopSystemEmail, ShopCheckout, ParentJourney, AccountingDocuments a
  TransactionalNotificationWorker: 71 testů / 1115 assertions PASS.
- Migrace aplikována na vlastní syntetické MariaDB `evidence_b1`, port 33427;
  lokální UI uložilo syntetického vystavovatele a pověření č. 1. Žádná
  konfigurace produkce v tomto kroku změněna nebyla.

- Rozšířený výběr: 51 testů / 496 assertions PASS. Místní browser a MariaDB
  prošly celým tokem vytvoření, úhrady, PDF, storna a potvrzené vratky;
  šest unikátních zpráv, dva doklady, opakovaný worker bez duplicit.

Implementace, CI, sloučení, nasazení a aktivace jsou dokončené;
přesné důkazy jsou ve výsledkovém protokolu níže. Skutečná bankovní transakce a doručení do schránky
se nesmějí odvozovat z lokálního outboxu nebo úspěšného transportu.

## Ovládání a provoz

1. Hospodář a platby → Fakturační údaje klubu → **Doklady a e-maily
   jednorázových objednávek**. Vybrat schválenou verzi vystavovatele, zapnout
   nové objednávky, uvést podklad a potvrdit. Konfigurace neplátce DPH
   schvaluje členění ESHOP / PRODEJ a chybějící řady FA/OPR pro dva roky;
   již používané řady ani jejich čítače se nepřepisují.
2. Nové účtové, hostovské a rychlé nákupy vyžádají fakturační adresu.
   Host a rychlá přihláška zachovají svůj původní e-mail s bezpečným odkazem.
   Pravidelné členství pokračuje vlastním dosavadním tokem.
3. Minutový `bin/staff-notification-operations.php` zpracuje také nově
   registrované objednávky. Workflow `staff-notifications-production.yml`
   má `status` pro čtecí kontrolu a `process` pro řízené zpracování.
   `shop` obsahuje pověření, počty dokumentů, chyby a frontu e-mailů.
4. PDF selhání se opakuje po pěti minutách se stejným dokladem/číslem.
   Chyby jsou vidět ve Fakturačních údajích klubu. Neaktivní schvalující,
   odňaté finanční oprávnění nebo konflikt ručního dokladu vyžadují hospodáře.
   Přerušované či nejasné doručení má `delivery_unknown` a neopakuje se
   automaticky; nejprve prověřit transport a použít dosavadní správu fronty.
5. Vypnutí pověření zastaví registraci dalších nákupů; rozpracované
   registrované objednávky se dál dokončí. Pro jejich úplné pozastavení
   je potřeba řízeně zastavit hostingový worker, který sdílí i pracovní upozornění.

Úplné storno vystaví opravný doklad, ale neprovádí platbu z banky. Dílčí
vratky zůstávají rozhodnutím hospodáře v Opravách a částečných vratkách;
každá skutečná výplata má vlastní e-mail. Ruční částečné opravné doklady
se nepřepisují automatickým úplným stornem. Operátor tak musí dořešit případ,
kde už existuje jiná oprava. Opravné PDF není potvrzení výplaty.

[Konkrétní důkazy a meze přejímky](verification/shop-document-automation-20261107/RESULTS.md).

## Provozní aktivace bez funkčního ovládání prohlížeče

Computer Use 7. 11. odmítlo pokračovat, protože ve Windows nedokázalo spolehlivě
ověřit aktuální URL. Produkční browser UAT tohoto vydání proto není potvrzené.
Náhradou pro schválenou aktivaci je chráněné workflow
`staff-notifications-production.yml`, nejprve `status`, poté `shop_enable`.
Operátor předá přesné `issuer_id` ze statusu a ověřené `issuer_registration_id`;
`potvrzeni=ZAPNOUT-UPOZORNENI`. Operace ověří neměnnou verzi vystavovatele,
její IČ a stále aktivní finanční oprávnění původního schvalujícího. Nevytváří
přihlášenou session ani nepřiděluje roli. Audit obsahuje ID chráněného běhu.
Opakovaná aktivace téhož vystavovatele nevyrobí další pověření; jiného odmítne.
Potom `status` a přirozený minutový tick ověří stav workeru. Vlastník tuto
aktivaci zadal spolu s dokončením a nasazením; nejde o nový bankovní pohyb.

CI odhalilo chybějící vlastnictví nových tabulek pro zálohy; obě tabulky byly
zařazeny do `EVIDENCE_TABLES`. Opraveny byly i inicializační větve veřejných
stránek (příslušné staré výjimky PHPStan odstraněny) a formátově závislé
kontroly PHP výrazů. Věcné testované podmínky zůstaly zachované.

## Vydání a předání

[PR #355](https://github.com/KovoPraha/evidenceTreninku/pull/355) sloučeno
7. 11. jako `9460ee76`. [Finálních 11 kontrol](verification/shop-document-automation-20261107/ci-summary.json)
PASS, včetně obou MariaDB profilů na stejném schématu.
[Chráněné nasazení 42362258878](https://github.com/KovoPraha/evidenceTreninku/actions/runs/42362258878)
skončilo SUCCESS pro přesný sloučený commit, bez importu a bez příznaku úplného lidského UAT.
Aktivace 42362688999: vystavovatel 1 / IČ 00541753, pověření 1 zapnuté.
Přímé produkční browser UAT nedoložené; Computer Use odmítlo ověřit aktuální URL.
Výsledkové předání: větev `codex/shop-document-release-20261107`, původní vlastní worktree.

Závěrečný status 42362771928 potvrdil zapnuté pověření a přirozený minutový
hostingový běh v 10:13:03 UTC bez chyb i duplicit. [Produkční důkazy](verification/shop-document-automation-20261107/RESULTS.md).
Další krok pouze při skutečném nákupu: potvrdit bankovní úhradu podle běžných
pravidel párování a doložit doručení PDF ve schránce. Tato lidská/finanční přejímka
není nahrazena prázdným workerem. Není potřeba opakovat aktivaci nebo instalaci.


## P07-UAT — reálná produkční přejímka

Doplněno 8. 11. 2026 na výslovné zadání vlastníka. **OTEVŘENO / NOT RUN**;
skutečná finanční operace zůstává odložená vlastníkem. Technicky dokončené
N1/N2 nelze označit za úplně provozně převzaté bez následujících důkazů.
Zápis tohoto seznamu není provedením platby, storna, vratky ani nové rozesílky.

Vlastník přejímky: vlastník klubu / hospodář; zákazník nebo tester potvrzuje
skutečné doručení do své schránky, vývojář doloží audit a stav systému.
Další krok: určit konkrétní účet, produkt, částku, příjemce a termín reálného
průchodu s vlastníkem. Použít novou jednorázovou objednávku s fakturačními údaji;
pro sportovní produkt klubem ověřeného propojeného sportovce. Před průchodem
ověřit aktuální nasazenou verzi, pověření a stav workeru podle CURRENT_STATE.

| Krok | Podmínka přijetí / požadovaný důkaz | Stav |
| --- | --- | --- |
| Vytvoření objednávky | Přihlášený zákazník dokončí nákup, vidí správnou cenu, VS a objednávku; potvrzovací e-mail skutečně přijde do cílové schránky právě jednou. | NOT RUN |
| Skutečná úhrada | Bankovní převod s předepsaným VS, částkou a měnou je dohledán v importu a jednoznačně spojen s toutéž objednávkou. Doložit přechod do zaplaceno a případné aktivace účasti. Shoda částky sama nestačí. | NOT RUN |
| Doklad a e-maily po úhradě | Přirozený worker vystaví právě jednu fakturu; příjemce potvrdí přijetí potvrzení úhrady a zprávy s PDF. PDF lze otevřít a údaje, částka, číslování i vazba na objednávku souhlasí. | NOT RUN |
| Přístup v aplikaci | Zákazník v přihlášeném účtu vidí správný stav a stáhne vlastní PDF; cizí účet jej nezíská. Zaznamenat skutečný průchod v prohlížeči, nikoli pouze HTTP 200. | NOT RUN |
| Storno uhrazené objednávky | Hospodář provede sjednané storno; stav, případné zrušení účasti a opravný doklad odpovídají celé původní částce. Původní faktura zůstane neměnná a dostupná. Příjemce potvrdí oznámení storna i opravné PDF. | NOT RUN |
| Skutečná vratka | Po skutečné výplatě a jejím zaúčtování souhlasí částka, vazba na objednávku a konečný stav. Příjemce potvrdí vrácení peněz a doručení e-mailu; samotné rozhodnutí ani opravný doklad nejsou důkazem výplaty. | NOT RUN |
| Dílčí vratka | Na samostatném sjednaném případu ověřit oznámení skutečně vyplacené části a následného doplatku; součet a konečný stav souhlasí. Dílčí účetní opravu řídí hospodář. | NOT RUN |
| Neuhrazené storno | Samostatná nezaplacená objednávka po stornu odešle oznámení, nevytvoří fakturu ani opravný doklad k neexistující úhradě. | NOT RUN |
| Opakovaný běh | Po dalším přirozeném běhu/importu nevznikne druhá úhrada, doklad ani opakovaná zpráva pro stejnou událost; fronta nemá nevysvětlené chyby ani čekající položky. | NOT RUN |

Protokol uložit pod `docs/verification/` a propojit z tohoto oddílu a registru.
U každého kroku uvést PASS/FAIL/NOT RUN, datum a čas s pásmem, nasazené SHA,
bezpečnou referenci objednávky/platby/dokladu/události, očekávaný a skutečný stav,
kontrolující osobu/roli a odkaz na důkaz. Potvrzení doručení musí zahrnout příjem
ve schránce a otevření přílohy; stav `sent` nebo přijetí transportem nestačí.
Osobní faktury, bankovní výpisy, adresy, tokeny a úplné hlavičky zpráv ponechat
v řízeném soukromém úložišti, do Gitu jen anonymizovaný výsledek a referenci.

**Uzavření:** hospodář přijme věcnou správnost dokladů a peněžních pohybů,
zákazník potvrdí příjem zpráv/PDF a peněz, všechny uvedené scénáře mají doložený
PASS a žádnou nevysvětlenou duplicitu či chybu. Při omezeném rozsahu zapsat jen
částečnou přejímku; zbývající NOT RUN neuzavírat. Měsíční členství P05 a karetní
platba SumUp mají vlastní přejímku, bankovní test jednorázového nákupu je neuzavírá.

Dokumentační změna: větev `codex/shop-real-acceptance-docs-20261108`, worktree
`C:/Users/Marek/Documents/Codex/evidencePavel-shop-real-acceptance-20261108`,
výchozí main `da152282`. Ověření: `php tools/check-current-docs.php` PASS (0 chyb), `git diff --check` PASS; žádné nové
produkční UAT ani finanční operace nebyly v této dokumentační změně provedeny.

Předání: [PR #402](https://github.com/KovoPraha/evidenceTreninku/pull/402) otevřený;
commit a push hotové, merge zatím neproveden. Výsledek CI je dostupný v PR.
