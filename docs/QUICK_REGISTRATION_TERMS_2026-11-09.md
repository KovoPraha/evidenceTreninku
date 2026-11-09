# QR01 — verze podmínek rychlé přihlášky

Stav: K OVĚŘENÍ release / implementace a izolované testy hotové. Vlastník: vývoj na přímé zadání 9. 11. 2026. Větev `codex/quick-registration-terms-20261109` z `origin/main`; izolovaný worktree `C:/xampp/htdocs/evidencePavel-quick-registration-terms-20261109`.

## Cíl a rozsah

Opravit chybu při rodičovské rychlé přihlášce produktu 298: přenést přesné verze všech aktuálních registračních podmínek z formuláře do serverové kontroly. Zachovat potvrzení platného znění, volitelný souhlas ČSC a atomické vytvoření účtu, žádosti, objednávky a platby. Testovací objednávky vytvářet pouze v izolované syntetické databázi; produkční obsah a cenu produktu neměnit.

## Zjištění a postup

V aktuálním `booking/rychla_prihlaska.php` je v atributu `name="term_version[... ]"` skutečný konec řádku a mezery před klíčem účelu. Prohlížeč je odesílá jako část názvu; PHP pak nevytvoří klíče, které očekává `athleteRegistrationSubmit()`. Smyčka jinak vypisuje všechny položky z `athleteRegistrationCurrentTerms()`, včetně volitelného ČSC, je-li dostupné. Opravit přesný atribut `name`; ověřit vykreslené HTML a parsování formuláře, aktuální verze po obnově, stale verzi a rollback. Ověřit také publikaci podmínek mezi načtením a POST.

Přejímka: čerstvý formulář odesílá přesné klíče a verze; čerstvá objednávka projde; stará verze je odmítnuta a nezanechá účet, žádost, objednávku ani platbu; nové načtení přijme novou verzi. Zvlášť ověřit volitelný souhlas ČSC včetně nezvoleného stavu.

## Stav předání

Úzká oprava HTML atributu hotová; [izolované výsledky](verification/quick-registration-terms-20261109/RESULTS.md) PASS. Testovací fixture doplněna o migraci pracovních upozornění, kterou nynější checkout potřebuje. [PR #416](https://github.com/KovoPraha/evidenceTreninku/pull/416) otevřený, CI běží; merge, deploy a skutečné rodičovské UAT zatím NOT RUN. Živou přejímku produktu 298 je třeba provést v kontrolovaném provozním okně.
