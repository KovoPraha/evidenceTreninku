# Předání — dávkové klubové ceny e-shopu 7. 11. 2026

Stav: ROZPRACOVÁNO, implementace a měření zatím nejsou dokončené.
Zadání vlastníka: optimalizovat klubové ceny, ověřit shodné výsledky a změřit
před/po, zachovat WIP, průběžně aktualizovat plán v repu. Bez produkčního deploye.

Kanonický [plán a průběžný stav](MEMBER_PRICING_READ_PERFORMANCE_2026-11-07.md),
[výsledky](verification/member-pricing-read-performance-20261107/RESULTS.md),
[registr P11](WORK_PLAN_CURRENT.md).
Větev `codex/member-pricing-read-performance-20261107`, kopie
`C:/Users/Marek/Documents/Codex/evidencePavel-member-pricing-performance-20261107`,
výchozí main `feb8fe8c`; R05/R13/R06 již obsahuje. Původní WIP zachované.

Hotovo: převzetí zdrojů, izolace a zapsání plánu. Nyní: zmrazit původní
výpočet a připravit paritní/měřicí fixture. Další: reader, zapojení do
katalogu/detailu, funkční i kolací parita, měření, regrese a dokončení předání.
Checkout, schéma a produkční konfigurace se nemění. Aktuální release
a provozní flags jsou výhradně v [CURRENT_STATE](CURRENT_STATE.md).
