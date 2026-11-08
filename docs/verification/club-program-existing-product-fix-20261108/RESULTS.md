# Oprava průvodce kroužkem z existujícího produktu

Datum: 8. 11. 2026. Výchozí větev: `origin/main` na `755b20a22a4401f54fd8117d8098a89e8603b77c`.

## Zjištění a oprava

V režimu `source_mode=existing` formulář neposílá `name`. Původní
`cpwSimpleDefaults()` tím předával prázdný `team_name` do založení soupisky,
ačkoliv samotné založení programu načítalo název z produktu. Předvyplnění
nyní vybírá variantu z načteného seznamu produktů a přebírá veřejný název
(`public_name`, jinak `name`) a veřejný popis (`public_summary`, jinak
`short_description`). Chybějící popis doplní jako `Kroužek <název>`.
Stejnou volbu textů používá i služba pro založení programu. Režim nového
produktu dál používá uživatelem zadaný název a popis.

## Ověření

- Integrační scénář se syntetickým produktem ID 298 a variantou ID 398:
  `Aligátoři`, 36 Kč, kapacita 30, automatická soupiska se správným názvem,
  převzatý veřejný popis a jediný produktový záznam.
- Regresní scénář vytvoření nového produktu a fallback popisu bez veřejného
  textu.
- Cílená sada `ClubProgramWizardTest`, `ClubProgramWizardWiringTest`,
  `ClubProgramTermDefaultsTest` a `LightweightEshopWorkflowWiringTest`.

Testy používají izolovanou SQLite databázi. Skutečný produkt ID 298 nebyl
upravován, objednávky ani skladové pohyby nebyly měněny. Produkční průchod
vyžaduje samostatné nasazení změny a následnou kontrolu v přihlášeném KIS;
výsledek syntetického testu není důkazem tohoto průchodu.
