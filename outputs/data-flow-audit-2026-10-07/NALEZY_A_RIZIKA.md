# Nálezy a rizika – aktualizace 7. 10. 2026

Historický audit z 24. 8. 2026 je zachovaný v `outputs/data-flow-audit-2026-08-24`. Níže je současný stav po kontrole kódu, dokumentace a workflow.

## Souhrn

| Priorita | Otevřeno | Stav |
|---|---:|---|
| HIGH | 0 | dřívější paralelní writery, soubory a webové DDL jsou uzavřené |
| MEDIUM | 1 | zbývá sjednocení starších přímých e-mailů |
| Rozhodnutí | 0 | veřejný bearer je potvrzený jako read-only |

## Vyřešené položky

1. Legacy zápis závodů je fail-closed a kanonické writery jsou jednotné.
2. Starý KIS wizard je preview-only; změny vyžadují fingerprintovaný promote/rollback.
3. Přílohy tréninků a závodů používají staging, finalize a kompenzaci.
4. Legacy import výsledků vrací 410 bez uploadu a databázových změn.
5. Aplikační URL používají `APP_BASE_URL` / `appUrl()`.
6. UCI temp je mimo webroot v private storage.
7. Webový bootstrap nespouští DDL; migrace vlastní CLI/deploy.
8. Veřejný profilový token je jen pro čtení. Zápis poznámky vyžaduje přihlášeného sportovce nebo schválený účet `self`/`guardian`.
9. Fio nemá automatický časový plán a nevytváří periodické chybové běhy při `FIO_IMPORT_ENABLED=false`.
10. Aktuální stavové dokumenty uvádějí poslední ověřený release, UAT readiness a invarianty.

## M2 – starší přímé e-maily mimo trvalou frontu

Závažnost: **MEDIUM**
Stav: **otevřeno**

Přímé `mail()` zůstává zejména v registraci, obnově hesla, rezervacích individuálních lekcí, čekací listině, starém cronu upomínek a hromadném e-mailu. Databázová změna může uspět, zatímco transport zprávu nepřevezme; společný retry a jednotný audit nejsou ve všech těchto cestách.

Řešení má rozšířit existující `club_event_notifications`/worker, ne zavést další paralelní frontu. Notifikace musí vzniknout ve stejné transakci jako doménová změna, mít idempotentní klíč, snapshot příjemce a textu, claim, retry a administrativní dohled. UI má uvádět „oznámení zařazeno“, nikoli tvrdit doručení.

Tento řez nebyl v této aktualizaci automaticky aktivován, protože skutečné rozesílání vyžaduje provozní rozhodnutí o textech, odesílateli, frekvenci a monitoringu. Kódové nasazení nesmí samo zapnout nové produkční e-maily.

## Provozní hranice Fio

Fio je vědomě pozastavené. `fio-import-production.yml` je ručně spustitelné, ale nemá `schedule`. Produkční konfigurace má zůstat vypnutá. Budoucí obnovení musí samostatně ověřit read-only token, shodu IBAN, testovací import, návrhy shod a odpovědnost za ruční potvrzení.

## Pozitivní kontrolní body

- checkout používá serverový přepočet, zámky, fingerprint a idempotenci;
- bankovní, SumUp a Stripe platby používají kanonický platební přechod;
- programy, akce a velodrom se aktivují až po platbě;
- produkční deploy má zálohu, migrace, release aktivaci a smoke;
- read-only readiness a invarianty mají strojově čitelné výsledky;
- veřejný bearer už neuděluje právo zápisu;
- Fio je provozně vypnuté bez odstranění připravené integrace.
