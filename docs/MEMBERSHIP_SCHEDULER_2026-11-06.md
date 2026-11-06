# Spolehlivý denní plán členství — 6. 11. 2026

Zadání: odstranit opožděné automatické spouštění, zachovat odložené skutečné
P05/P07 a export P06. Použít existující hostingový minutový cron, denní čas
05:23 UTC (06:23 CET / 07:23 CEST), nikoli měnit schválený čas na místní zónu.
GitHub schedule vytvořil běh 42222661557 až 13:05:18 UTC, tedy 7 h 42 min pozdě.

Větev `codex/membership-hosting-scheduler-20261106`, kopie
`C:/Users/Marek/Documents/Codex/evidencePavel-operations-handoff-20261106`.
Plán: otestovat UTC hranici, opakování/souběh, výpadek, čerstvost heartbeat,
bezpečnou instalaci a zachování přepínačů. Sdílený DB zámek s ručním CLI,
nejvýše jedno úspěšné automatické denní zpracování, odložený retry po chybě. Při přechodu
respektovat již úspěšný denní běh z provozního ledgeru, nepřehrávat dnešek.
Status nic nezpracovává ani neposílá. Instalace nevolá skutečnou platbu,
nevytváří testovací členství ani neimportuje export. Stávající povolený worker
nadále respektuje config automatizace a pošty. Nové skutečné P05/P07/P06 odložené.

Po zeleném CI: sloučit, chráněně nasadit, status → install → nezávislé status
po přirozeném hostingovém ticku, vypnout redundantní GitHub schedule. Změnu
ranního času neprovádět kvůli okamžitému testu. Skutečný další ranní billing
nelze dokazovat simulací; testy času a skutečný heartbeat uvádět odděleně.

## Průběžný záznam implementace (před nasazením)

Hotové: izolovaný spouštěč, status/install workflow s ověřením nasazených SHA,
atomický soukromý stav, denní UTC brána, catch-up a 10min retry, souborový zámek
hostingu a sdílený MariaDB lock s ručním workerem. Stav nezávisí na časové zóně
PHP ani GitHub runneru. Cílené členství/plán: 26 testů / 133 assertions PASS;
dokumentace: 10/61 PASS. Přidán unixový test symlinků a MariaDB ověření odmítnutí
souběhu a uvolnění zámku po výjimce; místní MariaDB neběží, důkaz musí dodat CI.
Nasazení a skutečný přirozený tick zatím NOT RUN. Plný test a statické kontroly
probíhají. Viz [handoff](HANDOFF_CURRENT.md) a [runbook](MONTHLY_MEMBERSHIP_RUNBOOK.md).

[PR #341](https://github.com/KovoPraha/evidenceTreninku/pull/341). Kontrola
produkční identity je samostatná testovaná funkce, stejně jako u Fio: PHPStan
jinak odvozuje URL z místního bootstrapu. Cílená analýza všech tří změněných
runtime souborů PASS; plán/identita 10 testů / 55 assertions, 1 Windows skip
unixových symlinků. Celý místní Composer test i analýza překročily jeho výchozí
300s limit; nenahrazují úplný Linux CI důkaz. Obě MariaDB matice jsou povinné.

První úplné CI na headu `f7a08f9e` prošlo všemi 11 kontrolami, včetně obou
MariaDB lock testů. Před merge přibylo předání #342: konflikt pouze na konci
registru sloučen zachováním obou záznamů, nové důkazy nasazení #340 zachované.
Runtime/testy/workflow proti ověřenému headu shodné; nové kontroly dokumentačního
merge musí zůstat zelené. Produkční nasazení scheduleru zatím neprovedeno.

Aktuální merge, nasazení a provozní přechod vede [výsledkový protokol](verification/membership-scheduler-20261106/RESULTS.md).

## Dokončeno

#341 sloučené a nasazené, hostingový spouštěč instalovaný a přirozené tiky
ověřené. GitHub schedule vypnuté až po důkazu hostingu; soukromé přepínače a
config zachované. [Výsledky a meze](verification/membership-scheduler-20261106/RESULTS.md),
[session handoff](SESSION_HANDOFF_MEMBERSHIP_SCHEDULER_2026-11-06.md).
Skutečné P05/P07 a export P06 zůstávají odložené. Budoucí ranní běh se dnes
neprohlašuje za proběhlý, časová brána a změny letního času prošly testy.
