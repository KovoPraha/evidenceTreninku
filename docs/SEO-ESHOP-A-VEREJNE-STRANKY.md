# SEO připravenost veřejných stránek a e-shopu

Aktualizováno: 17. 10. 2026. Rozsah: `kis.kovopraha.cz`, veřejné stránky v
`booking/` a veřejný e-shop. Jde o lokální implementaci, **nikoli o potvrzení
produkční indexace nebo nasazení**.

## Výchozí zjištění

- Produkční `robots.txt` a `sitemap.xml` vracely 17. 10. 2026 HTTP 404.
- Katalog, detail produktu a ostatní hlavní veřejné vstupy neměly vlastní
  meta description ani kanonickou URL; detail neobsahoval strukturovaná data
  produktu. Vyhledávání a řazení katalogu vytvářelo další URL téhož obsahu.
- Privátní kroky (přihlášení, osobní profily, objednávky, rychlý checkout)
  neměly jednotný `X-Robots-Tag`. Testovací publikované produkty se nesmějí
  objevit v sitemap ani jako indexovatelný detail.

## Implementace

- `robots.txt` odkazuje na `/sitemap.xml`, která jako index odkazuje na
  dynamickou `/sitemap.php`. Ta používá důvěryhodnou
  `APP_BASE_URL`, obsahuje základní veřejné stránky a jen aktuálně prodejné,
  netestovací produkty. Neobsahuje osobní URL, košík ani objednávky.
- Veřejné stránky mají popis, absolutní canonical a Open Graph základ. Katalog
  má samostatný canonical pro platnou kategorii; výsledky interního hledání,
  řazení a stránkování mají `noindex,follow`.
- Detail veřejného zboží má JSON-LD `Product` a skutečné nabídky `Offer`
  odvozené z aktivních variant, ceny a skladového stavu. Nevymýšlí recenze,
  značku výrobce, dopravu ani pravidla vrácení. Kroužky/služby tento typ
  strukturovaných dat nedostávají. TEST detail má `noindex`.
- Neveřejné booking trasy vracejí `X-Robots-Tag: noindex, nofollow, noarchive`.
  Stejnou hlavičku mají i všechny přihlášené pohledy, byť používají veřejnou
  trasu (například e-shop s osobním košíkem).
  `robots.txt` je neblokuje, protože crawler musí odpověď stáhnout, aby
  `noindex` viděl.

## Ověření před přijetím releasu

1. Po nasazení ověřit skutečné HTTP 200 a typ `text/plain` u `/robots.txt`,
   XML u `/sitemap.xml`, `application/xml` u `/sitemap.php` a validní XML s
   absolutními HTTPS URL.
2. V anonymním HTML ověřit title, description, jediný canonical a Open Graph
   u úvodu, kroužků, e-shopu, kategorie a produktu. Na produktu porovnat
   `Offer.price`, měnu, dostupnost a obrázek s viditelnou nabídkou.
3. Potvrdit `noindex` pro interní hledání, TEST detail, přihlášení,
   checkout a objednávku. Nedávat tyto URL do sitemap.
4. Zkontrolovat produkt v Google Rich Results Test a URL Inspection po
   nasazení. Přihlásit sitemap v Search Console a následně sledovat
   indexování a případné chyby; samotné vložení značek nezaručuje rich result.
5. Zkontrolovat nové produktové obrázky a veřejné texty: chybějící skutečný
   obrázek či stručný popis se nesmí doplňovat smyšleným obsahem.

Záměrně neřešeno bez rozhodnutí vlastníka: účty Search Console/Merchant
Center, politika dopravy a vrácení, autentický výrobce/značka a redakční
popisy jednotlivých kategorií. Sitemap není náhradou za tyto údaje.

Technický základ vychází z dokumentace Google Search Central k
[sitemap](https://developers.google.com/search/docs/crawling-indexing/sitemaps/build-sitemap),
[robots meta a X-Robots-Tag](https://developers.google.com/search/docs/crawling-indexing/robots-meta-tag),
[meta description](https://developers.google.com/search/docs/appearance/snippet) a
[strukturovaným datům produktu](https://developers.google.com/search/docs/appearance/structured-data/product).
