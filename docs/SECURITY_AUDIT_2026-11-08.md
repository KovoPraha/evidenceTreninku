# SEC01 — Bezpečnostní audit aplikace 8. 11. 2026

- Stav: implementace a místní ověření HOTOVO, předání PR / CI otevřené; vlastník: vývoj / zadavatel.
- Oprávnění: přímé zadání auditu, oprav a dokumentace; předání přes PR.
- Větev: `codex/security-audit-20261108`, worktree `/workspace/evidenceTreninku`.
- Výchozí main: `a3cca282f4db5e9082313b8cf27b958b552ac897`.
- Rozsah: aktuální autentizace/session, CSRF, soukromé soubory, webové hranice,
  riziková volání a dostupné závislosti; lokální syntetická reprodukce.
- Postup: prověřit současný kód, reprodukovat vady, přidat regresní testy,
  opravit, spustit přiměřené kontroly a předat k revizi přes GitHub PR.
- Varianty: starší audity jsou vodítko; opravy pouze pro současný doložený problém.
- Akceptace: doložené nálezy, opravy, výsledky PASS/FAIL/NOT RUN a meze auditu.
- Závislosti: dočasné PHP a Composer připraveny, zamčené závislosti ověřeny.
- Produkční nasazení, aktivace, skutečné platby a rozesílky nejsou součástí práce.
- Další krok: předání přes PR, kontrola CI a revize; deploy vyžaduje samostatné pověření.

## Potvrzené nálezy a opravy

### SEC01-1 — nekánonická cesta soukromého úložiště (MEDIUM)

`privateStorageRoot()` porovnávala řetězec konfigurace s webrootem. Odkaz
umístěný mimo webroot, který ve skutečnosti mířil do aplikace, prošel kontrolou.
Stejná mezera existovala pro dosud neexistující podadresář takového odkazu.
Předpokladem je chybná konfigurace nebo možnost měnit souborový systém;
nejde o doložený anonymní vzdálený útok ani důkaz úniku na produkci.

Oprava: absolutní lokální cesta bez tečkových segmentů, ověření skutečné cesty
včetně nejbližšího existujícího předka před případným vytvořením adresářů.
Soukromé soubory tak nelze konfiguračním aliasem přesunout do veřejné aplikace.

### SEC01-2 — symbolické odkazy kategorií a souborů (MEDIUM)

`privateStorageEnsureDirectory()` přijala kategorii tvořenou odkazem a provedla
nad cílem `chmod`; následné ukládání opustilo určenou kategorii.
`privateStorageResolve()` kontrolovala jen `is_file`, takže při znalosti platného
klíče následovala odkaz souboru nebo kategorie. Doménová autorizace a znalost
klíče zůstávají podmínkou čtení; samotná tato vada je neobchází.

Oprava: odmítnutí odkazované kategorie před vytvořením/zápisem/chmod,
odmítnutí odkazovaných kategorií a souborů při čtení/mazání a kontrola skutečné
výsledné cesty uvnitř soukromého kořene. Platné běžné soubory zůstávají dostupné.

## Reprodukce a ověření

Prostředí: Linux, PHP 8.4.24 v dočasném adresáři, Composer ze zamčených závislostí.
PHP ani Composer nebyly původně nainstalované; Debian balíčky byly rozbaleny
pouze do `/tmp` a ověřeny SHA-256 proti oficiálnímu katalogu. Bez systémové instalace.
Testy používaly pouze syntetické dočasné soubory a vlastní session adresář.

| Kontrola | Výsledek |
| --- | --- |
| Reprodukce původního kódu: root symlink / category read / category write | Všechny tři nebezpečné případy ACCEPTED |
| Stejná reprodukce po opravě | Všechny tři REJECTED |
| PHPUnit cíleně: `PrivateStorage`, `SecurityAuditRegression`, `SessionSecurity`, `PasswordSecurity`, `AuthRateLimit`, `ContentSecurityPolicy`, `LegacySecurityHardening` | PASS: 95 testů, 15 114 assertions |
| Nové regresní scénáře | Relativní/tečkové cesty, webroot alias i chybějící potomek, odkazovaná kategorie pro čtení/zápis, odkazovaný soubor pro čtení/mazání |
| `composer validate --strict` | PASS |
| `composer audit --locked --format=json` | PASS: advisories i abandoned prázdné |
| PHPStan (`phpVersion: 80200`) | PASS: No errors |
| PHP lint obou změněných PHP souborů | PASS |
| `php tools/check-php-style.php` | PASS |
| `php tools/check-current-docs.php`, `git diff --check` | PASS v průběžné kontrole; zopakovat před commitem |
| Úplný PHPUnit | PASS: 2 381 testů, 47 789 assertions; 1:08, 115 MB |
| Produkční testy, Apache HTTP ochrany a MariaDB matice | NOT RUN v této relaci |

První cílený běh selhal kvůli chybějícímu `/var/lib/php/sessions` a omezení
lokálních socketů sandboxem. Po nastavení vlastního session adresáře a běhu
HTTP testů mimo sandbox prošlo všech 95 testů. Toto nebyla chyba aplikace.

## Další prověřené hranice a meze

- Staticky zkontrolovány session lifecycle, CSRF helper, politika hesel,
  IP za důvěryhodnou proxy, push endpoint allowlist, soukromý download a Apache
  blokace interních/upload cest. V tomto průchodu bez dalšího potvrzeného nálezu.
- Audit závislostí odpovídá databázi advisories v okamžiku kontroly, není zárukou
  absence dosud nepublikované chyby.
- Oprava neřeší útočníka se stejným OS účtem, který souběžně přepisuje adresáře
  mezi kontrolou a přístupem; úložiště musí vlastnit důvěryhodný účet a mít
  omezená práva. Produkční stav těchto práv nebyl v této relaci ověřován.
- Tento dokument uzavírá konkrétní statický a regresní řez. Úplný penetrační
  audit všech rolí, obchodních operací, provozní konfigurace a souběhu není proveden.
- Změny jsou určeny pro revizi přes PR; merge, deploy a provozní přejímka se
  evidují samostatně. Další krok: revize PR a výsledků CI.


Úplné ověření nejprve narazilo na výchozí limit 128 MB, poté na dvě vlastnosti
přenosného runtime: jméno binárky `php8.4` místo `php` a chybějící knihovnu
`libargon2` v procesu s úmyslně prázdným prostředím. Samostatná diagnostika
reprodukovala obě příčiny bez aplikačního kódu. Po opravě pouze `/tmp` runtime
prošlo cílených 9 testů / 72 assertions a následně celá sada 2 381 / 47 789.
Nebyly měněny aplikační testy ani jejich očekávání kvůli prostředí.

Předání: vlastní vzdálená větev `codex/security-audit-20261108`; obsah se ukládá
přes GitHub connector do samostatného commitu s tímto protokolem. PR bude
otevřen k revizi. Merge / deploy / provozní přejímka NOT RUN.
