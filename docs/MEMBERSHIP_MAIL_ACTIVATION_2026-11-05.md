# Zapnutí pošty členství — 5. 11. 2026

Vlastník výslovně schválil zapnout e-maily k dokladům a upomínkám. Denní
zpracování už běží; požadavek nyní zahrnuje i skutečný poštovní transport.

1. Čtecím chráněným workflow ověřit stav a frontu zpráv před změnou.
2. Rozšířit existující konfigurační workflow o samostatné mail-enable/mail-disable.
   Samostatný spravovaný blok upraví pouze MONTHLY_MEMBERSHIP_MAIL_ENABLED;
   automatizace a ostatní integrace zůstanou beze změny. Před zapnutím ověřit
   dostupnou PHP mail funkci a aktivní automatizaci, zachovat zálohu i atomický zápis.
3. Ověřit nezávislé přepínání obou příznaků, opakovatelnost a původní chování;
   sloučit po kontrolách. Na produkci zapnout mailový příznak a workflow proměnnou.
4. Spustit skutečný apply --send a ověřit heartbeat, stav fronty a přepínačů.
   Prázdný běh nedokazuje doručení do schránky. Nezakládat falešné doklady ani
   neposílat zkušební zprávy na odhadnuté adresy.
5. Zapsat skutečný výsledek do CURRENT_STATE, handoffu a runbooku. Pro pozastavení
   pošty stačí workflow MAIL=false a mail-disable; denní účtování může pokračovat.

Předprodejní příprava Závodního týmu (vystavovatel, tarif, publikace) zůstává
samostatná. Tímto se neaktivují ostatní komunikační fronty aplikace.

## Ověřená implementace a aktivace

[PR #321](https://github.com/KovoPraha/evidenceTreninku/pull/321) sloučen jako
`8a2d9149ec2034dfbb3ff73031f423e7f359666e`. Přesný testovaný head
`4640bef7e28a8c65ec700ac8f3cd927b5d54c6f5` prošel všemi 11 kontrolami:
[CI 42107377525](https://github.com/KovoPraha/evidenceTreninku/actions/runs/42107377525),
Linux **2211 testů / 41386 assertions**, MariaDB 10.3/11.4, PHPStan, styl,
CodeQL a dokumentace PASS. Místně cílené testy členství/konfigurace **26/186**,
syntax PHP/Bash, styl, composer validace a dokumentační kontrakt PASS.

[Čtecí stav před změnou 42106760536](https://github.com/KovoPraha/evidenceTreninku/actions/runs/42106760536)
potvrdil zapnuté zpracování, vypnutou poštu a 0 členství/tarifů/zpráv.

[Aktivace 42108448782](https://github.com/KovoPraha/evidenceTreninku/actions/runs/42108448782)
SUCCESS, 5. 11. 2026 v 21:00 UTC. PHP `mail` je dostupná; soukromý příznak
`MONTHLY_MEMBERSHIP_MAIL_ENABLED` změněn false → true. Automatizace, Fio,
SumUp a ostatní příznaky zůstaly zachované. Před atomickým zápisem byla
ověřena soukromá záloha configu. SHA256 před/po:

- `abcfc2ba4e846e1a28329c70f9b7e603bb760fe9beb157ac9170cdd5d5ae344a`
- `fb28f95a4fcfa08046add5d1247616e6b0be93833ba0d13c09d8a7ca66ab6e9e`

Repozitářové proměnné `KIS_MONTHLY_MEMBERSHIP_SCHEDULE_ENABLED`,
`KIS_MONTHLY_MEMBERSHIP_APPLY_ENABLED` a `KIS_MONTHLY_MEMBERSHIP_MAIL_ENABLED`
jsou všechny **true**, bez přepisu prostředím production. Plán zůstává
05:23 UTC denně (06:23 CET / 07:23 CEST), nezávisle na počítači vlastníka.

## Ostrý běh s povolenou poštou

[Běh 42108711699](https://github.com/KovoPraha/evidenceTreninku/actions/runs/42108711699)
SUCCESS; log výslovně potvrzuje `--apply --send`. Výsledek v 21:01 UTC:

```json
{"apply":true,"memberships":0,"charges":0,"documents":0,"queued":0,"sent":0,"errors":[]}
```

Prázdný běh ověřuje povolení a spouštění procesu, nikoli skutečné doručení
e-mailu do schránky. Nebyl vytvořen falešný doklad ani odeslána zkušební zpráva.
Web po změně odpovídá HTTP 200; to samo není přihlášené UAT.
Konfigurační nástroj běžel mimo webroot, nebyl nutný nový deploy celého webu.
Produkt Závodní tým zůstává konceptem a stále čeká na fakturační údaje,
schválení vystavovatele/tarifu a publikaci. Ostatní komunikační fronty se tímto krokem neměnily.

[Nezávislá kontrola 42108836694](https://github.com/KovoPraha/evidenceTreninku/actions/runs/42108836694)
SUCCESS: uložený heartbeat `monthly_memberships` od **2026-11-05 21:01:17 UTC**
do **21:01:18 UTC**, `status=success`, `summary_code=completed`. Automatizace
i pošta true, prázdná fronta, stejný config hash. Budoucí první automatický
časový start s poštou tím ještě není doložen; ověřen je ruční běh téhož procesu.
