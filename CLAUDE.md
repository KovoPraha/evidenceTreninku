# Evidence + e-shop + KIS — vývojový kontext

Aktualizováno 25. 10. 2026. Před změnou přečti [aktuální stav](docs/CURRENT_STATE.md)
a [postup převzetí stanice](docs/HANDOFF_CURRENT.md), potom proveď
`git fetch --prune origin` a ověř Git, CI a dotčený kód.
Stav release patří pouze do CURRENT_STATE; historie do [provozního archivu](docs/CHANGELOG-PROVOZ.md).
[Zadání dozoru a pět oddělených balíků](docs/DOZOR_2026-10-25.md) stanoví pro tuto práci zákaz merge a deploye.
[Původní kontext](docs/CLAUDE_HISTORY.md) je archiv, nikoli návod pro novou implementaci.

## Rozsah a technologie

Samostatný modulární monolit cyklistického klubu: Evidence, e-shop, booking a KIS
sdílejí databázi, migrace, osoby a session. Nejde o submodul Velocoty.
PHP 8.2+, procedurální PDO, MySQL/MariaDB, Bootstrap a vanilla JavaScript.
Závislosti určuje composer.lock. Webové requesty nesmějí provádět migrace schématu.

## Co je implementováno

- Veřejná homepage, katalog, kurzy, klubové akce, kalendáře a velodrom.
- Rodinné účty, ověřované vazby na osoby, omezený sportovní účet a revokace session.
- Osm pracovních pozic, oddělené agendy, explicitní delegace a svěření trenérů.
- Registrářské zpracování osob, verzované registrační texty/souhlasy, papírové
  podklady, soukromé soubory a historie. Hierarchie soupisek a časově platné členství.
- Programy, přijetí, tréninky, omluvy, náhrady a uzavření skutečné účasti;
  sportovní souhrny, zdravotní podklady a příprava dalšího období.
- E-shop s variantami, cenami, košíkem více dětí, kupóny, objednávkami a platbami;
  příjmy, řízený sklad, ocenění, výdeje, vratky a vazby na opravné doklady.
- Účetní doklady, číselné řady, uzávěry, PDF/XLSX/ZIP export a audit předání účetní.
- Členské příspěvky, finanční rozhodnutí, výdaje, zálohy, odměny a klubová administrativa.
- Fronty a historie komunikace, dohled a příprava cutover; implementace není
  automatické povolení odesílání nebo převodu skutečných dat.
- Fio, Stripe a SumUp mají implementované adaptéry; aktuální provozní přepínače
  jsou pouze v CURRENT_STATE. Historický SSO bridge zůstává volitelnou kompatibilitou.
- Zálohování, migrační ledger, obnova v izolaci, PHPUnit, MariaDB smoke a HTTP přejímky.

## Výslovně otevřené

- Lidská a účetní přejímka, konkrétní pilot D12–D14 a rozhodnutí
  [D01–D20](docs/CLUB_COMPLETION_DECISIONS.md).
- Ověřené rodinné vazby a trenérské rozsahy, inventura/ocenění, skutečné dohody
  a finanční částky, doplnění chybějících podkladů. Nic nepřiřazovat odhadem.
- Ostrý import a cutover, aktivace bran/Fio/automatického potvrzování a transportů
  vyžadují samostatné rozhodnutí. Technický PASS ani HTTP 200 není úplné UAT.
- Kreditní wallet a TrainingPeaks nejsou doloženou aktivní integrací.
  `kredit_pohyby` a `sso_tokeny` nebyly nalezeny v aktuálních migračních PHP/SQL
  definicích; neuvádět je jako existující tabulky podle historického návrhu.

## Klíčové soubory a datové oblasti

| Oblast | Kanonické zdroje / tabulky |
| --- | --- |
| Bootstrap a autentizace | includes/init.php, db.php, includes/session_security.php, includes/auth_session.php |
| Pracovní pozice a rozsahy | includes/staff_workspaces.php, includes/coach_scope.php; staff_user_positions, staff_superadmins, coach_scope_grants |
| Osoby a rodiny | sportovec_karta.php, includes/family_portal.php; sportovci, verejni_uzivatele, account_person_roles |
| Soupisky a programy | includes/kis_roster.php, includes/club_program.php; club_teams, club_roster_members, club_program_admissions |
| Tréninky a skutečnost | includes/training_closure.php, includes/sports_activity.php; treninky, planovane_treninky, training_closures |
| Zdravotní podklady | includes/medical_clearance.php, includes/medical_activity.php; medical_clearance_watches, medical_clearance_records |
| Objednávky a platby | includes/shop_checkout.php, eshop_payments_admin.php; shop_orders, shop_order_items, shop_payments |
| Sklad a vratky | includes/shop_inventory.php, includes/shop_returns.php, includes/shop_fulfillment.php |
| Účetnictví | includes/accounting_documents.php, includes/accounting_export.php; accounting_documents, accounting_export_batches |
| Klubová administrativa | includes/club_expenses.php, includes/club_rewards.php, includes/club_event_work.php |
| Finanční rozhodnutí | includes/club_finance.php, includes/financial_decisions.php; financial_decisions, club_finance_settings |
| UI a ochrana vstupů | includes/ui_shell.php, hlavicka.php, csrf_helper.php, includes/private_storage.php |
| Schéma a deploy | migrations/, includes/migration_runner.php, bin/migrate.php, .github/workflows/deploy-production.yml |

Přesné sloupce a vazby určují migrace, nikoli tato orientační tabulka.
`includes/auto_migrace.php` je historický baseline používaný řízenou migrací,
nikoli instrukce ke spuštění DDL v requestu.

## Autentizace a autorizace

Trenérská identita používá `trener_id`, zákaznická `verejny_uzivatel_id` a sportovec
`sportovec_pristup_id`. Namespace se nesmějí zaměňovat. Role `trener/hlavni/admin`
jsou kompatibilita; nová stránka používá vlastníka v `staffRouteOwners()`, aktivní
pracovní pozici a doménovou actor funkci s kontrolou aktuálního oprávnění v DB.
Superadmin přepíná pracovní kontext, neslučuje všechna menu.

Nová PHP stránka musí projít anonymous sweep z balíku 2
(`php tests/Support/AnonymousSweep.php`, samostatný PR). Dokud není tento PR
sloučen, nejde o již dostupný příkaz na main. Veřejná výjimka musí být jednotlivě
zdůvodněná v allowlistu; ostatní cesty bez session musejí skončit přihlášením nebo
401/403/404/405. Při kontrole se nesmí přeskakovat podle textu názvů guardů.

## Vzor nové pracovní stránky

Příklad navazuje na skutečnou actor funkci zdravotní agendy. Vlastní stránka
musí mít vlastníka v registru a používat actor funkci své domény. Zápisová
služba znovu ověřuje práva a rozsah uvnitř transakce, včetně čekání na zámek.

```php
<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/init.php';
require_once __DIR__ . '/includes/medical_activity.php';
require_once __DIR__ . '/csrf_helper.php';

header('Cache-Control: no-store, private');
if (!isset($_SESSION['trener_id'])) {
    header('Location: login.php', true, 302);
    exit;
}
try {
    $actor = medicalActivityActor($pdo);
} catch (DomainException $error) {
    http_response_code(403);
    exit('Přístup odepřen.');
}
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    if (!csrf_verify((string) ($_POST['csrf_token'] ?? ''))) {
        http_response_code(403);
        exit('Neplatný CSRF token.');
    }
    // Volání konkrétní doménové služby: autorizace, validace, transakce a audit.
}
```

Formulář obsahuje `csrf_field()`, výstup escapuje HTML. `includes/init.php`
obnovuje autorizaci před akcí; samotné `staffEnforceCurrentRoute()` nenahrazuje
anonymní guard/actor funkci. Příklady skutečných finančních akcí jsou
v eshop_payments_admin.php: pozice finance_manager, důvod a výslovné potvrzení.

## Pracovní pravidla

- Nová práce z čerstvého origin/main; izolované worktree mimo htdocs, pokud je
  stávající kopie rozpracovaná. Žádný reset/clean nebo přidání cizího WIP.
- Do Gitu nepatří config.php, klíče, tajemství, dumpy ani skutečné osobní údaje.
- Lokální zápisy jen do oddělené syntetické databáze LOCALHOST/NEPLATIT.
- Produkční změny jen po výslovném schválení přes chráněné workflow; push/PR/CI
  nic nenasazuje. Integrace, importy a rozesílky mají samostatné oprávnění.
- Již aplikované migrace se nemění. Nová migrace aktualizuje zálohovací ownership
  kontrakt, pokud přidává vlastněnou tabulku. Viz [pravidla migrací](migrations/README.md).
- Před větší změnou a předáním udržuj konkrétní plán v docs/ podle HANDOFF_CURRENT.
- Základní ověření: composer validate --strict, dotčené testy, composer test,
  syntaxe změněných PHP a git diff --check. Windows omezení POSIX/symlink testů
  neoznačuj za plný PASS; rozhodující je čisté Linux CI.
