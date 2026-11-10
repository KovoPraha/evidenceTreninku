# Předání P11-ASSET7 —10.11.2026

ROZPRACOVÁNO. [Plán](BOOTSTRAP_ASSET_PERFORMANCE_2026-11-10.md#p11-asset7--stránky-rodinného-účtu-10-11-2026),
[výsledky](verification/family-assets-20261110/RESULTS.md).
Čtyři rodinné stránky: moje_osoby/objednavky/programy/rezervace.php.
Base4d1e9941, větev codex/family-assets-performance-20261110, vlastní worktree
C:/Users/Marek/Documents/Codex/evidencePavel-family-assets-performance-20261110.
Poslední ověřený runtimec9e5501a/deploy42881039034, flags vlastní CURRENT.
Původní fio/d1221f35 a WIP7a7afd75 zachované. Native2DB/Chrome/release NOT RUN.
Další krok: párová baseline se skutečným syntetickým loginem, neprázdné i
prázdné účty/denials/CSRF/revokace, source kontrakt a17párů cold/warm.
Pouze čtyři tagy, žádné změny cen/plateb/vazeb/programů/rezervací/writerů.
Při všech PASS merge/deploy autorizovaný, UAT/importfalse; doklady bez redeploye.
Můj sport jiná autentizace, HJS2 a RIX nevybrané WIP, EVENTREAD odložený
prototyp bez runtime helperu. [ASSET1–6 historie](BOOTSTRAP_ASSET_PERFORMANCE_2026-11-10.md).
Historické3 ASSET4 hashy a ASSET6 mobilní controlfoto zůstávají LIMIT.


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
