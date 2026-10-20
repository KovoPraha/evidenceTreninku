# Cesta rodiče — lokální dokončení 20. 10. 2026

Větev `codex/parent-journey` v odděleném worktree
`C:\xampp\htdocs\evidencePavel-parent-journey-20261020` vychází z
`2b6bc7998d2fb621dc3a694a3c3d5102a3def54b`. Níže jsou lokální změny,
nikoli potvrzení jejich nasazení. Původní checkout a jeho rozpracované výstupy
zůstaly oddělené. Skutečné platby přes Fio, Stripe a SumUp jsou podle zadání
odložené; testy používají pouze kanonické administrátorské potvrzení
syntetické platby.

## Opravené návaznosti

| Krok | Lokální výsledné chování | Regresní důkaz |
|---|---|---|
| Výběr importovaného kroužku | Publikace katalogu nezpřístupní kroužek jako anonymní nákup zboží. Nabídka zůstává viditelná; nákup vyžaduje výslovné propojení programu, období, soupisky a schválených podmínek. | `ShopStorefrontTest`, `ShopCheckoutTest`, `ClubProgramWizardTest` |
| Registrace a připojení dítěte | Bezpečný návrat k vybranému produktu a variantě pokračuje přes přihlášení, registraci, ověření, opakované zaslání odkazu a žádost o sportovce. | `BookingReturnTest`, `AuthMailOutboxTest` |
| První přihlášení rychlého účtu | Po jednorázovém ověření e-mailu rodič nastaví první heslo přes omezené oprávnění ve své session. Změna odvolá staré resetovací tokeny a zvýší verzi session. | `QuickAccountPasswordSetupTest` |
| Platba před kontrolou dítěte | Zaplacená přihláška drží místo i během čekání na schválení identity; po aktivaci se stejné místo nezapočítá podruhé. | `ClubProgramPaidIdentityCapacityTest`, `ClubProgramPaymentLifecycleTest` |
| Zamítnutí nebo zrušení | Samostatná objednávka žádosti se vypořádá v téže transakci. Neuhrazená se zruší, uhrazená přejde do požadavku na ruční vrácení peněz a uvolní kapacitu. Smíšená nebo již přiřazená objednávka vyžaduje správce. | `AthleteRegistrationOrderSettlementTest` |
| Oznámení rozhodnutí a aktivace | Rozhodnutí a pozdější zařazení do soupisky zapisují trvalou zprávu spolu s příslušným přechodem; unikátní klíč brání opakovanému zařazení stejné zprávy. | `AccountPersonClaimTest`, `ParentJourneyAcceptanceTest` |
| Moje kroužky | Rodič vidí i zaplacenou žádost bez ještě připojené osoby. Rozlišují se čekání na e-mail, platbu a kontrolu, aktivace, ukončení i vrácení peněz. | `ClubProgramAccountOverviewTest` |
| Provozní kontrola pošty | Společný worker má čtecí `--status`, omezené zpracování obou front a lokální test transportu bez SMTP. | `TransactionalNotificationWorkerTest` |

## Akceptační řetězec

`ParentJourneyAcceptanceTest` vykonává skutečné služby nad novou SQLite databází
v paměti: rychlá přihláška → neověřený účet a čekající žádost → spotřebování
ověřovacího tokenu → první heslo → syntetické potvrzení platby → držená kapacita
→ `athleteRegistrationAdminApproveExisting` → schválený vztah rodiče, účast
a aktivní člen soupisky → rodičovský přehled → vyprázdnění čtyř oznámení pomocí
vloženého testovacího transportu. Opakování objednávky, platebního přechodu
a propojení nepřidá další účast, místo ani oznámení. Samostatný scénář provede
zaplacení a skutečné zamítnutí žádosti, ověří uvolnění místa a stav
`refund_required`. Schválení žádosti se v testu neobchází ruční změnou jejího
stavu v SQL.

Cílený akceptační běh prošel: **2 testy / 92 kontrol**, PHP 8.2.12.
Závěrečná kompletní sada nad stabilní integrovanou verzí prošla:
**1 017 testů / 23 523 kontrol**, PHP 8.2.12, bez selhání a chyb.
Prošla také kontrola syntaxe změněných PHP souborů, `composer validate`
a `git diff --check`.
Integrační smoke na oddělené MariaDB 10.4.32 prošel také nad výsledným kódem:
souběžný checkout, opakované potvrzení platby a závod o poslední místo.
Při aplikaci historické migrace `20260922190000_member_fee_standing_orders.php`
se objevuje dřívější PHP varování `Undefined array key "columns"` na řádku 13.
Smoke končí úspěšně; historická migrace nebyla v této opravě změněna.
Testovací MariaDB byla po ověření řádně vypnuta. Automatická kontrola odmítla
odstranění jejích dvou dočasných datových adresářů (`blocked by policy`);
zůstávají pouze v nesledované `.codex-tmp/` mimo sadu změn.
Tyto testy nedokládají doručení do skutečné schránky, platbu u poskytovatele
ani uživatelskou průchodnost v prohlížeči.

```text
php vendor/bin/phpunit tests/Integration/ParentJourneyAcceptanceTest.php
php vendor/bin/phpunit
```

## Co musí následovat před skutečným používáním

1. Zkontrolovat a nasadit lokální změny běžným schváleným release postupem,
   včetně nové migrace `20261020120000_parent_journey_notifications.php`.
2. U skutečných importovaných kroužků správce výslovně určí program, období,
   cílovou soupisku, kapacitu a platné schválené podmínky. Kód nemůže
   spolehlivě odvodit tyto údaje z názvu produktu a hromadně je nezměnil.
3. Ověřit provoz společného workeru a doručení zpráv do vyhrazené schránky podle
   [provozního postupu](identity-email-queue.md). `ready=true` diagnostiky
   nedokládá běžící plán ani skutečné doručení.
4. Provést samostatnou uživatelskou prohlídku syntetické cesty v prohlížeči.
   V této práci neproběhla: automatická kontrola odmítla pokus o přípravu
   aplikačních demo dat; alternativní zápis do aplikační databáze se neprováděl.
5. Samostatně dořešit poskytovatele plateb, jejich konfiguraci a reálné ověření
   plateb podle dalšího zadání. Stav `refund_required` sám peníze nevrací.

Části níže uvedených stavových dokumentů z 11. 10. 2026 jsou historické snapshoty;
jejich starší SHA, počty testů a produkční UAT nelze použít jako ověření tohoto
lokálního dokončení.
