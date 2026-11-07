# Předání P11-R06-LESSONS — 7. 11. 2026

HOTOVO: dávkové čtení nabídky lekcí, parita, měření, merge a schválené nasazení.
[Plán](ATTENDANCE_LESSON_READ_PERFORMANCE_2026-11-07.md),
[měření](verification/attendance-lesson-read-performance-20261107/RESULTS.md),
[produkční protokol a meze](verification/attendance-lesson-release-20261107/RESULTS.md).

#367 sloučený; CI včetně obou MariaDB PASS, Linux 2355 testů / 46183 assertions.
720 párů: celé řádky/pořadí stejné, 153 → 6 SQL; 50 nabídek trenéra
41,47/19,99 → 1,77/0,97 ms. Jde o syntetické čtení helperu.
Transakční SQL/parametry/řádky stejné; writer a potvrzení docházky nezměněné.
Veřejný katalog po tomto druhém vydání: normalizovaný DOM i pole 5/5 shodné.
Anonymní soukromá trasa odmítnutá; přihlášené produkční R06 UAT NOT RUN.
Nové role/osoby/lekce ani účast se kvůli ověření nevytvářely.

Původní WIP zachovaný; detaily izolovaných větví a výsledkového předání v plánu.
Release/flags vlastní [CURRENT_STATE](CURRENT_STATE.md); nasazení neopakovat.
Další P11 je výběr osob (10221 voleb), mobil a souběh s celým HTTP/browser;
nejde o dokončení K0.2. [Registr](WORK_PLAN_CURRENT.md) zůstává otevřený.
