# P11-ASSET7 — průběžné ověření rodinných stránek

ROZPRACOVÁNO. [Plán](../../BOOTSTRAP_ASSET_PERFORMANCE_2026-11-10.md#p11-asset7--stránky-rodinného-účtu-10-11-2026).
Čtyři zdrojové CSS duplicity ověřené; native/browser a přínos zatím NOT RUN.


ASSET7 checkpoint: implementovaná přesně čtyři odstranění. Source kontrakt
proti4d1e9941 PASS: všechny PHP tokeny/literal HTML/JS zachované mimo konkrétní
tagy a4přesně pinované guard závorky v moje_programy.php (PSR12). Nulové PHP
hranice dělí dlouhé HTML řádky bez výstupu; whitespace není obecně maskovaný.
Lokálních19testů/188assertions včetně negativních mutací porovnávače a account
claims/role/program overview PASS; style/line200/lint/diff PASS. Nový runner:
17celýchHTTP scénářů (main/empty/unverified/anonymous/control), CSRF a čerstvá
revokace vazby/účtu,350checksumů a20Chrome kombinací. Native/baseline/Chrome
přínos a release zatím NOT RUN; další krok nová přejímka, ne hotový výsledek.


ASSET7 / [PR #447](https://github.com/KovoPraha/evidenceTreninku/pull/447), větev codex/family-assets-performance-20261110; native/browser běh připravený.


ASSET7 native kalibrace:42900569649/964df9a6 a42900622889/5926e376 obě DB
FAIL před HTTP maticí na chybně očekávaném login302 neověřeného účtu.
Neměněné prihlaseni.php výslovně odmítá email_overeno=0:200 a „Email není
ověřen.“, účet vůbec nepřihlásí. Fixture nyní přesně kontroluje toto odmítnutí
apak302 denials čtyř soukromých stránek. Revokace aktivního přihlášeného účtu
se proto ověřuje na prázdném ověřeném účtu12: podle db.php přesně303 na
session=expired. Žádné rozšíření tolerovaných stavů ani změna autentizace;
první FAIL zůstává v historii, nová plná native/Chrome přejímka se opakuje.
Produkční anonymní before:4redirecty302 a login200,5canonicalUIpayloadů PASS;
přihlášené živé UAT NOT RUN. Žádné produkční POST, WIP zachovaný.


ASSET7 druhý native checkpoint42901157327/ee01c130: loginové denials už podle
původního kontraktu prošly; obě DB FAIL na neprázdném main-orders ještě při
before. Kapacitní základ fixture vytváří objednávky bez kanonických payments,
zatímco skutečné shopOrderListForAccount používá INNER JOIN payments.
Doplněné pouze syntetické payment řádky bez reálného IBAN/platby, aby byl
skutečný neprázdný stav vůbec vykreslený; query/writer/ochrana beze změny.
Revokační fixture navíc ve finally obnovuje i původní updated_at vazby
(ON UPDATE v migrovaném schématu), checksum kontrakt se nesnižuje. Nový celý
native2DB/Chrome běh se opakuje, dosavadní FAIL zůstávají FAIL.
