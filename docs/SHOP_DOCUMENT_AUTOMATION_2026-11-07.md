# Doklady a komunikace jednorázových objednávek

Stav: ROZPRACOVÁNO, 7. 11. 2026. Vlastník výslovně zadal dokončení N1/N2,
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

Další krok: finální CI, sloučení, chráněné nasazení a produkční aktivace.
Stav vydání bude zapsán
s konkrétním SHA a během. Skutečná bankovní transakce a doručení do schránky
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
