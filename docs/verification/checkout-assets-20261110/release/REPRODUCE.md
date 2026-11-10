# Reprodukce a rozsah důkazů

Native běh: `.github/workflows/checkout-assets-performance.yml`, source pin
588489003aff40963c3a64959d566406c687d08f, finální head d6af0a09. Workflow
připraví izolovanou syntetickou DB a skutečný Apache/Chrome na Linuxu. Vyžaduje
`EVIDENCE_PERFORMANCE_CONFIRM=SYNTHETIC-ONLY`; nepouštět proti produkční DB.
HTTP i browser code je v `tests/Support/CheckoutAssets*`. Bezpečné JSON/GZ
obsahují jednotlivé vzorky, summary mediány, source a cleanup kontrakty.

Živé produkční průchody jsou pouze anonymní GET. Archivovaný
`live-browser-probe.js.txt` je šablona: nahradit konfigurační placeholder
`__CHECKOUT_LIVE_CONFIG__` objektem `{phase:"before"|"after",output:"absolutní cesta"}`,
zkopírovat jako vlastní `.js` a spustit přes vlastněnou session Playwright CLI
0.1.23. Před/po musí mířit na tentýž konkrétní release; nový průchod neslouží
jako rekonstrukce historické baseline. Výstup JSON uložit jako
`output/checkout-live/{before,after}.json` a zachovat původní PNG.

Pro canonical payload kontrolu zkopírovat `static-probe.py.txt` jako
`output/checkout-static-assets.py` v checkoutu repozitáře a spustit
`python output/checkout-static-assets.py before` / `after` (Windows/XAMPP).
Ověřuje skutečné decoded TLS odpovědi proti Git blobům58848900; transportní
encoded velikosti jsou odděleně v browser vzorcích. Privátní curl těla/header
se do repozitáře nekopírují.

`live-parity.py.txt` zkopírovat jako `output/checkout-live-parity.py`,
`decoded-pixels.php.txt` jako `output/checkout-pixels.php.txt`. Aktuální archivovaný
porovnávač dokumentuje šest HTML/asset/header párů, pět striktních pixelových
shod a přesně vyjmenovaný control-mobile LIMIT. Ten se nepřevádí na PASS a
nepoužívá se pixelová maska. Diagnostický JSON obsahuje původní odchylku;
opakované after PNG a čtyři JPEG SHA jsou zachované. Budoucí odlišná odchylka
musí znovu projít vlastním rozborem, nikoli být přijata tímto historickým seznamem.

HTTP runner0 a předepsaný servercurl200 jsou výslovně rozlišené v receipt;
nezávislý živý Chrome6GET200. Lidské UAT, fyzický telefon a obnova této zálohy
NOT RUN. Samotné publikování těchto dokladů nevyžaduje druhý deploy.
