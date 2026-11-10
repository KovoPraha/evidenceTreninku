# P11-ASSET6 — průběžné ověření checkout formulářů

ROZPRACOVÁNO. [Plán](../../BOOTSTRAP_ASSET_PERFORMANCE_2026-11-10.md#p11-asset6--rychlý-nákup-a-přihláška-10-11-2026).
Veřejný [GET probe](candidate-get-probe.json):218/784 a298/952 oba200/2CSS, control200/1CSS.
Není časový benchmark. Native2DB/browser/nový release zatím NOT RUN.

ASSET6 checkpoint: source0braces/2tagy a21/412 lokálních kontrol PASS; původní regex testu upraven pouze pro PHP whitespace, DOM názvy/verze a rollback kontrolované dál. Native2DB/HTTP/Chrome/CI/release zatím čekají.


ASSET6 / [PR #445](https://github.com/KovoPraha/evidenceTreninku/pull/445).
Souběžný main06ad54e4 /#444 převzatý bez konfliktu se zachováním dokončeného
BARu a všech jeho věcných záznamů. Žádná aplikativní změna proti58848900;
source oracle dvou formulářů zůstává58848900, celý ostatní PHP kontext je v A/B
páru vždy čerstvý a byte-shodný. Nové CI ověřuje společný head před release.


ASSET6 native kalibrace: první42876038779/d9c62776 obě DB skončily před
HTTP maticí na očekávání customer login303. Skutečný neměněný veřejný login
prihlaseni.php používá header Location bez status argumentu, tedy302; login
byl úspěšný. Test nyní očekává přesně302, nikoli obecnou libovolnou3xx.
Následný ověřený-account guard rychlé přihlášky zůstává přesně303 a celý
Location/body kontrakt je dál kontrolovaný. Source/aplikace se nemění.
Cleanup vlastního schema/user/config a workerů proběhl, žádná produkční akce.
Nová plná native2DB/browser přejímka se opakuje, neoznačovat první FAIL za PASS.
