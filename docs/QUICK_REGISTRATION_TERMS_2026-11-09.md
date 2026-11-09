# QR01 — verze podmínek rychlé přihlášky

Stav: implementace a produkční release HOTOVO; živé rodičovské UAT otevřené. Vlastník: vývoj na přímé zadání 9. 11. 2026. Větev důkazu vydání `codex/quick-registration-terms-release-proof-20261109`; izolovaný worktree `C:/xampp/htdocs/evidencePavel-quick-registration-terms-20261109`.

## Cíl a rozsah

Opravit chybu při rodičovské rychlé přihlášce produktu 298: přenést přesné verze všech aktuálních registračních podmínek z formuláře do serverové kontroly. Zachovat potvrzení platného znění, volitelný souhlas ČSC a atomické vytvoření účtu, žádosti, objednávky a platby. Testovací objednávky vytvářet pouze v izolované syntetické databázi; produkční obsah a cenu produktu neměnit.

## Zjištění a postup

V aktuálním `booking/rychla_prihlaska.php` je v atributu `name="term_version[... ]"` skutečný konec řádku a mezery před klíčem účelu. Prohlížeč je odesílá jako část názvu; PHP pak nevytvoří klíče, které očekává `athleteRegistrationSubmit()`. Smyčka jinak vypisuje všechny položky z `athleteRegistrationCurrentTerms()`, včetně volitelného ČSC, je-li dostupné. Opravit přesný atribut `name`; ověřit vykreslené HTML a parsování formuláře, aktuální verze po obnově, stale verzi a rollback. Ověřit také publikaci podmínek mezi načtením a POST.

Přejímka: čerstvý formulář odesílá přesné klíče a verze; čerstvá objednávka projde; stará verze je odmítnuta a nezanechá účet, žádost, objednávku ani platbu; nové načtení přijme novou verzi. Zvlášť ověřit volitelný souhlas ČSC včetně nezvoleného stavu.

## Stav předání

Úzká oprava HTML atributu v [PR #416](https://github.com/KovoPraha/evidenceTreninku/pull/416) prošla 11 kontrolami a byla sloučena jako `5977897f44b60acbf54a2722ade1c63db75b8ae6`. Před vydáním odhalen navazující případ: po odmítnutí staré verze stránka ponechá původní potvrzení zaškrtnuté. [PR #419](https://github.com/KovoPraha/evidenceTreninku/pull/419) doplnil opětovné načtení verzí a zrušení dřívějšího potvrzení při změně kterékoliv registrační nebo programové verze; 11 kontrol PASS, merge `e3f299037517c35b96752edfbf40dfa0e8e41f94`. Vlastník schválil ruční produkční nasazení. [Workflow 42637712723](https://github.com/KovoPraha/evidenceTreninku/actions/runs/42637712723) vydal přesně `e3f2990` s `uat_schvaleno=false` a `importovat_krouzky=false`; záloha, migrace a HTTP smoke PASS. Veřejný formulář produktu 298/varianty 952 vrací aktuální skrytá pole všech pěti registračních a dvou programových verzí. [Výsledkový protokol](verification/quick-registration-terms-20261109/RESULTS.md) odděluje syntetický celý proces a produkční čtení. Živé rodičovské vytvoření objednávky produktu 298 a skutečná platba zůstávají NOT RUN.
