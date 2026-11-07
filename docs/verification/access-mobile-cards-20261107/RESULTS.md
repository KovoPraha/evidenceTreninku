# ROLE01-MOB — mobilní karty lidí a přístupů

7. 11. 2026, [plán](../../ACCESS_OVERVIEW_2026-11-07.md),
[rozhodnutí](../../DECISION_LOG.md#d2026-11-07-06).
**Implementace a nasazení HOTOVO; vizuální přejímka nových karet NOT RUN.**

## Implementace a předání

[PR #361](https://github.com/KovoPraha/evidenceTreninku/pull/361) sloučen jako
`3b37e0537f50f7064ae2d8a84b5a1daee4af50aa`. Pod 768 px se místo tabulky
zobrazují sémantické karty: jméno, kontakt, stav, čtyři skupiny rolí, výchozí
pozice, superadmin, samostatná vlastní spotřeba a přímý GET odkaz do editoru.
Desktop zachovává tabulku; obě varianty používají stejný filtrovaný seznam.
Pouze prezentační šablona, žádná změna autorizace, zápisů nebo schématu.

Původní vlastní větev `codex/access-visual-uat-20261107`; následné výsledkové
předání `codex/access-mobile-release-20261107`, základ `3b37e053`, stejný worktree
`C:/Users/Marek/Documents/Codex/evidencePavel-access-overview-20261107`.
Výsledky jsou commitnuté a pushnuté v [otevřeném dokumentačním PR #365](https://github.com/KovoPraha/evidenceTreninku/pull/365),
zatím mimo main; runtime tím nemění. Stav CI/přijetí ověřit u tohoto PR.

## Ověření

- PASS: PHP syntaxe, PSR-12 a délka řádků (1 změněná PHP šablona), dokumentační
  kontrakt a `git diff --check`.
- PASS: 44 místních cílených testů / 2413 assertions přehledu, pozic, životního
  cyklu a baru. Syntetické vykreslení 3 karet, 1 desktopové tabulky a 6
  odpovídajících odkazů editoru; jde o HTML kontrolu, nikoli vizuální důkaz.
- PASS: všech 11 CI kontrol přesné hlavy `00b6292d239872ce6a6a28987bfbd02d1eee95c8`,
  včetně obou MariaDB 10.3/11.4 a profilů čtení, PHPStan, stylu, dokumentace a CodeQL.
  [Testy 42365319385](https://github.com/KovoPraha/evidenceTreninku/actions/runs/42365319385):
  2344 testů / 43433 assertions; unixové hranice samostatně 3 / 45.

## Produkční vydání a zachování rolí

Vlastník výslovně schválil karty, merge/deploy a následně i společné nasazení
již sloučené cenové optimalizace #358. Původní omezení jejího deploye bylo pro
toto konkrétní vydání nahrazeno tímto potvrzením.

- [Deploy 42365946802](https://github.com/KovoPraha/evidenceTreninku/actions/runs/42365946802)
  SUCCESS, přesně `3b37e053`, 7. 11. v 10:59 UTC. [Bezpečný souhrn](deploy-summary.json).
- Testy během deploye 2344 / 43433 PASS. Ověřená záloha 389 tabulek / 2 triggery;
  obnova této generace NOT RUN. Migrace current / pending []; dosavadní VS zachované.
- Import kroužků false, globální UAT flag false. Žádná nová aktivace dalších funkcí.
- Závěrečný HTTP smoke 200 z produkčního serveru. GitHub runner měl TCP timeout;
  použit dokumentovaný serverový HTTP fallback, nejde o přihlášené UI UAT.
- Čtecí baseline [42364034851](https://github.com/KovoPraha/evidenceTreninku/actions/runs/42364034851)
  a následný čtecí baseline [42366277370](https://github.com/KovoPraha/evidenceTreninku/actions/runs/42366277370)
  PASS, celé snapshoty včetně seznamu ID přesně shodné. V Gitu jen souhrny bez ID:
  [před](access-before.json), [po](access-after.json).
- Všech 48 účtů, 82 přiřazení, 5 superadminů a 1 barový grant shodných před/po.
  SHA-256 `7cb89121cb73926ba98601ee9e4b04d3684229ab1b966a99585c28de3fbcdd41`.
- Žádný zapisující drill, nové identity, změna role, výchozí pozice, grantu či
  aktivace účtu. Provozní kontrola použila výhradně čtecí fázi baseline.

## Otevřená vizuální přejímka

Computer Use při pokusu otevřít syntetickou místní ukázku opakovaně neodpovídal;
selhalo i obnovení čtecího připojení ke známé KIS kartě. Nebyl vrácen úspěšný
snímek nové verze. Vlastník byl požádán obnovit připojení Chrome, potvrzení
obnovy zatím nepřišlo. Místní server 8138 zastaven. Žádné osobní snímky v Gitu.

Nové mobilní karty proto nejsou označené jako vizuálně převzaté. Starší úspěšný
[Chrome průchod](../access-visual-20261107/RESULTS.md) se týká původní tabulky.
Fyzický telefon ani Safari nebyly nyní ověřené.

Další krok po obnovení spojení: skutečný přihlášený Chrome / produkce 390 a
360 px, role pohromadě se jménem bez bočního posunu, filtr Bar, hledání,
prázdný výsledek, GET otevření editoru existujícího neaktivního technického účtu
#64, desktopová tabulka. Nic neukládat ani neaktivovat, nepřepínat pracovní roli,
nezaložit účet. Vrátit dočasný viewport a uložit bezpečný vizuální důkaz.
