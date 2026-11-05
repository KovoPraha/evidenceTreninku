# Ostré Fio a SumUp — implementační příprava 5. 11. 2026

Tento dokument zachycuje přípravu před sloučením nové opravy SumUp. Nejde o
prohlášení, že obě platební cesty už úspěšně dokončily skutečnou úhradu.
Vlastník požaduje produkci s ostrými daty, bez provider sandboxu; následně
výslovně zvolil použití stávajícího KIS_SUMUP_API_KEY v production Environment.
Hodnoty klíčů se nečtou do chatu ani neukládají do důkazů.

## Výchozí ověření a Fio

Základ je main f7bf825d, živý aplikační release bc627ab1. Vlastní worktree
obsahuje jen tuto práci; rozpracovaný kis_rosters_admin.php v původním checkoutu
je nedotčený. První vytváření worktree bylo přerušené; ověřený vlastní checkout
se obnovil z přesného základu až po ověření, že nezůstal běžící Git proces.

Fio token a správný bankovní účet nyní skutečně ověřil
[čtecí běh 42000593900](https://github.com/KovoPraha/evidenceTreninku/actions/runs/42000593900).
[Konfigurace 42001091976](https://github.com/KovoPraha/evidenceTreninku/actions/runs/42001091976)
zálohovala config a zapnula import, auto-confirm zůstal false. První
[import 42001475122](https://github.com/KovoPraha/evidenceTreninku/actions/runs/42001475122)
úspěšně uložil osm pohybů, všechny předal ke kontrole; potvrdil nula plateb.
Historie navazuje od stávajícího 21. 9. po omezených oknech s překryvem a deduplikací.
Dokončení historie, automatika a plán mají vlastní následné ověření.

Historie už následně dosáhla 5. 11. (caught_up=true), bez jediné automaticky
potvrzené platby. Před zapnutím automatiky byl potvrzen také nález, kdy starší
pohyb uhradil později vytvořenou platbu: [červený test](fio-date-red.xml).
Doménová kontrola nyní pod stávajícími zámky vyžaduje, aby datum zaúčtování
nepředcházelo dni vzniku platby. Neznámý/neplatný den a starší pohyby zůstanou
v review_auto_conflict; ruční, zdůvodněné rozhodnutí je zachované.

Nejde o odesílání peněz z Fio. Používá se pouze periods GET, bez posunu bankovní
zarážky nebo platebních příkazů. Token má vlastní KIS Secret. Minimální rozestup
dotazů a správnou volbu periods potvrzuje aktuální
[dokumentace Fio](https://www.fio.cz/docs/cz/API_Bankovnictvi.pdf).

## Nález a oprava SumUp

Kontrola porovnala současný KIS s MotolSumUpGateway ve Velocotě (checkout
results.velocota.com 8f4bb047) a aktuální dokumentací SumUp. KIS ověřoval checkout
a jeho stručnou transakci, ale ne samostatný autoritativní detail. Regresní test
prokázal přijetí úhrady, přestože její detail uvádí vratku, a také přijetí při
nedostupném detailu API: [2 testy FAIL před opravou](sumup-detail-red.xml).

Před první lokální změnou úhrady se nyní serverovým GET načte identifikovaná
transakce správného obchodníka. Kontroluje se totožné ID, částka, měna, obchodník,
SUCCESSFUL/ECOM, případný simple_status a obě podoby historie událostí.
Vratka, chargeback, sporný odpočet nebo chybějící/poškozený důkaz úhradu nepotvrdí.
Síťový dotaz neprobíhá pod databázovými zámky. Výpadek nezanechá částečný zápis;
opakování úspěšného callbacku zachovává původní idempotenci.

Úplný detail a historii finančních událostí popisuje
[SumUp Retrieve a transaction](https://developer.sumup.com/api/transactions/get/).
Historie již potvrzené platby se touto opravou automaticky nestornuje ani
nevrací zákazníkovi; následná vratka/chargeback je samostatná účetní situace.
Nesprávný příjemce nebo jiná měna se nepřijímají od zákazníka.

Další [dva červené testy](config-red.xml) potvrdily varování při zapnutí staršího
configu a přijetí nevhodné URL s přihlašovací částí. Konfigurační pomocník nyní
zachová původní obsah, ochrání starší definice konstant a odmítne credentials,
query, fragment a nestandardní port. Zpřísněné jsou také runtime URL guardy.

Chráněné workflow SumUp má nově režim verify. Bez změny produkční konfigurace
ověří živou online kartu v CZK a oprávnění číst transakce. Čte maximálně jednu
položku historie pouze do neveřejného dočasného souboru runneru, který uklidí;
do logu jde jen souhrn booleans. Nezakládá checkout, neprovádí úhradu ani vratku.
Stejná kontrola předchází enable; rollback nadále používá disable a zálohu configu.

## Ověření této přípravy

- Výchozí související sada: 52 testů / 319 assertions PASS.
- Nálezy: transakční detail 2/4 FAIL; konfigurace 2/5 FAIL.
- Závěrečná cílená sada včetně Fio časové hranice: **57 testů / 413 assertions PASS**.
- Testy ověřují také refund/chargeback, dvě podoby eventů, nesprávné identity,
  částky, měny a merchant, výpadek API, rollback fronty/auditu i bezpečné opakování.
- Změněné PHP soubory prošly syntaxí a povinným stylem, git diff --check PASS.
  Úplná místní sada, PHPStan a CI se dokončují nad předávaným headem.

Testy používají výhradně offline syntetická data. Nový provider sandbox se
nezakládá. Žádná skutečná testovací finanční transakce se netvrdí.
