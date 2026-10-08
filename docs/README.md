# Dokumentace EvidencePavel / KIS

Aktualizováno: 8. 10. 2026, Europe/Prague

Evidence tréninků, e-shop a KIS jsou jedna aplikace s jedním repozitářem,
webrootem, migračním katalogem a kanonickou databází osob. Produkce běží na
<https://kis.kovopraha.cz/>.

## Začněte zde

| Dokument | Účel |
|---|---|
| [Aktuální stav](CURRENT_STATE.md) | Poslední ověřené SHA, CI, deploy, funkční rozsah a provozní hranice |
| [Aktuální předání](HANDOFF_CURRENT.md) | Bezpečné převzetí práce na jiné stanici a synchronizace s GitHubem |
| [Produkční nasazení](NASAZENI.md) | Ruční chráněný deploy z GitHub Actions, zálohy a řešení chyb |
| [Produkční UAT](PRODUKCNI-UZIVATELSKE-TESTOVANI.md) | Identity, brány, testovací data a bezpečný úklid |
| [Localhost testování](localhost-testovani.md) | Příprava izolované lokální databáze a syntetických dat |
| [Pravidla migrací](../migrations/README.md) | Neměnné číslované databázové migrace |

Při rozporu má přednost čerstvý `origin/main`, skutečné workflow, migrace a
testy, potom `CURRENT_STATE.md` a `HANDOFF_CURRENT.md`.

## Tematická dokumentace

| Oblast | Dokumenty |
|---|---|
| Klubové programy a kroužky | [club-programs.md](club-programs.md), [training-roster-bridge.md](training-roster-bridge.md), [kis-roster-policies.md](kis-roster-policies.md) |
| E-shop a objednávky | [shop-checkout-k4.md](shop-checkout-k4.md), [shop-beneficiaries.md](shop-beneficiaries.md), [shop-account-person-roles.md](shop-account-person-roles.md), [shop-catalog-publication.md](shop-catalog-publication.md) |
| Platby | [shop-program-payment-verification.md](shop-program-payment-verification.md), [fio-readonly-import-k4.md](fio-readonly-import-k4.md), [stripe-integration-plan.md](stripe-integration-plan.md) |
| Akce a soupisky | [club-events-k3.md](club-events-k3.md), [club-event-roster-targets.md](club-event-roster-targets.md), [kis-teams-rosters.md](kis-teams-rosters.md) |
| Bezpečnost a osoby | [auth-one-time-tokens.md](auth-one-time-tokens.md), [auth-revocation-rate-limit.md](auth-revocation-rate-limit.md), [rodne-cislo-bezpecnost.md](rodne-cislo-bezpecnost.md), [pravidla-shody-osob.md](pravidla-shody-osob.md) |
| Současná testerová vlna | [UAT-OPRAVY-2026-10-07.md](UAT-OPRAVY-2026-10-07.md) |
| UX navigace a kalendáře | [UX-NAVIGACE-A-KALENDARE-2026-10-08.md](UX-NAVIGACE-A-KALENDARE-2026-10-08.md) |
| Datové toky a rizika | [grafická mapa XLSX](../outputs/data-flow-audit-2026-10-07/DATOVE_TOKY_APLIKACE.xlsx), [aktuální mapa kódu](../outputs/data-flow-audit-2026-10-07/DATOVE_TOKY_KOD.md), [aktuální nálezy](../outputs/data-flow-audit-2026-10-07/NALEZY_A_RIZIKA.md), [historický audit 24. 8.](../outputs/data-flow-audit-2026-08-24/DATOVE_TOKY_KOD.md) |

## Aktuální funkční oblasti

- evidence tréninků, měření, sportovců, skupin, podskupin a závodů;
- plánovač tréninků, soupisky, RSVP a trenérský přehled účasti;
- rodinné a sportovní účty a vazby na kanonické osoby;
- e-shop pro zboží, kroužky, programy, členství a placené klubové akce;
- klubové programy, období, kapacity, podmínky a přechod do soupisek;
- klubový kalendář, události, soustředění a registrace;
- členské příspěvky, bankovní platby a administrativní párování;
- veřejný velodrom, individuální lekce a rezervace sportovišť;
- audit, pracovní pozice, oprávnění, importní kontroly a exporty.

## Technologie

- PHP 8.2+, procedurální aplikační vrstva a PDO;
- MariaDB; CI ověřuje kompatibilitu s 10.3 a 11.4;
- Bootstrap 5.3.3, Bootstrap Icons 1.11.3 a vanilla JavaScript;
- Composer se zamčenými verzemi v `composer.lock`;
- PHPUnit 11;
- Apache/XAMPP lokálně a chráněný SSH/rsync deploy z GitHub Actions.

## Historické dokumenty

Datované audity, roadmapy, staré handoffy, implementační prompty a soubor
`plan-eshop-tymova-evidence/SESSION_HANDOFF.md` zůstávají v repozitáři jako
auditní a rozhodovací stopa. Jejich SHA, počty testů, verze a označení
„plánováno“ popisují dobu vzniku. Nejsou autoritou pro aktuální checkout.

Velké dokumenty `uzivatelska-prirucka.md`, `technicka-dokumentace.md`,
`databazove-schema.md` a `vyvojarsky-pruvodce.md` jsou historický základ
baseline 2.20. Aktuální doplňky jsou vedené tematicky a přesný stav určuje kód,
migrace a testy.
