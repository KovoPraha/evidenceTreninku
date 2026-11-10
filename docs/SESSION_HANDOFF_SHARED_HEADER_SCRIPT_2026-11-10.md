# Předání P11-HJS — 10. 11. 2026

NEVYBRÁNO. [Plán](SHARED_HEADER_SCRIPT_PERFORMANCE_2026-11-10.md),
[skutečné JS přenosy](verification/shared-header-script-20261110/production-js-transport.json).
Prototyp na codex/shared-header-assets-performance-20261110 zachovaný mimo main.
Mechanická shoda a Node7/normalizátor1/8 PASS; nativní výkon ani deploy nepřijaty.
Hosting JS neposílá gzip a cache má30s, proto by nový14.6kB asset mohl zhoršit
cold přenos. Další zvolený řez P11-JSZ: gzip existujících veřejných JS, obsah
identický, bez novéhoHTTP requestu/cache změny. Vlastní nový plán bude v repu.
Runtime9dbd9eaf/deploy42727693090 a původnífio WIP7a7afd75 zachované.


## P11-HJS2 — nový běh po gzip

10. 11. 2026 ROZPRACOVÁNO, basefaed76c0, vlastní větev
codex/shared-header-script-gzip-20261110. [Nový plán](SHARED_HEADER_SCRIPT_PERFORMANCE_2026-11-10.md#p11-hjs2--nové-posouzení-po-nasazení-gzip-10-11-2026)
a [nové důkazy](verification/shared-header-script-gzip-20261110/RESULTS.md).
Původní zamítnutí a prototyp4c6ffea0 zachované; reálný Apache/gzip před nativním
PHP/DB a Chrome, žádné převzetí starých měření. Nový merge/deploy až po PASS.
Další krok: prověřit jednotlivé soubory prototypu a přidat komprimovaný transport.
