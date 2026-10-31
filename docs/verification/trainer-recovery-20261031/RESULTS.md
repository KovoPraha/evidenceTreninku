# Obnova trenérských hesel — nasazení a ověření

31. 10. 2026, Europe/Prague. Technické nasazení PASS; úplný trenérský pilot
zatím není připraven bez údajů o rozsazích a potvrzení doručení.

## Vydání a data

[PR #258](https://github.com/KovoPraha/evidenceTreninku/pull/258) je sloučené.
Ověřený head `de8023c8790048afb954c6fc2e6afe830dbb28c8` má shodný strom
s nasazeným `3d19aae6f9e0c9e008fe4c80e7229faa07b09cf1`.
[Chráněné nasazení](https://github.com/KovoPraha/evidenceTreninku/actions/runs/41185815825)
SUCCESS; UAT false, import false. [Výsledek](deployment.json).

Nová migrace přidala tabulku `trainer_password_reset_tokens`; ledger 142 → 143,
schéma current/pending []. Existující trenérské účty, hesla ani oprávnění nebyly
hromadně změněny. Config hash, dosavadní VS, přílohy, canonical-write a integrační
přepínače zůstaly zachované. [Před](pre-verification.json), [po](post-verification.json),
[porovnání](release-comparison.json). Živá identita je doložena deployment metadata,
workflow a obsahem nových stránek, nikoli kompletní inventurou souborových hashů.

Předmigrační záloha `evidence_2026-10-31_084609_21ea6ef9.sql.gz`:
SHA-256 `2b91d108496d1851666a90e90ec03abed1524d76f5d54d0abc5053218bcfe3ba`.
Skutečná [izolovaná obnova](https://github.com/KovoPraha/evidenceTreninku/actions/runs/41186359964)
ověřila SHA, import, počty všech 336 tabulek a 2 triggery.
[Doklad](backup-restore.json). Nová tokenová tabulka vzniká až zachovanou migrací;
její vlastnictví je zahrnuto v novém kontraktu budoucích záloh.

## Ověření funkce

- [Linux CI](ci.json): 1873 testů / 37959 assertions; samostatné POSIX scénáře
  bez skipů; MariaDB 10.3/11.4, PHPStan, styl, bezpečnostní a dokumentační kontroly PASS.
  Nasazení zopakovalo kompletní PHPUnit. První běh odhalil zastaralé migrační/
  zálohovací očekávání v testech, opravená před opětovným CI a sloučením.
- [Lokální HTTP průchod](local-http.json) použil skutečné formuláře a cookie relace
  s izolovanou SQLite a zakázaným `mail()`: CSRF, zařazení žádosti, reset,
  odmítnutí replay, odmítnutí původně funkčního hesla, odvolání již přihlášené
  relace a úspěšné přihlášení novým heslem. Žádné produkční heslo se neměnilo.
- Produkční anonymní HTTP [před](pre-http.json) i [po](post-http.json) 26/26,
  stejné tři katalogové produkty. [Trenérské trasy](trainer-http.json) 3/3:
  odkaz z přihlášení, formulář obnovy a nové heslo, všechny no-store a bez localhost tokenu.
- [Produkční fronta](trainer-readiness.json): potřebná schémata/šifrování dostupné,
  žádný chybějící klíč. Trenérská fronta prázdná. Tři staré klubové zprávy
  zůstaly čekající; jejich odeslání nebylo tímto rozsahem autorizováno.
- [Worker](trainer-worker.json) při ručním spuštění PASS, 0 zpracovaných/odeslaných
  zpráv, výhradně trenérské obnovy. GitHub workflow je aktivní, cron `*/5 * * * *`;
  skutečný běh vyvolaný plánovačem zatím nebyl zachycen. Interval není zárukou latence.
- Vlastník schválil jednu zprávu na `info@kovopraha.cz`. [Transportní test](mail-probe.json)
  `EP-TRENER-20261031` byl přijat funkcí `mail()`; soukromý marker brání opakování.
  Příjemce dosud nepotvrdil doručení do schránky. Nejde o produkční reset reálného hesla.

## Co zbývá před pilotem

Read-only inventura má **36 aktivních pracovních účtů, 28 způsobilých k obnově,
8 blokovaných** kontrolou adresy nebo explicitní veřejné vazby. Agregát nerozlišuje
konkrétní příčinu každého z osmi případů; bez ověřených údajů se kontakty neopravují.
Všech 36 má pozici trenéra, ale žádný aktuální explicitní grant skupiny/soupisky.
To není důkaz, že všech 36 jsou skuteční účastníci pilotu; mohou mít další pracovní pozice.

Vlastník má dodat konkrétní trenéry a skupiny/soupisky a potvrdit doručení testu.
Správce pak ověří adresy/propojení vybraných účtů, sportovní vedoucí výslovně
přiřadí rozsahy a ověří pohled trenéra na jeho sportovce a docházku i odmítnutí
cizí skupiny. Žádné oprávnění nevzniká odhadem z historie tréninků.

Trenér použije [přihlášení](https://kis.kovopraha.cz/login.php) → „Zapomenuté heslo“,
zadá evidovaný e-mail a použije odkaz do 60 minut. Bez doručení zkontroluje spam
a obrátí se na správce; formulář úmyslně neprozrazuje existenci účtu.

EvidencePavel a Velocota zůstaly samostatné. Integrace Velocoty je vypnutá;
globální hledání Velocoty ani jeho cron nebyly v tomto vydání aktivovány.
