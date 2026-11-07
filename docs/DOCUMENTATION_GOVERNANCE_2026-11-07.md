# Souborová dokumentace a stručné předání — 7. 11. 2026

Stav: K OVĚŘENÍ / GitHub předání. ID DOC01. Zadání vlastníka: všechny záměry, koncepty,
implementační plány, změny, výsledky a rozhodnutí vést ve verzovaných souborech;
průběžně aktualizovat CURRENT a session handoff, historii oddělit a zpřístupnit
vývojářům přes GitHub. Interní paměť ani chat nejsou projektovou evidencí.

## Rozsah a pracovní kopie

- Pouze dokumentace a její existující kontrolní nástroj; žádná změna aplikace,
  produkce, oprávnění, dat ani aktivace automatizace.
- Base `feb8fe8c`, větev `codex/repo-documentation-policy-20261107`, vlastní worktree
  `C:/Users/Marek/Documents/Codex/evidencePavel-repo-documentation-policy-20261107`.
- Původní pracovní kopie `C:/xampp/htdocs/evidencePavel` je na starší větvi
  `codex/fio-auto-reconciliation-recovery`; její WIP ponechat beze změny.
- Výchozí pokyny: [AGENTS](../AGENTS.md), [CLAUDE](../CLAUDE.md),
  [pravidla dokumentace](WORK_DOCUMENTATION_POLICY.md).

## Zjištění při auditu

Pravidla již požadovala souborové plány; společný registr P01–P14 existoval.
Hlavní handoff (536 řádků) i registr (265) však kumulovaly staré session. Starý session handoff
měl 3 697 řádků a staré programové pokyny jej stále vydávaly za aktuální stav.
Chyběl společný průběžný deník a rozcestník rozhodnutí pro celý projekt.
Kontrola hlídala jen délku CURRENT (80 řádků) a malou část místních odkazů.

## Postup a akceptace

- [x] Ověřit čerstvý main, původní WIP a izolovat práci.
- [x] Zpřesnit AGENTS, CLAUDE, pravidla a vstupní prompty: povinné načtení a
  zápis plánů, konceptů a rozhodnutí do Gitu během práce i před předáním.
- [x] Zkrátit hlavní handoff na nejvýše 80 řádků, starou cestu session handoffu
  na nejvýše 25 řádků; historii zachovat beze ztráty a s kontrolou obsahu.
- [x] Odstranit session historii z aktuálního registru, zachovat P01–P14,
  doplnit dohledatelné souběžné PR a oddělit jejich stav od main/produkce.
- [x] Propojit aktuální stav, handoff, registr, deník událostí a rozhodovací log.
- [ ] Ověřit délky, odkazy, zachování archivů a kompatibilitu serverového
  publikátoru auditu; předat změnu přes GitHub PR a zapsat skutečný stav.

## Souběh a důkazní hranice

Při kontrole GitHubu 7. 11. otevřené [PR #355](https://github.com/KovoPraha/evidenceTreninku/pull/355)
(syntetická přejímka kroužku, dosud nesloučené) a draft
[PR #347](https://github.com/KovoPraha/evidenceTreninku/pull/347)
(ranní výsledek, stále NOT RUN podle dostupného předání).
Připravené serverové bloky `membership-morning-*` je nutné zachovat v aktuálních
souborech; nevyvolávat běh a nevydávat dnešní datum za důkaz provedení auditu.
Starší plány a rozhodnutí se zpětně nepovažují za dokončené bez zdroje.

## Ověření a další krok

Místní dokumentační kontrakt, kontrola odkazů/integrity, tři negativní zkoušky
kontrolního nástroje, pět regresí publikátoru i syntetický zápis do kopie
skutečných dokumentů PASS. [Přesné výsledky a meze](verification/documentation-governance-20261107/RESULTS.md).
Následuje dokončení GitHub PR a kontrol; stav předání zapsat sem a do přehledů.
