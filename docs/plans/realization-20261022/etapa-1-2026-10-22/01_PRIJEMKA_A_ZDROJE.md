# Etapa 1 — přejímací scénáře a zdrojové podklady

> Historický návrhový a přejímací podklad z 22. 10. 2026, sdílený v Gitu od 7. 11.
> Původní formulace „plánováno“ a „neimplementováno“ níže popisují datum vzniku.
> Technické výsledky R01–R44 a provozní meze ověřujte v [současném přehledu](../README.md).
> Tento přenos nepovoluje nové produkční operace a není novým datovým auditem.


Příloha k [plánu etapy 1](00_ETAPA_1_PLAN.md), 22. 10. 2026. Scénáře jsou **zadání budoucího ověření**, nikoli tvrzení, že byly při přípravě provedeny.

## Přejímací scénáře

Všechny zápisové scénáře patří do izolovaného prostředí se syntetickými daty. Produkční ověření se plánuje samostatně a přiměřeně jeho autorizaci. Protokol: ID scénáře, revize, prostředí, datum, zkoušející, očekávaný/skutečný výsledek, důkaz a případná závada.

| ID | Úkol | Scénář | Očekávaný výsledek |
|---|---|---|---|
| T01 | E1-01 | Kontrola prostředí před první přihláškou | Jiná databáze a úložiště; syntetické kontakty; vypnuté ostré transporty, platby a importy. |
| T02 | E1-02 | Dvě přihlášky téže osoby; dvě různé osoby stejného jména | První případ nabídne prověření možné duplicity; druhý nesloučí bez důkazu. Rozhodnutí je auditované. |
| T03 | E1-02 | Osoba bez narození a se sdíleným kontaktem | Systém si datum nevymyslí a nesdělí soukromé údaje na základě pouhé shody e-mailu. |
| T04 | E1-03 | Zákazník koupí oblečení bez členství | Nákup nevytvoří nechtěné členství, sportovní zařazení ani oprávnění k dítěti. |
| T05 | E1-03 | Člen ukončí sezonní zařazení s existujícím dluhem a historií | Historie a dluh zůstanou; aktuální účast a práva se řídí explicitními pravidly. |
| T06 | E1-04 | Nový rodič, dvě děti a druhý rodič | Každá osoba a každý vztah má správný stav; druhý rodič získá pouze výslovně schválené vazby. |
| T07 | E1-04/07 | Ověření/reset, opětovné poslání, starý token a expirovaný token | Nový platný postup funguje; staré či spotřebované tokeny neobnoví přístup; zprávy mají dohledatelný výsledek. |
| T08 | E1-04/07 | Odebrání rodičovského vztahu při otevřeném portálu a připravené zprávě | Další požadavek odmítne přístup k dítěti; čekající zpráva neprozradí nově nepovolený obsah. Vlastní objednávky se posuzují odděleně. |
| T09 | E1-04/06 | Přihláška vyřízená osobně bez rodičovského účtu | Obsluha eviduje skutečnou žádost a důkaz; nevyrábí online potvrzení ani ověřený účet. |
| T10 | E1-05 | Trenér požádá přímým odkazem o cizí skupinu, export či soukromou přílohu | Server přístup odmítne i mimo menu. Právo se kontroluje pro konkrétní objekt a akci. |
| T11 | E1-05 | Odebrání pracovní pozice / deaktivace během otevřené relace | Další pracovní požadavek neprojde; zůstane historie změny. Případná legitimní rodičovská práva se řídí samostatně. |
| T12 | E1-06 | Nová verze textu a oprava záznamu papírového podpisu | Původní znění a datum zůstanou dohledatelné. Neznámá verze zůstane označená jako neověřená. |
| T13 | E1-06 | Přímé stažení soukromého souboru neoprávněným účtem | Soubor není veřejně dostupný; oprávněné zobrazení používá určený postup a potřebný audit. |
| T14 | E1-07 | Rezervace a čekací listina při chybě e-mailového transportu | Rezervace má konzistentní stav a zpráva dohledatelnou frontovou položku; lze řízeně opakovat. |
| T15 | E1-07/08 | Dvojí zpracování události a přerušení pracovní dávky | Nevznikne druhý obchodní úkon ani duplicitní položka stejné události; převzaté položky lze bezpečně dořešit. Nejasné odeslání je rozlišeno. |
| T16 | E1-08 | Zastavený plánovač oproti záměrně vypnuté úloze | První stav vyvolá provozní problém po určené toleranci, druhý se nezobrazuje jako falešná porucha. |
| T17 | E1-09 | Obnova databáze, příloh a potřebné konfigurace do izolovaného prostředí | Sedí kontrolní počty a soubory, funguje dešifrování i práva, měří se čas. Obnovená kopie nic neposílá ven. |
| T18 | E1-09 | Výpadek během osobního prodeje/přihlášky a následný návrat | Číslované náhradní záznamy mají vlastníka a jednorázové doplnění s kontrolou duplicit. Finanční úplnost dále ověří příslušná etapa. |
| T19 | E1-10 | Nejasná identita nebo neúspěšná zpráva zůstane přes předání služby | Náhradník najde vlastníka, důvod, další krok a historii. Uzavření případu neodstraní důkaz. |
| T20 | E1-11 | Rodina na telefonu hledá termín, čekající schválení a platbu | Je jasná osoba i další krok; finanční významy nejsou smíchané a chybějící migrace je přiznaná. |
| T21 | E1-12 | Registrář a uživatelský garant zopakují cestu bez pomoci vývojáře | Postup je použitelný, podklady jsou dostupné a závady mají konkrétního vlastníka. |

## Výstupní brány

1. **Připraveno k implementaci konkrétního balíku:** dohodnutá pravidla a rozsah, známé existující řešení, konkrétní akceptace a izolované prostředí. Tento dokument je podklad, nikoli oprávnění začít implementovat.
2. **Převzato v testu:** povinné scénáře na dané revizi prošly; nevyřešené P0 nesmí být překryté obecným „testy zelené“.
3. **Připraveno na pilot:** vybraní skuteční účastníci mají vyřešené identity a práva; obsluha je určena; potřebné doručování a plánovače mají provozní důkaz nebo přijatý ruční postup; existuje protokol obnovy.
4. **Převzato v provozu:** teprve po samostatně schváleném nasazení/pilotu ověřit cílovou revizi a skutečné uživatelské postupy. Tato brána nyní nebyla provedena. Neznamená automaticky připravenost vypnout Shoptet či starou Evidenci.

## Zdrojové podklady

Odkazy míří na konkrétní kontrolovanou revizi, aby je pozdější změny `main` neposunuly. Popis existence funkce se opírá o zdroj, nikoli o pouhý název testu. Uvedené testy nebyly v tomto dokumentačním úkolu znovu spouštěny.

| Značka | Podklad | Co dokládá / omezení |
|---|---|---|
| Z1 | [CLAUDE.md](https://github.com/KovoPraha/evidenceTreninku/blob/68b22bd2b3b666dbb4fb571a2731eae5b7f056d9/CLAUDE.md) | Společná aplikace, kanonické osoby a hranice produkčních aktivací. Datované provozní údaje je nutné ověřovat samostatně. |
| Z2 | [Vazby účtů a osob](https://github.com/KovoPraha/evidenceTreninku/blob/68b22bd2b3b666dbb4fb571a2731eae5b7f056d9/includes/account_person_role.php#L60), [editor osoby](https://github.com/KovoPraha/evidenceTreninku/blob/68b22bd2b3b666dbb4fb571a2731eae5b7f056d9/includes/sportovec_editor.php#L34), [úplný import osob](https://github.com/KovoPraha/evidenceTreninku/blob/68b22bd2b3b666dbb4fb571a2731eae5b7f056d9/docs/kis-full-person-import.md) | Schvalování/odvolání vztahů, editace a původ dat. Úplný import není obecné bezpečné doplnění historie. |
| Z3 | [Správa registrací](https://github.com/KovoPraha/evidenceTreninku/blob/68b22bd2b3b666dbb4fb571a2731eae5b7f056d9/includes/athlete_registration_admin.php#L75), [žádosti o propojení](https://github.com/KovoPraha/evidenceTreninku/blob/68b22bd2b3b666dbb4fb571a2731eae5b7f056d9/includes/account_person_claim.php) | Kandidáti shody, spojení se stávající osobou, vytvoření osoby, kontrola schvalovatelnosti. |
| Z4 | [Úrovně zapojení](https://github.com/KovoPraha/evidenceTreninku/blob/68b22bd2b3b666dbb4fb571a2731eae5b7f056d9/includes/member_engagement.php#L17) | Odvozené zapojení podle dat. Neprokazuje samo sjednocení všech členských stavů. |
| Z5 | [Rodinný portál](https://github.com/KovoPraha/evidenceTreninku/blob/68b22bd2b3b666dbb4fb571a2731eae5b7f056d9/includes/family_portal.php#L35) | Kontrola aktivního ověřeného účtu a platných vztahů; přehled soupisek, událostí, tréninků a předpisů. |
| Z6 | [Session](https://github.com/KovoPraha/evidenceTreninku/blob/68b22bd2b3b666dbb4fb571a2731eae5b7f056d9/includes/auth_session.php), [jednorázové tokeny](https://github.com/KovoPraha/evidenceTreninku/blob/68b22bd2b3b666dbb4fb571a2731eae5b7f056d9/docs/auth-one-time-tokens.md), [fronta autentizačních zpráv](https://github.com/KovoPraha/evidenceTreninku/blob/68b22bd2b3b666dbb4fb571a2731eae5b7f056d9/includes/auth_mail_outbox.php), [oznámení rodičům](https://github.com/KovoPraha/evidenceTreninku/blob/68b22bd2b3b666dbb4fb571a2731eae5b7f056d9/includes/parent_journey_notification.php) | Existující ochrany a notifikace; samotný kód neprokazuje produkční doručení. |
| Z7 | [Pracovní pozice](https://github.com/KovoPraha/evidenceTreninku/blob/68b22bd2b3b666dbb4fb571a2731eae5b7f056d9/includes/staff_workspaces.php), [životní cyklus účtu](https://github.com/KovoPraha/evidenceTreninku/blob/68b22bd2b3b666dbb4fb571a2731eae5b7f056d9/includes/staff_account_lifecycle.php#L9) | Pracovní kontexty; auditovaná deaktivace již implementována a mění verzi session. |
| Z8 | [Vlastní souhlasy](https://github.com/KovoPraha/evidenceTreninku/blob/68b22bd2b3b666dbb4fb571a2731eae5b7f056d9/docs/CUSTOM_CLUB_CONSENTS_2026-10-21.md), [citlivé údaje](https://github.com/KovoPraha/evidenceTreninku/blob/68b22bd2b3b666dbb4fb571a2731eae5b7f056d9/includes/person_sensitive.php#L243) | Verze a odpovědi pro kroužky; ochrana, audit, výmaz a rotace citlivých údajů. Neprokazuje kompletní papírovou ani zdravotní agendu. |
| Z9 | [Diagnostika a zpracování zpráv](https://github.com/KovoPraha/evidenceTreninku/blob/68b22bd2b3b666dbb4fb571a2731eae5b7f056d9/includes/transactional_notification_worker.php#L21), [přímé e-maily rezervací](https://github.com/KovoPraha/evidenceTreninku/blob/68b22bd2b3b666dbb4fb571a2731eae5b7f056d9/booking/rezervovat.php#L213), [čekací listina](https://github.com/KovoPraha/evidenceTreninku/blob/68b22bd2b3b666dbb4fb571a2731eae5b7f056d9/booking/waiting_list.php#L120), [týdenní souhrny](https://github.com/KovoPraha/evidenceTreninku/blob/68b22bd2b3b666dbb4fb571a2731eae5b7f056d9/docs/family-weekly-summaries.md) | Přímé `@mail` jsou konkrétní zbývající mezera; připravená fronta neprokazuje aktivní plánovač či doručení. |
| Z10 | [Provozní přehled](https://github.com/KovoPraha/evidenceTreninku/blob/68b22bd2b3b666dbb4fb571a2731eae5b7f056d9/includes/admin_operational_overview.php#L64), [auditní osa](https://github.com/KovoPraha/evidenceTreninku/blob/68b22bd2b3b666dbb4fb571a2731eae5b7f056d9/includes/person_audit_timeline.php), [workflow](https://github.com/KovoPraha/evidenceTreninku/tree/68b22bd2b3b666dbb4fb571a2731eae5b7f056d9/.github/workflows) | Přehled problémů a některé definice plánů již existují. Skutečné hostované CRONy a konfigurace nejsou z tohoto seznamu plně zjistitelné. |
| Z11 | [Databázová záloha](https://github.com/KovoPraha/evidenceTreninku/blob/68b22bd2b3b666dbb4fb571a2731eae5b7f056d9/bin/db-backup.php), [zkouška obnovy MariaDB](https://github.com/KovoPraha/evidenceTreninku/blob/68b22bd2b3b666dbb4fb571a2731eae5b7f056d9/tests/Support/DatabaseBackupMariaDbSmoke.php) | Manifest, tabulkový kontrakt a test skutečné obnovy DB. Neprokazuje úplnou provozní obnovu souborů a klíčů. |
| Z12 | [Integrační testy](https://github.com/KovoPraha/evidenceTreninku/tree/68b22bd2b3b666dbb4fb571a2731eae5b7f056d9/tests/Integration) | Existují mj. `AccountPersonRoleTest`, `AccountPersonClaimTest`, `AuthSessionValidationTest`, `FamilyPortalTest`, `StaffAccountLifecycleTest`, `PersonAuditTimelineTest`, `TransactionalNotificationWorkerTest`, `CustomClubConsentsTest`. Přítomnost testu není novým výsledkem jeho běhu. |

## Omezení této přípravy

- Aktuální zdrojová revize byla ověřena po načtení vzdálených referencí. Nebyla provedena změna vzdáleného repozitáře ani produkce.
- Živá měření osob a historie pocházejí z [porovnání v etapě 0](../etapa-0-2026-10-22/03_EVIDENCE_VS_KIS.md); tento dokument není nové úplné porovnání databází.
- Existence či nepřítomnost konkrétního workflow není vydávána za globální důkaz stavu celé aplikace. U neprokázaných oblastí plán výslovně požaduje inventuru nebo ověření.
- Při tomto úkolu vznikla pouze tato místní dokumentace a plán. Nebyla vytvořena testovací ani skutečná objednávka, poslán e-mail, změněna role ani spuštěna obchodní automatizace.
