# Správa registračních textů a souhlasů — 21. 10. 2026

## Použití

1. Přepněte pracovní pozici na **Registrář členů a KIS**.
2. V nabídce **Členové** otevřete **Registrační texty a souhlasy**
   (`registration_texts_admin.php`). Odkaz je i na pracovním rozcestníku.
3. Vyberte informace o evidenci člena, informace k rodnému číslu, interní nebo
   veřejné fotografie, případně volitelné předání rodného čísla ČSC.
4. Upravte text a vyplňte důvod změny. **Uložit koncept a zobrazit náhled**
   vytvoří samostatný koncept; dosavadní veřejné znění se tím nemění.
5. Zkontrolujte uložený náhled. Zaškrtněte potvrzení schválení přesného znění
   klubem a použijte samostatné tlačítko **Zveřejnit toto znění**.
6. V historii lze rozbalit celé znění každé zveřejněné verze. U nových verzí
   je uložen autor konceptu, publikující pracovník, čas a důvod změny.

Nová verze platí pro nové přihlášky a rychlé přihlášení do kroužku. Dřívější
potvrzení a jejich textové snapshoty zůstávají zachované. Rozpracovaná přihláška
se starou verzí musí potvrdit aktuální podmínky podle stávající registrační logiky.
Samotný tento release nemění obsah schválených veřejných textů.

## Implementační hranice

- Přístup řídí centrální vlastnictví trasy a aktivní pracovní pozice registráře.
  Superadmin musí rovněž přepnout do této pozice. POST vyžaduje CSRF token.
- Každé uložení vytvoří nový neměnný koncept v `athlete_registration_text_drafts`.
  Koncepty se neupravují ani nemažou; případná oprava se uloží jako další koncept.
- Zveřejnění pracuje se zněním uloženého konceptu. Transakce zamyká koncept
  a jeho výchozí verzi, kontroluje aktuální veřejnou verzi a až potom přidá nový
  řádek do kanonického `club_event_term_versions`. Opakování stejné publikace
  je idempotentní. Konkurenční nebo zastaralý koncept nepřepíše novější text.
- Původní registry a přihláškové snapshoty se nepřepisují. Historie zahrnuje
  také verze vytvořené před zavedením editoru. Všechny texty se zobrazují jako
  escapovaný prostý text. Vzorové/neúplné znění je při publikaci odmítnuto.
- Migrace `20261021150000_athlete_registration_text_drafts` vytváří jednu novou
  tabulku. Je zahrnuta v zálohovacím kontraktu `2026-10-21.1`.

## Lokální ověření

- PHPUnit: **1 147 testů / 25 871 kontrol**, zelené; sedm nových integračních
  scénářů pokrývá role, koncept, publikaci, historii, starý přihláškový snapshot,
  idempotenci, konflikt dvou konceptů, validaci a transakční rollback.
- MariaDB 10.4.32: migrace, dvě souběžné publikační operace a idempotentní
  opakování prošly. Tentýž smoke je součástí povinné CI matice 10.3 a 11.4.
- Skutečná záloha a obnova izolované databáze: **186 tabulek**, úspěch.
- Samostatná localhost instalace: migrační katalog **107**, žádné čekající kroky.
- Prohlížeč: přepnutí na registráře, viditelný odkaz v běžném menu, načtení
  aktuálních textů, uložení syntetického konceptu, samostatné zveřejnění a
  rozbalení předchozího znění v historii prošly. Testovací obsah existuje pouze
  v oddělené syntetické localhost databázi.
- Composer validate, PHP lint změněných souborů a `git diff --check` prošly.

Úplné UAT aplikace není tímto dílčím ověřením schváleno. Starší migrace
`20260922190000_member_fee_standing_orders` při čisté lokální instalaci a
zálohovacím smoke vypisuje existující varování o klíči `columns`; oba běhy se
dokončily úspěšně. Historická migrace nebyla měněna.

## Produkční stav

Nasazení čeká na povinné PR kontroly a chráněný produkční workflow.
