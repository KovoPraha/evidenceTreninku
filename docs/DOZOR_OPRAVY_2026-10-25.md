# Opravy po nezávislé přejímce 25. 10. 2026

Vlastník schválil implementaci připomínek dozoru. Bez merge do main, deploye,
aktivace integrací, nových migrací a zásahů do skutečných dat.
Práce probíhá v izolovaných worktrees mimo htdocs; původní WIP zůstává zachován.
Během práce se main posunul cizími PR #201 a #202 na 83ebee72; všechny navázané
větve byly aktualizovány na tento základ. Poslední doložený deploy zůstal a23e393f.

## Plán a závislosti

- #196: zákaz všech tečkových cest a historické kopie ebook.pdf v Apache;
  ověřit pravidla i skutečné HTTP odpovědi v izolovaném Apache.
- #199 naváže na #196: sjednocení deploy výjimek, bez duplicitního ebook exclude.
- #197 naváže na #199: inventura přes git ls-files, bez tečkových cest;
  404 není obecný důkaz ochrany, parametrizované sondy a negativní testy.
- #198 naváže na #197: oprava payments a dokumentace bez zastarávajícího
  globálního zákazu merge a úkolu posoudit právě pět PR. Historický archiv neměnit.
- #200 naváže na #198: systémová dočasná cache PHPStanu, baseline přegenerovaná
  nad společným stavem a CI stylu vůči skutečné základní větvi PR.
- Spustit testy celku, sweep, PHPStan, styl a dokumentační kontrakt;
  předat přesné commity a zelené CI. PR zůstávají otevřené k přejímce.

Navázané PR udržují samostatné diffy a umožňují otestovat skutečný celek před
sloučením. Po přijetí předchůdce je nutné přesměrovat základ dalšího PR na main
a znovu ověřit CI; zejména squash/rebase merge mění předky commitů.

## Výsledky

- Apache: 13 syntetických existujících cest včetně vnořených a URL-encoded teček
  vrátilo 403; kontrolní PHP i statický soubor 200. Izolovaná konfigurace XAMPP,
  žádná změna běžící lokální ani produkční konfigurace.
- Deploy/security regrese: 14 testů / 269 assertions PASS.
- Sweep: 32 veřejných / 2 CLI / 235 chráněných / 0 chyb. Původních 236 zahrnovalo
  include fragment booking/free_event_registration_form.php, nyní explicitně
  v seznamu knihoven. Nejde o otevření dříve chráněné stránky.
- GET i POST se testují bez parametrů a s id=1; private_download.php má čtyři
  platné varianty druhu souboru a syntetická data pro větev stress. Localhost
  stránky dosáhnou skutečného login guardu (PHP server dostává SERVER_ADDR).
- Mutace: nová trackovaná stránka s 404 bez id a 200 s id byla odmítnuta v obou
  případech; export_csv.php bez login guardu také. Sweep skončil exit 1;
  export byl bajtově obnoven a dočasná stránka odstraněna i z indexu.
- Regrese inventury/HTTP politiky a pracovního registru: 16 / 2041 PASS.
- Dokumentace: payments potvrzeno v migraci 20260803230000_shop_checkout.php;
  CURRENT_STATE 29 řádků, 168 lokálních odkazů, 69 archivních bloků, 0 chyb.
- PHPStan: 555 položek / 2539 výskytů, odstraněn jen zastaralý nález export.php,
  žádné přidané potlačení. Cache vzniká v systémovém temp/phpstan. Starý adresář
  je v excludePaths volitelný, aby prošel i čistý checkout bez jeho existence.
- Testy SharedUiShellTest nyní také používají trackované soubory bez tečkových
  cest. Skutečná stará cache zůstala při lokálním testu na místě: původní dva
  falešné UI nálezy jsou odstraněné, nic se nemuselo ručně mazat.
- Společný stav nad ba2c9eef: [Linux test suite](https://github.com/KovoPraha/evidenceTreninku/actions/runs/40218167562)
  1722 testů / 35106 assertions PASS, MariaDB 10.3/11.4 PASS;
  [PHP quality](https://github.com/KovoPraha/evidenceTreninku/actions/runs/40218167599) PASS.
  Lokálně Windows 1722 / 35090, bez selhání, 3 unixové symlink testy přeskočené;
  Linux je ověřil. Styl: 4 změněné PHP soubory / 0 chyb, Composer valid.
- Následná aktualizace zahrnuje i main 83ebee72 (#202). Přesné poslední head SHA
  a opakované kontroly kompletního stavu jsou v [závěrečném PR #200](https://github.com/KovoPraha/evidenceTreninku/pull/200).
  CI po analýze ve stejném checkoutu spouští sweep i UI/inventory/deploy regrese.
- Všech 31 produkčních package záznamů composer.lock je beze změny; přibylo
  pouze 34 dev balíků, deploy používá --no-dev. Žádná aplikovaná migrace se nezměnila.

Samotný sweep nedokazuje přístupové matice přihlášených rolí ani úplné obchodní
UAT. Výjimka 404 je jen explicitní CLI cron a admin diagnostika; obecná 404 ani
samotná 405 neprokazují ochranu. Produkční účinnost Apache pravidel bude možné
potvrdit až po samostatně povoleném nasazení.
