# Ověřovací a obnovovací e-maily

## AUTH-MAIL-05 — společný přehled pošty, kontrolní kopie a bezpečné opakování (24. 11. 2026)

**Stav: KONCEPT K REVIZI, pouze návrh; implementace a nasazení NOT RUN.**
Vlastník zadání: Marek/klub; návrh: vývoj KIS. Větev
`codex/auth-mail-admin-plan-20261124`, samostatný worktree
`C:\Users\Marek\Documents\Codex\evidencePavel-auth-mail-admin-plan-20261124`
z `origin/main` `7eeacfa7`. Oprávnění: čtecí diagnostika a dokumentační PR;
bez zkušebního odeslání, změny účtů, zapnutí jiné fronty či nasazení.
[Registr](WORK_PLAN_CURRENT.md#auth-mail-05--společná-administrace-e-mailů-24-11-2026).
[Dokumentační PR #524](https://github.com/KovoPraha/evidenceTreninku/pull/524)
je otevřený; push proveden, merge/implementace/deploy NOT RUN.
Místní `php tools/check-current-docs.php` a `git diff --check` PASS.
V CI [run 45380794696](https://github.com/KovoPraha/evidenceTreninku/actions/runs/45380794696)
prošel `docs`; job PHPUnit selhal před testy na `composer audit --locked`
(39 advisories ve 2 balíčcích existujícího `composer.lock`, který PR nemění).
Jde o samostatný blokátor CI; testy návrhu neproběhly, protože návrh nemění kód.

### Nález a hranice důkazu

Vlastník 24. 11. hlásí, že nová registrace nedostává ověřovací e-mail a žádá
úplný přehled všech požadavků, ruční resend a kontrolní skrytou kopii. Aktuální
`auth_mail_admin.php` ukazuje bez filtru jen aktivní/selhané, nejvýše 100 řádků;
`sent` lze vybrat kartou, ale chybí datum, hledání adresy, detail pokusů a
důkaz doručení. Reset má vlastní audit žádostí včetně nezařazených, registrace
a nové ověření nikoli. `eshop_notifications_admin.php` spravuje samostatnou
frontu. Další fronty jsou `roster_message_recipients`,
`family_weekly_summaries`, `member_charge_reminders` a
`monthly_membership_messages`; při implementaci provést úplnou inventuru
všech produkčních odesílacích cest včetně přímých volání transportu.

`booking/registrace.php` vytváří účet, ověřovací token a řádek
`auth_mail_outbox` atomicky. Neúspěch vložení vrací transakci včetně účtu;
duplicitní e-mail vrací obecné potvrzení bez nové zprávy. Rate limit, CSRF a
validace mohou požadavek zastavit ještě před frontou. Tyto cesty dnes nemají
jednotnou referenci v administraci. Čtecí produkční [status 45379814959](https://github.com/KovoPraha/evidenceTreninku/actions/runs/45379814959)
ve 24. 11. 2026 10:58 UTC ukázal nedávný úspěšný CRON, rodičovskou frontu
`pending=0`, `processing=0`, `failed=0`, `sent=11`, `cancelled=2` a
`mailbox_delivery_verified=false`. Proti 22. 11. přibyla jedna zpráva `sent`;
bez reference konkrétní registrace ji nelze přiřadit. `sent` znamená přijetí
PHP `mail()`, nikoli převzetí příjemcovým serverem nebo doručení do schránky.
Souhrnný `attention_required` se týká i oddělené klubové fronty (10 `pending`,
87 `failed`), nikoli rodičovské fronty; její výdej se tímto návrhem nezapíná.

### Doporučená podoba administrace

1. Stránka **Veškerá pošta**: stránkovaný chronologický přehled všech
   registrovaných požadavků z jednotlivých front a žádostí před frontou.
   Sloupce: čas vzniku, reference, oblast/typ, příjemce jen oprávněné roli,
   stav požadavku, stav fronty, počet pokusů, čas posledního pokusu,
   transportní výsledek, detail/důvod. Filtry: období, druh, stav, přesná
   adresa, reference/ID; export pouze minimálních metadat a po zvláštním
   oprávnění. Žádný výchozí limit skrytých starších záznamů: stránkování a
   souhrny s celkovým počtem. Detail spojí žádost, frontový řádek, pokusy,
   ruční zásahy a stav odkazu; nikdy neukáže heslo, token ani plný text
   bezpečnostního e-mailu.
2. Jednotné názvy stavů: **přijato → zamítnuto / nezařazeno / ve frontě →
   zpracovává se → přijato transportem / odmítnuto / doručení nejasné /
   nahrazeno**. „Doručeno“ se zobrazí jen při doložené události dopravce
   nebo potvrzení schránky, s uvedením původu důkazu. Administrace nesmí
   označovat výsledek `mail()` za doručení.
3. Akce **opakovat** v detailu vyžaduje oprávnění, CSRF, povinný důvod,
   potvrzení a neměnnou auditní událost. `failed` s prokazatelně odmítnutým
   odesláním a stále platným odkazem lze znovu zařadit pod zámkem. `sent` a
   `delivery_unknown` nelze slepě zopakovat: nejprve ověřit logy/doručení.
   Pro ověření a obnovu po expiraci nebo nejistém výsledku akce vystaví
   **nový** jednorázový odkaz přes existující doménovou službu, zneplatní
   starý podle pravidel typu, zaznamená vazbu `nahrazuje/nahrazeno` a zabrání
   dvojitému kliknutí idempotentním klíčem. Nic neodesílat přímo z webového
   POST; po zařazení odesílá příslušný worker. U ostatních typů znovu ověřit
   aktuálnost události a dosavadní deduplikační pravidla.
4. **Kontrolní kopie:** pro běžné neautentizační zprávy umožnit před jejich
   prvním pokusem zvolit ověřenou správcovskou kontrolní adresu ze seznamu;
   kopie má samostatný stav a audit a nesmí změnit hlavního příjemce.
   U hesla/ověření se použitelný jednorázový odkaz neposílá na libovolnou
   další adresu: držitel kopie by mohl převzít účet. Správci lze poslat
   oddělené kontrolní oznámení bez odkazu/tokenů a pro skutečný test celé
   zprávy použít řízený testovací účet a schránku. Volně zadanou adresu lze
   použít jen pro samostatný neautentizační test transportu po jejím ověření;
   takový test sám o sobě nedokazuje doručení konkrétnímu rodiči. Skutečná
   BCC se nemá přidávat dodatečně k již odeslanému řádku ani opětovně posílat
   použitý odkaz.

### Návrh dat a realizace

- Zavést lehký společný **registr záměrů a doručovacích pokusů** s odkazem na
  původní frontu, nikoli převádět všechna těla zpráv do nové tabulky. Každá
  žádost dostane stabilní náhodnou referenci, `source_type/source_id`, druh,
  čas, stav a strojový důvod; auditní události zachytí přechody, aktéra,
  transportní ID a případnou kontrolní kopii. Vazbu na účet/adresu ukládat
  jen tam, kde ji správce potřebuje a s omezenou retencí. Pro přesné hledání
  nezařazených pokusů podle adresy použít klíčovaný otisk normalizované
  adresy; správce zadá celou adresu a server porovná otisk, neukládá ji do
  auditní tabulky jako plaintext. Klíč a rotaci oddělit od veřejných tokenů.
  Citlivé obsahy
  zůstanou šifrované v původních frontách; `sent` u auth nyní maže šifrované
  tělo, takže historickou zprávu nelze znovu vykreslit ani přesně odeslat.
- Staré existující řádky načíst čtecí sjednocenou projekcí a označit jejich
  důkazní meze. Nezařazené historické pokusy bez auditu se nedají věrohodně
  zpětně vytvořit. Nové registrační, ověřovací a obnovovací POSTy auditovat
  už od vstupu: `received`, validace/CSRF/limit/duplicitní účet, chyba zápisu,
  nebo `queued`. U úspěšné registrace zapsat `queued` ve stejné transakci
  s účtem, tokenem a outboxem; při rollbacku uložit bezpečný `failed` pod
  stejnou referencí mimo vrácenou transakci. Veřejné odpovědi zůstanou
  obecné, aby nebylo možné zjišťovat existenci účtů. Audit nesmí obsahovat
  heslo, token, tělo ani volnou výjimku s osobními daty.
- Pro výsledek přepravy nahradit nebo doplnit PHP `mail()` dopravcem s
  korelačním ID, logem přijetí/odmítnutí a ověřenými doručovacími událostmi
  (delivery/bounce), případně získat ekvivalentní MTA logy hostingu. Zvolit
  dopravce až po porovnání přístupů, DNS/SPF/DKIM/DMARC a retenčních podmínek.
  Všechna potvrzení musí být ověřena, idempotentní a spojena s konkrétním
  pokusem. Ani přijatá kontrolní kopie není důkazem přijetí hlavním příjemcem.
- Přidat dohled nad stářím `pending`, chybami, heartbeaty jednotlivých workerů,
  odmítnutími a bouncy. Výstraha má ukazovat oblast a referenci, ne obsah.
  Druhý transport nebo automatický failover navazuje na AUTH-MAIL-03 až po
  definování bezpečného chování při neznámém výsledku.

### Etapy a přejímka

1. **Bez odesílání:** ověřit hlášenou registraci podle času/reference, účet a
   audit, frontu, CRON a hostingové MTA logy; rozlišit `not queued`,
   `mail accepted`, SMTP odmítnutí a doručení/spam. Inventarizovat všechny
   PHP odesílací cesty a dostupná provozní data. Bez tohoto důkazu není
   příčina aktuálního případu určena.
2. **Evidence a přehled:** migrace/backup ownership, jednotné reference,
   zápis výsledků před frontou, čtecí sjednocený přehled, filtry a detail.
   Testovat oprávnění, stránkování, úplnost proti inventuře a ochranu PII.
3. **Zásahy a kontrola:** auditované akce podle typu stavu, bezpečné vydání
   nového odkazu, kontrolní oznámení bez tokenu, řízené testovací schránky.
   Souběh dvou správců nesmí vytvořit dva odkazy nebo dvě akce.
4. **Doručovací důkazy:** zvolený transport/MTA integrace, bezpečné události,
   bounce/odmítnutí, alarmy a přejímka na testovacích adresách pro registraci,
   nový ověřovací odkaz, reset i běžné oznámení. Ověřit skutečný příjem v
   kontrolní schránce a korelaci na ID pokusu. Připravit provozní návod pro
   případy `sent` bez doručení a expirované odkazy.

Akceptace: každý **nový** POST registrace/obnovy/nového ověření je dohledatelný
pod referencí včetně nezařazení; každý frontový řádek ze všech inventarizovaných
zdrojů se objeví právě jednou v přehledu a ve filtrech; správce pozná rozdíl
mezi přijetím PHP transportem a doloženým doručením. Bezpečné opakování a
kontrolní kopie neumožní získat cizí ověřovací/resetovací token, nevytvoří
duplicitní účet a každá akce je auditovaná. CI, chráněný deploy, živý čtecí
stav a přejímka skutečné schránky se vyhodnotí odděleně. Retenci a okruh
oprávněných správců je nutné schválit před implementací produkčních dat.

Další krok: věcně schválit rozsah etap, seznam kontrolních adres/oprávněných
rolí a pravidlo retence; současně pokračovat v čtecí diagnostice konkrétní
registrace. Dnešní návrh neprokazuje, proč daná zpráva nedorazila.

## AUTH-MAIL-04 — dohledatelnost požadavku na obnovu hesla (22. 11. 2026)

**Stav: KÓD NASAZEN; přihlášené UAT a doručení otevřené.** Vlastník: vývoj KIS. Větev
`codex/auth-mail-reset-request-audit-20261122` v odděleném worktree
`C:\Users\Marek\Documents\Codex\evidencePavel-auth-mail-repair-20261121` z
`origin/main` `2341f16c`. Oprávnění: prověřit a opravit formulář a evidenci
požadavků; bez produkčního odesílání, ručního vydávání tokenů či změny účtů.
[Registr](WORK_PLAN_CURRENT.md#auth-mail-04--dohledatelnost-požadavku-na-obnovu-hesla-22-11-2026).

**Zjištění z kódu:** POST na `booking/zapomenute_heslo.php` volá rate limit a
frontovací službu, ale při zamítnutí limitem (`false`), nenalezeném nebo
nezpůsobilém účtu (`issued=false`) a výjimce zobrazí stejné obecné potvrzení.
Ve frontě pak nemusí být žádný řádek. Limit je 5 pokusů na identifikátor a 40
na IP za 15 minut; konkrétní hlášený případ bez časového údaje a identifikátoru
nelze zpětně přiřadit k jedné z těchto cest. Veřejný formulář dovoluje 254
znaků, frontovací služba tiše odmítá identifikátor delší než 160 znaků.

**Volba:** Zachovat stejnou veřejnou odpověď pro existující i neexistující
účet. Každému POST přidělit náhodné referenční ID a do nové omezeně přístupné
auditní tabulky zapsat pouze stav, strojový důvod a případné ID frontového
řádku; bez adresy, IP, hesla, tokenu a těla zprávy. Úspěšné `queued` musí být
uloženo ve stejné transakci jako token a outbox. Při chybě zařazení zůstane
auditní `failed` a strukturovaný serverový log s referencí. Administrace
umožní hledání reference a ukáže navázaný stav fronty. Pokud selže samotný
zápis auditu, zařazení se nespustí a reference zůstane alespoň v serverovém
logu. Alternativa pouze `error_log` by neukázala zamítnutí limitem ani
nezpůsobilý účet; ukládat plaintext adresy by zbytečně rozšiřovalo osobní data.

Akceptace: syntetický POST s platným CSRF a způsobilým účtem vytvoří právě
jeden token, outbox a audit `queued` se stejnou referencí. Zamítnuté CSRF,
limit, neplatný vstup a nezpůsobilý účet mají auditní důvod bez outboxu.
Vynucené selhání šifrování nebo INSERTu vrátí transakci včetně tokenu a
zanechá dohledatelné `failed`. Formulář nesmí z odpovědi odhalit existenci
účtu. Správce dohledá reference i stav zprávy. Lokální testy, migrace, CI,
merge, deploy a provozní přejímka se evidují odděleně.

Závislosti: nová migrace před vydáním kódu, zálohovací ownership tabulky,
aktuální rodičovský worker a bezpečná administrátorská role. Implementace a
syntetický POST stránky prošly 38 cílenými testy; do produkce se zatím nic
nevydalo. [PR #517](https://github.com/KovoPraha/evidenceTreninku/pull/517)
obsahuje kód, migraci, testy i tuto dokumentaci. Vlastník 22. 11. 2026 schválil
sloučení a nasazení; PR byl sloučen do `main` jako `641493a6` a
[workflow 44972109908](https://github.com/KovoPraha/evidenceTreninku/actions/runs/44972109908)
prošel. Živý formulář vrátil referenci při syntetickém odmítnutí; přihlášené
dohledání a oprávněný reset čekají na kontrolní účet a správce. Auditní tabulka zatím nemá
schválenou dobu retence; stanovit ji před dlouhodobým provozem.

[Místní výsledky a meze](verification/auth-mail-request-audit-20261122/RESULTS.md).

## AUTH-MAIL-02 — třídenní obnova a náhrada dvou starých odkazů (21. 11. 2026)

**Stav: K OVĚŘENÍ DORUČENÍ — kód nasazen, dvě nové zprávy přijaty transportem.** Pokyn vlastníka: poslat nové obnovovací zprávy dvěma
příjemcům, jejichž původní zprávy zrušil první běh rodičovského workeru, a
prodloužit platnost nových odkazů na 3 dny. Vlastník vývoj KIS; větev
`codex/auth-mail-reset-72h-reissue-20261121`, oddělený worktree z `origin/main`
`eb78c45b`, [PR #510](https://github.com/KovoPraha/evidenceTreninku/pull/510) v `main` jako `ccac61ab`. Produkční odeslání bylo výslovně povoleno tímto pokynem a
proběhlo po ověření přesného rozsahu, nasazení a čtecí kontrole. Adresy,
tokeny ani těla zpráv se neukládají do Gitu či výstupu workflow.

Rozsah: běžná samoobslužná obnova veřejného/rodičovského účtu má mít platnost
72 hodin v tokenu i textu e-mailu. Trenérské a aktivační odkazy ani ověření
registrace se tím nemění. Zrušené staré zprávy se nesmějí opakovat: nová
žádost vydá nový jednorázový token a šifrovanou zprávu ve stejné DB transakci.

Postup a akceptace:

1. Připravit čtecí produkční inventuru právě dvou zrušených `public reset`
   zpráv z incidentu. Ověřit dva různé stále oprávněné účty, aktuální adresu,
   nepřítomnost novější aktivní žádosti a běžící rodičovský worker. Do výstupu
   dát pouze identifikátory starých frontových řádků a bezpečné souhrny.
2. Změnit TTL obvyklé rodičovské obnovy z 1 hodiny na 72 hodin a upravit text.
   Synteticky ověřit hranici expirace, jednorázovost, idempotenci řízené
   náhrady a odmítnutí změněného účtu/adresy či novější žádosti.
3. Po CI, merge a nasazení spustit znovu čtecí inventuru. Chráněná jednorázová
   operace musí v jedné transakci vystavit právě dva nové tokeny, zařadit dvě
   zprávy a označit staré řádky jako nahrazené. Opakovaný běh nic nevydá.
4. Přirozeným během ověřit `pending → sent/failed/cancelled` a platnost nových
   tokenů 72 hodin bez jejich zveřejnění. `sent` znamená přijetí `mail()`, ne
   potvrzené doručení do schránky; kontrola schránky zůstává samostatná.

Závislost: dostupný produkční worker AUTH-MAIL-01. Záložní provozní režim
navrhnout po tomto urgentním kroku; druhý transport bez důkazu nedoručení
nesmí automaticky přeposílat zprávu s nejistým výsledkem.

Výsledky: [protokol AUTH-MAIL-02](verification/auth-mail-reset-20261121/RESULTS.md),
[registr](WORK_PLAN_CURRENT.md#auth-mail-02--třídenní-obnova-a-náhrada-dvou-odkazů-21-11-2026).

Produkční výsledek v [dokumentačním PR #511](https://github.com/KovoPraha/evidenceTreninku/pull/511): [deploy 44908781060](https://github.com/KovoPraha/evidenceTreninku/actions/runs/44908781060) SUCCESS;
[čtecí inventura 44909136270](https://github.com/KovoPraha/evidenceTreninku/actions/runs/44909136270) PASS pro staré řádky 3 a 8;
[jednorázové vydání 44909270205](https://github.com/KovoPraha/evidenceTreninku/actions/runs/44909270205) vytvořilo nové řádky 10 a 11 s expirací 24. 11. 2026 ve 13:31:35 UTC. Přirozený běh
oba předal PHP `mail()` a zanechal `pending=0`, `failed=0`; skutečné doručení
do obou schránek je stále **NOT RUN**. Původní zrušené odkazy zůstaly neplatné.

## AUTH-MAIL-03 — záložní provoz rodičovské pošty (21. 11. 2026)

**Stav: KONCEPT.** Vlastník požádal o záložní režim; rozsah druhého odesílatele
ani provozní aktivace zatím nejsou určené. Vlastník návrhu vývoj KIS; evidence
ve větvi `codex/auth-mail-reset-release-proof-20261121` z `origin/main`
`ccac61ab`. Cíl: včas odhalit zastavený plánovač či stárnoucí `pending` a mít
bezpečnou náhradní cestu bez duplicitního odesílání jednorázových odkazů.

První etapa: nezávislá čtecí kontrola každých několik minut sleduje stáří
heartbeatu, splatné rodičovské `pending`, `failed` a chybějící klíče. Výsledek
bez adres/tokenů vytvoří provozní upozornění; konkrétní komunikační kanál
správce se musí určit. Existující chráněná akce `process` umožňuje řízené
náhradní spuštění stejného workeru po diagnostice. Claim, zámek a validace
tokenu brání souběžnému převzetí stejné zprávy. Automatické opakování zprávy,
kterou `mail()` mohlo přijmout, není přípustné.

Druhá etapa, pokud se prokáže výpadek transportu nebo nedoručitelnost:
zvolit a nakonfigurovat nezávislý ověřitelný transport s provozními logy.
Přepnutí pouze při prokazatelném odmítnutí před přijetím; neznámý výsledek
zůstane k prověření. Tajemství poskytovatele zůstanou mimo Git. Test na
řízených schránkách musí prokázat obě cesty a chování při výpadku.

Alternativy: druhý identický CRON na stejném hostingu nepokrývá výpadek
hostingu; automatické přeposlání všeho `pending`/`sent` vytváří duplicitní
odkazy a nemá důkaz nedoručení. Akceptace: upozornění na vynechané tick-y,
bezpečné řízené obnovení běhu a teprve poté, pokud je potřeba, ověřená
alternativní doručovací cesta. Další krok: rozhodnout provozní upozornění a
ověřit doručení současného transportu; poté samostatně implementovat a
testovat první etapu. [Registr](WORK_PLAN_CURRENT.md#auth-mail-03--záložní-provoz-rodičovské-pošty-21-11-2026).

## AUTH-MAIL-01 — oprava automatického zpracování rodičovské fronty (21. 11. 2026)

**Stav: AUTOMATIKA NASAZENA A AKTIVNÍ; doručení do schránky K OVĚŘENÍ.** Vlastník: vývoj KIS;
větev `codex/auth-mail-worker-repair-20261121`, oddělený worktree z `origin/main`
`11505aed`, převzaté souběžné `main` `5acd225f` v PR #506. Zadání vlastníka: opravit automatické zpracování ověření účtu a obnovy
hesla rodičů, bezpečně převzít existující frontu, neřešit výpadek ručním retry.
Podklad hlášení: tři `pending`, nula `processing` a `failed`, šest `sent`; dva
starší příklady mají nula pokusů a prošlý čas `available_at`. Konkrétní adresy
ani tokeny se do Gitu nezapisují. Výchozí počty následně potvrdil čtecí produkční
status; viz [protokol](verification/auth-mail-worker-20261121/RESULTS.md).

**Potvrzená příčina:** `bin/club-event-notifications.php` zpracovává obě
fronty, ale v repozitáři není jeho produkční schedule ani instalátor hostitelského
CRONu. `trainer-access-operations.php` filtruje pouze `target_type=trainer` a
`kind=reset`. Čtecí produkční běh potvrdil chybějící plánovač i poslední běh
společného workeru `null`, tři splatné rodičovské zprávy a připravené schéma,
klíče i místní transport. Konfigurace
odesílání používá PHP `mail()`; projekt nemá vlastní SMTP klient ani SMTP
přihlašovací údaje.

**Volba a rozsah:** před zapnutím výdeje doplnit read-only diagnostiku přes
chráněné GitHub Actions. Poté zavést samostatný vlastněný hostitelský plán pro
rodičovské `verify` a `reset`, který nevyzvedne trenérské resety ani klubové
notifikace. Využije stávající claim/token validaci a omezení pokusů; nebude
vytvářet účty ani nové odkazy. Alternativa spustit nyní společný worker by
zpracovala i nezkontrolovanou klubovou frontu. Alternativa ruční retry jednotlivých
řádků neřeší automatiku a může vytvořit nejisté duplicitní doručení.

Postup a akceptace:

1. Čtecím během zjistit skutečnou konfiguraci, stav due/failed a CRONu bez
   příjemců, tokenů a těla zpráv. Rozlišit chybějící plán od chyby bootstrapu.
2. Na syntetické DB ověřit oddělený výběr veřejných `verify`/`reset`, expiraci,
   souběh, odmítnutý transport a stárnoucí claim. `failed` má mít viditelný důvod;
   známé odmítnutí dodržuje pět pokusů, nejisté doručení se neopakuje automaticky.
3. Vydat kód a provozní instalátor; před aktivací znovu číst produkční frontu,
   zejména platnost starých tokenů a konfiguraci klíčů. Instalovat pouze vlastní
   CRON soubor atomicky, s kontrolou identity, zámkem a heartbeat. Cizí úlohy
   neměnit.
4. Ověřit přirozený tick, `pending → sent/cancelled/failed`, počet pokusů a
   skutečné doručení obou typů na řízené TEST schránce. `sent` dokládá přijetí
   transportem, nikoli doručení do schránky. Dva přirozené tick-y a bezpečné
   vypořádání staré fronty **PASS**; kontrolní schránka a doručení obou typů
   **NOT RUN**. Přetrvávající skutečné nedoručení vyžaduje samostatné prověření
   hostitelského mail transportu a jeho logů.

Výsledky a předání: [protokol](verification/auth-mail-worker-20261121/RESULTS.md),
[registr](WORK_PLAN_CURRENT.md#auth-mail-01--rodičovské-ověřovací-a-obnovovací-e-maily-21-11-2026).

Implementace v [PR #506](https://github.com/KovoPraha/evidenceTreninku/pull/506): `parent-auth-mail-operations.php` má `status`, `install`
a `process`. `status` čte souhrny bez příjemců a tokenů, poslední běh původního
společného workeru a vlastní heartbeat. Chráněný workflow
`parent-auth-mail-production.yml` kontroluje hash nasazeného kódu; po vydání
nejprve spustit `status` s potvrzením `OVERIT-RODICOVSKOU-POSTU`. Po
vyhodnocení výsledku spustit `install` s `ZAPNOUT-RODICOVSKOU-POSTU`; obojí
provedeno 21. 11. 2026.
Hostitelský minutový CRON používá neblokující zámek a limit 20 zpráv na běh;
`process` vybírá pouze `public` ověření/obnovu a `child` obnovu. Instalátor
odmítne cizí soubor a nemění jiné crony. Výchozí vydání samotného kódu nic
neodesílá. Vypršelé nebo nahrazené odkazy worker zruší; uživatel si pak vyžádá
nový odkaz. První přirozený běh zrušil dva staré resety a transport přijal jedno
ověření. Druhý běh potvrdil pravidelnou aktivitu bez další práce. Klubových sedm
čekajících oznámení zůstalo netknutých. Syntetické testy ani `mail()` neprokazují
doručení do schránky.

Trenérská obnova připravená 31. 10. 2026 používá stejnou šifrovanou frontu,
ale vlastní tokeny a `target_type=trainer`. Její samostatný chráněný worker
`trainer-access-production.yml` vybírá výhradně trenérské obnovy; nezapíná
historickou veřejnou nebo klubovou frontu. Plánovaný interval je pět minut,
skutečný provoz a doručení je třeba ověřit. Podmínky a přejímka jsou v
[přípravě trenérů](TRAINER_READINESS_2026-10-31.md); nasazení v CURRENT_STATE.

Fronta je součástí kódu `main` od 19. 10. 2026; lokální doplnění diagnostiky a testů z 20. 10. 2026 samo o sobě není nasazením. Produkční doručení zatím nebylo ověřeno. Worker vyžaduje migraci `20261019100000_auth_mail_outbox` a provozně ověřený plán `bin/club-event-notifications.php`; samotný úspěch HTTP požadavku ani `mail()` doručení nedokazuje.

## Datový tok

Registrace uloží účet a hash ověřovacího tokenu a ve stejné DB transakci zařadí e-mail do `auth_mail_outbox`. Obnova hesla podobně uloží hash do `password_reset_tokens` a zašifrovanou zprávu do fronty. Původní dosud platný resetovací odkaz nová žádost neodvolá; spotřebování jednoho odkazu odvolá všechny aktivní tokeny daného účtu. Veřejná odpověď neprozrazuje existenci adresy. `booking/znovu_overit_email.php` vydá nový ověřovací token a zruší čekající staré zprávy.

Obsah zprávy s odkazem je v databázi šifrovaný XChaCha20-Poly1305. Pro podklíč se používá existující `PERSON_RC_KEYRING` a aktivní verze; při rotaci je nutné ponechat staré verze, dokud neodejdou nebo nevyprší všechny zprávy v dané verzi. Indexovací klíč `PERSON_RC_INDEX_KEY` zůstává samostatný. Token v URL je ve fragmentu, aby se nedostal do serverových access logů cílové stránky; prohlížeč jej předá až POST formulářem. Žádný admin náhled plaintextu/tokenů neexistuje.

CLI worker střídá `auth_mail_outbox` a `club_event_notifications` do společného limitu. Před odesláním znovu kontroluje stav tokenu, cílového účtu, e-mailu a u dětského resetu i platnou vazbu rodiče. Po pěti známých odmítnutích transportu zůstane zpráva `failed`; mezi pokusy platí prodleva. Staré/neplatné tokeny se zruší bez odeslání. Výjimka transportu, selhání uložení výsledku nebo převzetí starší 15 minut znamenají nejasné doručení: úloha zůstane k ruční kontrole a automaticky se neopakuje. Nejde o garanci doručení právě jednou; před ručním opakováním musí správce ověřit původní výsledek.

Před převzetím první zprávy se ověří struktura obou front; chybějící fronta tedy nezpůsobí částečné odeslání z druhé. Parametry `--limit=1..99`, `--transport=mail|local-outbox` a výstup běžného zpracování zůstávají zachovány.

Správce v `auth_mail_admin.php` vidí stavy, příjemce, typ, pokusy a chybu. Pouze stále platnou selhanou zprávu smí s důvodem a potvrzením auditovaně vrátit do fronty. Obecný provozní přehled signalizuje `failed`. Uživateli vypršelého odkazu nabídněte nový odkaz, nikoli ruční znovuodeslání starého.

## Kontrola bez odesílání

S explicitním `APP_HOST` a nakonfigurovanou databází lze spustit:

```text
php bin/club-event-notifications.php --status
```

Tento režim provádí pouze čtení. Nevyzvedává zprávy, nemění jejich stav, neposílá
poštu a nevytváří lokální adresář ani soubory. JSON obsahuje počty podle stavu,
počet zpráv připravených k odeslání, stáří nejstarší čekající zprávy v sekundách,
počet převzetí starších 15 minut a nedostupných verzí šifrovacích klíčů.
Neobsahuje příjemce, obsah zpráv, tokeny, hashe ani texty chyb transportu.

`ready=true` znamená dostupnost ověřovaných struktur front, konfigurace šifrování
a lokálních předpokladů zvoleného transportu. Neověřuje všechny registrační
tabulky ani platnost každého odkazu; tu worker kontroluje při zpracování.
`attention_required=true` signalizuje chybějící předpoklad, selhanou zprávu,
zastaralé převzetí nebo čekající zprávu starší 15 minut. Návratový kód je `0`
pouze pro připravenou frontu bez těchto upozornění; jinak `1` (neplatné CLI
argumenty `64`). Chyba připojení/konfigurace skončí `1` bez citlivého detailu.

`scheduler_verified=false` a `mailbox_delivery_verified=false` jsou výslovné
meze této diagnostiky. Existující plán zpráv soupiskám není důkazem běhu tohoto
workeru. U `mail` se neprovádí síťový test SMTP. U `local-outbox` se kontroluje
localhost a zapisovatelný existující nadřazený adresář bez zkušebního zápisu.

Test `TransactionalNotificationWorkerTest` používá výhradně SQLite v paměti,
náhodné testovací klíče a adresy `example.test`. Ověřuje skutečné uložení
rozšifrovaného ověřovacího e-mailu a klubové zprávy do dočasného lokálního
outboxu, společný limit obou front, selhání a opakování po prodlevě, zrušení
neplatného tokenu i read-only diagnostiku se zapnutým SQLite `query_only`.
Nezapojuje aplikační databázi, SMTP ani produkční plán.

## Předprodukční a produkční ověření

1. Nasadit migraci a ověřit `bin/migrate.php` v režimu `check`; bez migrace registrace bezpečně selže bez částečného účtu.
2. Zkontrolovat dostupnost všech potřebných verzí `PERSON_RC_KEYRING`, šifrovacího rozšíření Sodium a aktivního CLI CRON bootstrapu z `docs/thinline-deploy-runbook.md`.
3. Na vyhrazené TEST adrese vytvořit registraci, ověřit `pending → sent`, použít odkaz jednou, opakované použití odmítnout. Ověřit resend a reset, včetně selhání transportu a obnovy.
4. Zkontrolovat `auth_mail_admin.php?status=failed` a `eshop_notifications_admin.php?status=failed`, čas nejstarší čekající zprávy a skutečné doručení v testovací schránce. Při opakovaném selhání transportu neoznačovat uživateli zprávu za doručenou.

Rezervační cesty již používají producenty fronty; starší audit F10 zachycuje
historický stav. Aktuální transportní adaptéry `mail()` jsou v klubových
oznámeních, soupiskových zprávách, rodinných souhrnech a upomínkách.
Soupiskový worker a jeho plán jsou samostatné. WebPush v rezervaci zůstává
oddělený best-effort efekt. Inventuru a syntetické kontrakty uvádí
[W11](REFACTORING_W11_2026-10-30.md); nejsou důkazem skutečného doručení.
