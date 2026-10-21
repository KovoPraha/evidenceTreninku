# Názvy registračních souhlasů — 21. 10. 2026

Editor registračních textů nově umožňuje měnit také název dokumentu. V pozici
**Registrář členů a KIS → Členové → Registrační texty a souhlasy** je nad textem
pole **Název souhlasu / informace**. Název má nejvýše 200 znaků na jednom řádku.

Změna samotného názvu stačí k vytvoření nové verze. Uživatel vyplní důvod,
uloží koncept, zkontroluje název i text v náhledu a samostatně zveřejní novou
verzi. Koncept nemění veřejný název. Po zveřejnění se nový název objeví v
nabídce editoru, na registrační stránce sportovce i v rychlé přihlášce.
Historie zobrazuje název příslušné verze, nikoli pouze aktuální název.

## Uložení a kompatibilita

- Nová neměnná migrace `20261021170000_registration_text_titles` přidává
  `title_plain` k verzím dokumentů a uloženým konceptům. Původní migrace se nemění.
- Dosavadním verzím a konceptům doplní dosud používané názvy. Nezmění tělo
  dokumentu, identifikátor verze ani dřívější potvrzení. Opakování nepřepíše
  vlastní název, který už registrář uložil.
- Publikace ukládá název ze stejného neměnného konceptu jako text. Původní
  záznamy ve verzovém registru se nepřepisují; dřívější přihláškové snapshoty
  zůstávají přes `term_version_id` navázané na původní dokument i jeho název.
- Veřejný čtenář má původní názvy jako fallback i pro starší izolované databáze.
  Název se ve všech HTML výstupech escapuje jako prostý text.
- Nové sloupce zahrnuje zálohovací ownership kontrakt `2026-10-21.2`.

## Ověření

- Lokálně **1 158 testů / 26 107 kontrol**, zelené. Deset integračních scénářů
  editoru má 109 kontrol: přibylo přejmenování bez změny těla, původní název
  u staré potvrzené verze, opakování migrace, validace a zapojení veřejných nadpisů.
- MariaDB 10.4.32: souběžné publikace, zachování starého názvu a publikace pouze
  názvu prošly. Stejný smoke běží v povinné CI matici 10.3 a 11.4.
- Skutečná záloha a obnova izolované databáze prošla pro **186 tabulek**, včetně
  nových sloupců kontraktu. Lokální migrační katalog **108**, žádné čekající kroky.
- Prohlížeč nad samostatnou syntetickou databází: změna pouze názvu informace
  o evidenci člena, uložení konceptu, nezměněný veřejný název před zveřejněním,
  samostatná publikace a nový název v registrační stránce sportovce. Historie
  současně zobrazuje nový i původní název. Žádná žádost o sportovce se neodesílala.
- Composer validate, PHP lint a `git diff --check` prošly.

Starší migrace `20260922190000_member_fee_standing_orders` při čistém lokálním
bootstrapu a zálohovacím smoke nadále vypisuje existující varování o klíči
`columns`; úspěšnému dokončení nebrání a nebyla měněna. První pokus o start
dřívějšího lokálního datového adresáře neuspěl, test proto použil novou
samostatnou MariaDB na portu 33110. Ostatní lokální databázové procesy se neměnily.

## Produkční stav

Nasazení čeká na povinné PR kontroly a chráněný produkční workflow.
