# Zapnutí pravidelného zpracování členství — 5. 11. 2026

Vlastník výslovně schválil zapnout pravidelnou práci systému i bez členství.
Pošta zůstává vypnutá a koncept Závodní tým se tím nezveřejňuje.

## Implementace a kontrola

- [PR #318](https://github.com/KovoPraha/evidenceTreninku/pull/318), merge
  `2301257b289d69e39a6be900c49a9df147588901`: úzce vymezený konfigurační nástroj
  a chráněný workflow, společný produkční zámek, privátní ověřená záloha,
  atomická změna jediného příznaku, následné ověření v novém procesu.
- Přesný testovaný head `d91d082951b717c0eb192eff2bffa3063383d6d8`:
  [CI 42085036486](https://github.com/KovoPraha/evidenceTreninku/actions/runs/42085036486),
  Linux **2209 testů / 41327 assertions**, obě MariaDB matice 10.3 a 11.4 PASS.
  Všech 11 kontrol PASS, včetně PHPStan, stylu, CodeQL a dokumentace.
- Místně cílené konfigurační testy 6/41, syntaxe PHP/Bash, styl a composer PASS.
  Místní úplná sada měla jednu nesouvisející chybu PDF parseru baru a tři POSIX
  skipy: sdílený vendor ještě neobsahoval novou závislost Smalot z mezitím
  sloučeného PR #306. Stejná chybějící závislost omezovala místní PHPStan.
  Rozhodující čistý Linux CI instaloval aktuální lock a prošel bez těchto omezení.

## Produkční změna

[Aktivace 42086302806](https://github.com/KovoPraha/evidenceTreninku/actions/runs/42086302806)
SUCCESS. Soukromý config před změnou měl SHA256
`d77909b9ac5d4348e6583e0bfa958ca95a0a3ce7e4fc58e75aa76d1566c5bb3c`, po změně
`abcfc2ba4e846e1a28329c70f9b7e603bb760fe9beb157ac9170cdd5d5ae344a`.
Záloha vznikla v soukromém `data/.kis-backups/config` a byla ověřena před zápisem.

`MONTHLY_MEMBERSHIP_AUTOMATION_ENABLED=true`; soukromé povolení pošty je false.
Fio import/automatické potvrzování a SumUp zůstaly true, Stripe, Velocota a
Packeta zůstaly efektivně false. Konfigurační workflow porovnal ostatní příznaky
před/po. Členské tabulky zůstaly prázdné kromě jednoho existujícího katalogového
auditního záznamu. Žádné členství ani tarif nebyly vytvořeny.

Ověřené repozitářové proměnné:

- `KIS_MONTHLY_MEMBERSHIP_SCHEDULE_ENABLED=true`
- `KIS_MONTHLY_MEMBERSHIP_APPLY_ENABLED=true`
- `KIS_MONTHLY_MEMBERSHIP_MAIL_ENABLED=false`

Prostředí `production` nemá přepis těchto proměnných. Existující workflow
`monthly-memberships-production.yml` je active a má plán **05:23 UTC denně**
(06:23 CET / 07:23 CEST). Čas zahájení GitHub Actions může mít prodlevu.
Na počítači vlastníka tento proces nezávisí.

## První skutečný běh

[Apply 42086490294](https://github.com/KovoPraha/evidenceTreninku/actions/runs/42086490294)
SUCCESS, 18:40 UTC:

```json
{"apply":true,"memberships":0,"charges":0,"documents":0,"queued":0,"sent":0,"errors":[]}
```

Jde o ručně vyvolaný ostrý běh stejného procesu, který používá denní plán,
nikoli dry-run. První budoucí automatický start podle časového plánu tím ještě
není doložen. Skutečná úhrada, nové PDF ani doručení pošty nebyly tímto prázdným
během testovány. Pošta vyžaduje samostatné zapnutí. Produkt zůstává konceptem,
tarif a skutečné fakturační údaje musí být schváleny před prodejem.

[Následná čtecí kontrola 42086602560](https://github.com/KovoPraha/evidenceTreninku/actions/runs/42086602560)
SUCCESS: uložený heartbeat `monthly_memberships` má `status=success`,
`summary_code=completed`, začátek **2026-11-05 18:40:44 UTC**, konec
**18:40:45 UTC**. Automatizace stále true, pošta false, config hash i počty
členství/předpisů/zpráv beze změny.

Nebyl proveden nový deploy celého webu: workflow použil dočasný CLI nástroj
mimo webroot a již nasazený worker. Nejde o aktivaci baru nebo jiné integrace.
