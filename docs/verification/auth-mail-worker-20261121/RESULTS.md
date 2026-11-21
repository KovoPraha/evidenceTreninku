# AUTH-MAIL-01 — ověření opravy automatické rodičovské pošty

Datum 21. 11. 2026. Implementace ve větvi `codex/auth-mail-worker-repair-20261121`, [PR #506](https://github.com/KovoPraha/evidenceTreninku/pull/506) sloučen na `741c0625427acef1badc9df8a7311183876d6620`. Produkční nasazení [44904278420](https://github.com/KovoPraha/evidenceTreninku/actions/runs/44904278420) na stejném SHA SUCCESS; záloha, migrace, aktivace a HTTP smoke PASS, `uat_schvaleno=false`, `importovat_krouzky=false`. Instalace vlastního plánovače byla samostatný krok.

| Kontrola | Výsledek | Podklad a mez |
| --- | --- | --- |
| Příčina | PASS | [Čtecí produkční stav 44904719446](https://github.com/KovoPraha/evidenceTreninku/actions/runs/44904719446): původní společný worker neměl záznam posledního běhu; rodičovský plánovač chyběl. `pending=3`, `due=3`, `processing=0`, `sent=6`, `failed=0`; `verify=1`, `reset=2`. |
| Připravenost | PASS | Produkční status: chybějící verze klíčů 0, issues `[]`, `ready=true`, PHP `mail()` dostupná. To není SMTP test ani doklad doručení. |
| Bezpečné oddělení front | PASS | Před aktivací klubová fronta `pending=7`, `due=7`; po prvním a druhém rodičovském běhu zůstala `pending=7`. Trenérské zprávy nejsou v rodičovském výběru. |
| Instalace vlastního CRONu | PASS | [Workflow 44904796827](https://github.com/KovoPraha/evidenceTreninku/actions/runs/44904796827): `installed=true`, `changed=true`, `ok=true`. Lokální test ověřil i idempotenci a odmítnutí cizího souboru. |
| První přirozený tick | PASS | [Čtecí stav 44904936261](https://github.com/KovoPraha/evidenceTreninku/actions/runs/44904936261): heartbeat 12:38:04 UTC, `processed=3`, `sent=1`, `cancelled=2`, `failed=0`, `public_only=true`. Rodičovské `pending=0`, `sent=7`, `cancelled=2`; oba staré resetovací odkazy byly neplatné nebo expirované. Nový účet ani odkaz worker nevytvořil. |
| Druhý přirozený tick | PASS | [Čtecí stav 44905030151](https://github.com/KovoPraha/evidenceTreninku/actions/runs/44905030151): heartbeat 12:40:06 UTC, `processed=0`, `pending=0`, `failed=0`; plánovač běží opakovaně. Stav hlásí `executable=false`, ale očekávaný zdroj je instalován a oba PHP tick-y skutečně proběhly. |
| Cílené regrese | PASS | Místní PHPUnit: 21 testů, 186 tvrzení. Finální CI PR #506: všech 11 kontrol PASS, včetně úplného PHPUnit, PHPStan/stylu, CodeQL, dokumentace a profilů MariaDB 10.3/11.4. |
| Doručení do schránky a řízené TEST `verify`/`reset` | NOT RUN | Jedno `verify` přijala funkce `mail()`. Vlastník nemůže potvrdit příjem ani spam; dva staré `reset` byly před odesláním zrušeny. Oba typy v doručené schránce tedy nejsou ověřeny. |
| Hostingové SMTP/MTA logy | NOT RUN | Aplikace používá PHP `mail()` bez vlastního SMTP klienta. Čtecí status nezpřístupňuje log doručovací služby; nebyl získán nezávislý důkaz konečného doručení. |

Známé odmítnutí transportu se znovu zkouší nejvýše pětkrát s uloženým `last_error`, pak přejde do `failed`; nejistý výsledek jde do `failed` bez automatického opakování. Administrace ukazuje chybu i u čekajícího opakování. To ověřují syntetické testy; v této produkční dávce chyba transportu nenastala.

**Další krok:** v řízené TEST schránce projít novou registraci a novou žádost o obnovu včetně doručení, použití odkazu a kontroly spamu. Rodiče se dvěma zrušenými starými resetovacími odkazy musí požádat o nový reset; staré odkazy nelze bezpečně odeslat znovu. Pokud nové zprávy nedorazí, získat provozní mail/MTA logy hostingu a řešit transport/doručitelnost. Neopakovat naslepo již zpracované zprávy.
