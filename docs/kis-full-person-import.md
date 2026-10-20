# Úplný import osob ze starého KIS

Import `kis-full-person-v1` je samostatný schválený cutover osob a kontaktů.
Nepoužívá katalog licencí jako zdroj identity a nemění kontrakt staršího
třízdrojového preview `kis-import-field-v1`. `id_hash` ze starého KIS se ukládá
do `kis_person_aliases`, nikoli jako údajně ověřené společné ID plateb a soupisek.

Administrátor otevře `kis_person_import_admin.php`, nahraje schválený JSON/GZ,
zkontroluje náhled a poté se stejným souborem potvrdí `IMPORTOVAT A NAHRADIT`.
Pokud prohlížeč nepovoluje výběr místního souboru, lze do stejného formuláře
vložit JSON nebo `KISGZ:` následované base64 původního gzip souboru. Používá
se stejná validace a otisk původního rozbaleného JSON, nikoli textového obalu.
Náhled má platnost 30 minut. Soubor ani jeho osobní údaje nepatří do Gitu.
Před akcí je nutná úplná ověřená záloha a autorizace odstranění původních osob
i jejich historie. Hesla, klíče a zdrojová data se nezapisují do logu.

## Zápis

- Jedna transakce odstraní původní osoby a související historii a vloží nový
  soubor osob, všechny aliasy, kontakty a všechny původní sloty vztahů.
- Mazání respektuje cizí klíče, zahrnuje známé legacy sloupce bez FK a celé
  dotčené objednávky i staré tréninky a závody. Budoucí plánované tréninky,
  administrátoři, účty, katalog a konfigurace nejsou kořeny
  mazání. Neznámé cykly, složené FK, netransakční tabulky a tabulky bez
  primárního klíče akci zastaví.
- Obsah dotčených řádků musí odpovídat otisku náhledu. Aplikační zámek chrání
  souběžné importy; zamčené řádky a FK chrání probíhající transakci.
- Opakované předání stejného balíku vrací původní protokol. Jiný balík pro již
  importovaný zdroj je odmítnut. Pád uprostřed zápisu vrátí i odstraněnou historii.
- Chybějící datum narození zůstává SQL NULL. Zdrojové varianty, včetně RČ,
  jsou zachovány v autentizovaně šifrovaném archivu. RČ se nekopíruje do starého
  plaintextového pole `sportovci.rc`. Klíč je oddělen pomocí HKDF. Deploy
  idempotentně připravuje samostatný klíč mimo aplikaci v
  `data/.kis-secrets/kis-person-import.json` a ověřenou soukromou kopii v
  `data/.kis-backups/keys/kis-person-import.json` (adresáře 0700, soubory 0600).
  Existující klíče nikdy nepřepisuje. Obnova databáze musí zahrnout také tento
  klíč; kopie není součástí SQL dumpu ani Gitu. Sdílené klíče
  `PERSON_RC_KEYRING` zůstávají podporované pro starší archivy a nejsou měněny.
  Bez dostupného klíče zápis nezačne.
- `kis_person_profiles` drží kontaktní adresu odděleně od trvalé adresy.
  Kontakty mohou existovat bez přihlašovacího účtu a bez známého jména.
- Nové účty mají náhodná neznámá hesla, `aktivni=0`, `email_overeno=0`.
  Účet vzniká pouze pro pojmenovanou identitu s jediným platným e-mailem, který
  nesdílí více identit. Existující účty se nepřipojují podle e-mailu.
  Role jsou pouze `pending`, nikoli `approved`; nic se nerozesílá.
- Nové členství v soupiskách se nevytváří. Původní řetězce soupisek jsou
  v šifrovaném zdroji pro následné samostatné párování.

## Kontrola a další kroky

Stránka po importu zobrazuje osoby a kontakty a poskytuje protokol s převodní
mapou. Všechny nové tabulky vlastní standardní databázová záloha.
`KisPersonImportMariaDbSmoke.php` ověřuje transakční rollback, změněný náhled,
NULL datum, zachování více zdrojových vazeb, šifrovaný archiv, neaktivní účet,
čekající roli a idempotenci na MariaDB 10.3 i 11.4.

Aktivace přihlášení, ověření vztahů, reset hesel, rozesílka a napojení soupisek
jsou samostatné kroky. Tento import je neprovádí.
