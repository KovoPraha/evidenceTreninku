# Ověření a obnova databázové zálohy

Produkční obnova není součástí automatického deploye. Je to záměr: databáze je
sdílená s dalšími aplikacemi a SQL dump obsahuje `DROP TABLE` pouze pro explicitně
vlastněné tabulky Evidence. Chybný cíl importu by mohl zničit data.

## Bezpečný lokální restore drill

Tento postup ověří zálohu v nové prázdné lokální databázi. Nikdy pro něj
nepoužívejte produkční přihlašovací údaje.

1. Přes SFTP zkopírujte z `~/.evidence-backups/` jednu celou trojici
   `evidence_*.sql.gz`, `.sha256` a `.manifest.json` do neveřejné lokální složky.
2. Ověřte SHA256. Ve Windows PowerShellu:

   ```powershell
   Get-FileHash .\evidence_YYYY-MM-DD_HHMMSS_xxxxxxxx.sql.gz -Algorithm SHA256
   Get-Content .\evidence_YYYY-MM-DD_HHMMSS_xxxxxxxx.sha256
   ```

   Obě hexadecimální hodnoty se musí přesně shodovat.
3. V lokálním phpMyAdminu vytvořte novou prázdnou databázi s názvem ve tvaru
   `evidence_restore_drill_20260801`. Nikdy nevybírejte produkční nebo běžnou
   lokální databázi `evidence`.
4. Vyberte tuto novou databázi, klikněte na **Import** a nahrajte `.sql.gz`.
   phpMyAdmin umí gzip rozbalit. Pokud narazíte na limit velikosti, zastavte se;
   limit zvyšte lokálně nebo použijte správce, který bezpečně zadá cílovou DB.
5. Po importu porovnejte seznam tabulek a orientační počty řádků s částí
   `tables` v `.manifest.json`. Zkontrolujte také seznam triggerů.
6. Otevřete lokální aplikaci s dočasnou konfigurací ukazující výhradně na drill
   databázi a ověřte přihlášení, přehled sportovců a jeden starší trénink.
7. Teprve po kontrole smažte pouze databázi s názvem
   `evidence_restore_drill_...`. Název cíle před smazáním znovu vizuálně ověřte.

Úspěšný checksum ještě nedokazuje, že SQL jde obnovit. Za ověřenou zálohu se
považuje až sada, která prošla importem do nové izolované databáze a základním
smoke testem aplikace.

## Produkční obnova – pouze řízený zásah

Produkční obnovu neprovádějte tlačítkem deploy ani prostým importem přes
phpMyAdmin. Nejdřív je nutné:

1. zastavit zápisy do Evidence a naplánovat odstávku;
2. potvrdit přesný incident, čas zálohy a aktuální vlastnictví tabulek;
3. uložit ještě jednu nouzovou zálohu současného stavu, pokud to DB dovolí;
4. úspěšně provést lokální restore drill vybrané sady;
5. nechat druhou osobu zkontrolovat cílovou databázi a seznam tabulek;
6. připravit konkrétní obnovovací příkaz a návratový plán pro daný incident.

Teprve potom může zkušený správce provést ruční obnovu. Tento repozitář úmyslně
neobsahuje automatický produkční restore skript.

## Známá omezení

- Záloha pokrývá pouze tabulky v `EVIDENCE_TABLES` a jejich triggery. Cizí
  aplikace ve sdílené DB mají vlastní zálohy.
- Views nejsou podporovány a jejich výskyt na vlastněném názvu zastaví deploy.
- Konzistentní snapshot je garantován pouze pro InnoDB; jiný engine zastaví
  deploy.
- Záloha neukládá databázové uživatele, oprávnění, routines ani serverové
  nastavení.
- Přímý produkční restore nebyl automatizován ani autorizován.
