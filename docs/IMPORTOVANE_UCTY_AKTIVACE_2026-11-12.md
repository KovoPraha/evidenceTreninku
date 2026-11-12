# KIS: aktivace importovaných účtů rodičů

## Povolení vydání 12. 11. 2026

Vlastník pokynem „nasadit“ povolil sloučení PR #463 a chráněné vydání kódu a migrace do KIS po zeleném CI, ověřené záloze a standardních kontrolách. [Vydání 43322186478](verification/parent-activation-release-20261112/RESULTS.md) skončilo SUCCESS na `373b8d89`, s `uat_schvaleno=false` a `importovat_krouzky=false`. Pokyn neautorizuje schválení rodinných vazeb, zařazení pozvánek do fronty ani odeslání pilotní dávky. Text pozvánky, identifikační postup a rozsah pilotu musí vedení potvrdit před první skutečnou pozvánkou.

ID: KIS-ACT-01 / PL-9d3bb013a4. Stav: **KÓD NASAZEN, PILOT OTEVŘEN**. Vlastník implementace: vývoj; kontrola identity, textu pozvánky a produkčního pilotu: vedení TJ KOVO Praha. Vývojová větev `codex/parent-account-activation`, izolovaný worktree `C:\xampp\htdocs\evidencePavel-parent-activation`. Skutečné počty a stav konkrétních osob v produkci je nutné ověřit chráněným administrativním přehledem. Tento dokument neobsahuje osobní údaje.

Sdílení: [PR #463](https://github.com/KovoPraha/evidenceTreninku/pull/463) sloučen jako `373b8d89`; poslední větev `bdece243` měla 11 CI PASS. Schválení vedením pro konkrétní pozvánky a produkční pilot zůstává samostatnou bránou.

Souběh: před merge byly převzaty `428a2258` (MAKEUP-AUTO-01) a `22d151f7` (ROSTER-SCROLL-01). Obě agendy a jejich dokumentace zůstaly zachovány; výsledek společného stromu potvrdilo poslední CI PR #463.

## Analýza současného stavu

| Záznam | Význam | Co dělá import starého KIS |
| --- | --- | --- |
| `sportovci`, `kis_person_profiles` a `kis_external_id` | Existující osoba a její importní identita; členství, soupisky a historie se vztahují k ID sportovce. | Zakládá nebo mapuje profil osoby. Samotný profil nedává přístup do portálu. Přehled zahrnuje oba importní původy. |
| `verejni_uzivatele` | Přihlašovací účet rodiče nebo dospělého; má `heslo_hash`, `aktivni`, `email_overeno` a `session_version`. | Jen pro jednoznačně určený kontakt bez kolize vytváří vypnutý, neověřený účet s náhodným neznámým heslem. U sdíleného či již obsazeného e-mailu vyžaduje ruční posouzení. |
| `account_person_roles` | Vazba účtu k osobě, `guardian` nebo `self`. | Importované vazby jsou `pending` a nedávají oprávnění. Při schválení administrátorem vzniká audit v `account_person_role_events`. |
| `child_access_accounts` | Samostatný přístup sportovce s vlastním jménem, heslem a verzí relace. | Není rodičovským účtem a nemá se vytvářet jako náhrada rodiče. |
| `kis_person_contacts` a `kis_person_links` | Podklad o importovaných kontaktech a rodinných vazbách. | Slouží k administrativní kontrole; e-mail ani jméno samy neprokazují totožnost. |

Přihlášení v `booking/prihlaseni.php` přes `unifiedAccountAuthenticate` přijímá jen aktivní účet; portál v `familyPortalAuthorizedPeople` ukazuje jen platné schválené vazby účtu s ověřeným e-mailem. `booking/zapomenute_heslo.php` a `includes/password_reset.php` vydávají jednorázový reset jen pro aktivní ověřený účet. Registrace vytváří veřejný účet a ověřuje e-mail; rodič bez účtu může pak v `booking/moje_osoby.php` požádat o propojení s již existujícím dítětem. Správce žádost posoudí v `eshop_identity_admin.php`. Samotná shoda jména automatické propojení neprovádí.

## Nejjednodušší bezpečný postup

1. Administrátor v novém přehledu dohledá importovanou osobu, rodičovský účet a všechny její vazby. Před schválením ověří totožnost zástupce a kontaktní adresu mimo samotný importní e-mail. Vazby schválí ve stávající správě identit. Účet se bez schválené vazby nepozve.
2. U neaktivního nebo neověřeného importovaného účtu po individuální kontrole zařadí pozvánku. Odkaz se uloží jen jako hash, tělo e-mailu šifrovaně do stávající fronty. Po otevření odkazu rodič nastaví vlastní heslo; jedna transakce ověří aktuální e-mail a vazby, aktivuje účet, ověří adresu, zvýší `session_version`, zneplatní ostatní odkazy a zaznamená audit. Pak se běžně přihlásí.
3. U již aktivního ověřeného účtu stejná správa zařadí odkaz na stávající obnovu hesla. Přístup k dětem se nemění. Doručovací e-mail nového odkazu je při použití kontrolován proti aktuálnímu e-mailu účtu.
4. Pokud existuje pouze profil sportovce, rodič použije běžnou registraci účtu bez vytváření dalšího profilu dítěte, ověří e-mail a odešle žádost v „Moje osoby“. Administrátor schválí vazbu ke stávajícímu ID sportovce. U více dětí schválí každou vazbu zvlášť.

Samostatný účet sportovce zůstává oddělený. Jeho případné zřízení nebo změnu podle věku řeší současná správa přístupů sportovců; tato úprava nezavádí další typ přihlášení.

## Rozsah změn

- Nová migrace `migrations/20261112150000_imported_account_activation.php`: volitelný `password_reset_tokens.delivery_email` a auditní tabulka `public_account_activation_events`. Existující tokeny zůstávají platné podle původních pravidel.
- `includes/imported_account_activation.php`: bezpečnostní podmínky, vydání pozvánky, ověření a jednorázové dokončení; omezení na jednoznačný e-mail, žádný trenérský účet, schválené platné vazby importovaných osob a žádné čekající importní vazby.
- `booking/aktivace_uctu.php`: stránka pro nastavení prvního hesla. Token je v fragmentu URL a do serverového logu URL se neposílá; odesílá se až v chráněném POST s CSRF.
- `kis_account_activation_admin.php`: stránkovaný přehled importovaných osob, hledání, filtry, stav účtu/vazby/samostatného přístupu/pozvánky a výběr až 100 účtů do dávky. Odeslání požaduje potvrzení individuální kontroly a důvod. Každý účet se znovu kontroluje v transakci.
- `includes/auth_mail_outbox.php`, `includes/password_reset.php`, `includes/one_time_token.php`: navázání aktivačního tokenu na existující šifrovanou frontu, kontrola aktuální adresy a audit změny hesla.
- Stávající znovuodeslání běžného ověřovacího odkazu a jeho použití odmítají účty vytvořené starým importem; ty musí projít kontrolovaným nastavením prvního hesla.
- `includes/staff_workspaces.php`: administrativní navigace k nové správě. `bin/db-backup.php` a kontrolní test: záloha auditní tabulky.
- `.github/workflows/tests.yml`: srovnávací MariaDB profily musí použít shodnou migrační sadu ve starém i novém worktree; nová migrace se proto kopíruje do tří srovnávacích běhů. `tests/Integration/AuthSecurityMigrationTest.php` kontroluje její přítomnost a opakovatelnost.

Fronta rozlišuje `pending`, `processing`, `sent`, `failed` a `cancelled`. Stav `sent` dokládá jen předání dopravci; infrastruktura nyní neposkytuje potvrzení doručení do schránky. Nové pozvání se nevydá, dokud pro účet existuje platný nepoužitý resetovací nebo aktivační odkaz. Heslo ani token se v administraci nezobrazuje.

## Rizika a kontrolní body

- Importovaná kontaktní adresa nemusí patřit rodiči. Schválení vazby a potvrzení kontroly adresy jsou lidský krok; pouhé otevření e-mailu neprokazuje rodičovství. U sporného nebo sdíleného e-mailu účet zůstává blokován, dokud správce nesjedná jednoznačný účet a vazbu.
- Pokud je část importovaných vazeb stále `pending`, pozvánka daného účtu se nevydá. Tím se brání částečnému přístupu u více dětí bez kontroly všech vazeb.
- Při změně e-mailu, zrušení schválené vazby, vypršení či použití tokenu se aktivace odmítne. Změna hesla a aktivace zvyšují verzi relace.
- Hromadné odeslání zatěžuje e-mailovou frontu; před první dávkou ověřit pracovníka, šifrovací klíče, transport, limity a vzor zprávy na syntetických účtech. Začít malou schválenou dávkou.
- Před první produkční pozvánkou vedení organizace schválí text, postup kontroly identity, rozsah pilotní dávky a způsob řešení nedoručených zpráv. Pokyn vlastníka k nasazení povoluje vydání kódu a migrace, nikoli odeslání.

## Ověření v této větvi

- PASS: CI na commitu `64b546da`: 2463 PHPUnit testů / 56435 kontrol, PHPStan a styl, dokumentace, CodeQL a integrační i profilovací joby MariaDB 10.3/11.4. [Běh](https://github.com/KovoPraha/evidenceTreninku/actions/runs/43263993335). Lokální neúplný vendor je popsán níže.
- PASS: cílená sada s dočasným bootstrapem namířeným na tento worktree: 68 testů / 3217 kontrol. Nevyřízená vazba blokuje pozvánku; schválený rodič se dvěma dětmi aktivuje účet jedním odkazem; opakované použití a vypršení nefungují; duplicitní či změněný e-mail, zrušená vazba i mezitím aktivovaný účet blokují; běžné ověření e-mailu obchvat nevytvoří; aktivní ověřený účet projde resetem; po aktivaci přihlášení ukazuje právě schválené děti. Sada zahrnuje obnovu hesla, poštovní frontu, žádosti o vazbu, rodinný portál, zálohovací kontrakt, katalog migrací a registraci administrativní stránky.
- PASS: cílené stávající testy obnovy hesla, poštovní fronty a zálohovacího kontraktu, 40 testů / 701 kontrol; existující tok žádosti o vazbu 8 testů / 76 kontrol s dočasným bootstrapem namířeným na tento worktree. Syntaktická kontrola dotčených PHP souborů, `composer validate --strict` a `git diff --check` PASS.
- Celá lokální sada `composer test` STOP po 248 testech: sdílený vendor původního checkoutu nemá `Symfony\Component\Filesystem\Path`. Jde o omezení lokálních závislostí; výsledek celé sady nelze označit PASS. CI se správně instalovaným `composer.lock` musí projít před merge.
- Autentizované prohlížečové UAT, počty skutečných importovaných účtů, doručitelnost a schválení pilotu zůstávají samostatnou předprodukční kontrolou. Lokální testovací MariaDB na portu 3308 nebyla při kontrole spuštěná; pokus o agregované čtení skončil odmítnutím spojení. Tato větev nečetla produkční osobní údaje a neodeslala žádnou produkční zprávu.

Další krok: vedení schválí identifikační postup, text pozvánky a malou pilotní dávku; správce potom na ověřených vazbách provede pilot. Produkční pozvánky, doručení, přihlášení a správné zobrazení dětí dosud NOT RUN. [Technické vydání a přesné meze](verification/parent-activation-release-20261112/RESULTS.md).
