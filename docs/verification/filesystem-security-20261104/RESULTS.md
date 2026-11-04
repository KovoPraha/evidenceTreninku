# Bezpečnostní odblokování deploye — 4. 11. 2026

Nasazení sloučeného PR #297 v běhu
[41896863045](https://github.com/KovoPraha/evidenceTreninku/actions/runs/41896863045)
zastavil `composer audit --locked` před SSH, zálohou, migracemi a aktivací.
Produkce tímto neúspěšným pokusem nebyla změněná.

Nově publikované [GHSA-6prc-h956-hxjq](https://github.com/symfony/symfony/security/advisories/GHSA-6prc-h956-hxjq)
se týká vyhodnocení kořene cesty v Symfony Filesystem. Oficiální oprava řady 7 je **7.4.23**.
Balíček používá pouze vývojový PHP CS Fixer; je v `packages-dev` a není součástí produkčního
`--no-dev` balíčku. Bezpečnostní gate kontroluje celý lock a zůstává beze změny.

`composer.lock` aktualizuje jediný balíček **symfony/filesystem 7.4.21 → 7.4.23**,
včetně oficiálního source/dist reference. Produkční balíčky, composer.json, aplikační
kód, workflow, oprávnění, schéma a data se nemění. Žádný advisory nebyl ignorován.

- Audit před: jedna položka PKSA-g499-cm4q-v35y; [strojový důkaz](audit-before.json).
- Audit po: prázdné advisories i abandoned, exit 0; [strojový důkaz](audit-after.json).
- Nový regresní kontrakt používá pouze operace nad řetězci, neotevírá ani nezapisuje soubory.
  `Path::isBasePath('/base', '/base/../x://../secret.txt')` před opravou nesprávně vrátil true:
  **1 test / 1 assertion FAIL**. Po opravě **1 test / 3 assertions PASS**;
  ověřen i běžný podadresář a zachování legitimního `phar://` schématu.
- Composer validate --strict, kontrola stylu a git diff --check PASS.
  Rozhodující pro sloučení je nové CI přesného headu tohoto PR.

Jde o nutnou nápravu bezpečnostního zastavení již autorizovaného nasazení PR #297.
Následný skutečný deploy a ověření budou doložené v samostatném protokolu vydání.
Heslo, skutečný použitý obnovovací odkaz a T07 zůstávají SKIPPED.

Následné úspěšné nasazení a jeho kontroly dokládá [protokol vydání](../functional-round7-release-20261104/RESULTS.md).
