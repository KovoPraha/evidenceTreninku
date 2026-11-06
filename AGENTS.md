# Pokyny pro práci v repozitáři

Před prací přečti [vývojový kontext](CLAUDE.md), [aktuální stav](docs/CURRENT_STATE.md),
[session handoff](docs/HANDOFF_CURRENT.md), [společný průběžný plán](docs/WORK_PLAN_CURRENT.md)
a [pravidla souborové dokumentace](docs/WORK_DOCUMENTATION_POLICY.md).

Vlastník 6. 11. 2026 výslovně požaduje průběžný zápis přímo do souborů repozitáře.
Každá rozpracovaná, dokončená i plánovaná práce musí mít dohledatelný záznam a odkazy
na související dokumenty. Udržuj příslušný plán, datovaný session handoff a důkazy;
při drobné změně aktualizuj existující záznam. Samotná zpráva v chatu nestačí.

Před předáním nebo ukončením session zapiš výsledek, skutečné ověření, otevřené kroky,
závislosti a další konkrétní postup. Zachovej historické protokoly a odkaž z nich na novější stav.
Aktuální nasazení a provozní přepínače mají jediný kanonický souhrn v CURRENT_STATE.

Chraň cizí rozpracované změny a přidávej jen vlastní konkrétní cesty. Do repozitáře
nepatří hesla, konfigurace, osobní údaje, session, databázové dumpy ani soukromé přílohy;
zaznamenej pouze bezpečné souhrny, kontrolní výsledky a odkazy na řízené postupy.

Dokumentaci ověř `php tools/check-current-docs.php` a `git diff --check`;
CURRENT_STATE zachovej do 80 řádků a zkontroluj také odkazy v nových souborech.
