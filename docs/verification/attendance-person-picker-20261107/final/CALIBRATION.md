# Kalibrace finálního páru, 7. 11. 2026

Původní dvouformulářový pár v nadřazené složce byl uzavřený před nalezením
ztráty sousedního draftu. Není kontrolní skupinou finálního párů: pro společnou
formu vznikl nový celý seed a znovu originální d958fd6e → finální picker.

- Nový vlastní runtime `kis-picker-final-20261107-76d29d23`, schema
  `p11_read_ad9527ab647e37e4`; první runtime zůstal zastavený. Ověřované
  localhost porty/datadir/config hash před každou DB operací, žádná produkce.
- Místo původního nativního okna DPR 1,25 má finální pár explicitní viewport
  1036 × 703 a změřené DPR přibližně 1; stejná hodnota pro všechny role/varianty.
- První finální coach browser běh skončil timeoutem networkidle 20 s.
  Neúplný běh byl celý nahrazen kompletním opakováním. Ze závěrečných úplných
  běhů nebyly vybírány ani odstraňovány jednotlivé časové vzorky.
- První pokus o závěrečný dry-run stylu postrádal explicitní --config při více
  cestách. Opravený kompletní dry-run všech devíti souborů je style.txt, 0 oprav.
- Výřezy elementů picker-form-* překrývá sticky header; plné JS/noJS stránky
  jsou vizuální důkaz. Hladký scroll viewport výřezy se jako důkaz nepoužívají.
- Žádný zásah do dat mezi časováními obou variant. Záměrné zápisové UAT až
  po prostém HTTP/browser i oddělené diagnostice; výsledný post-uat-state je
  samostatný doklad a nesrovnává se s nezměněnými read-only checksumy.
- Generovaný browser login/script/log s testhesly je odstraněný. Archiv
  obsahuje pouze bezpečné šablony a měření; nikdy config, cookies nebo hesla.
- Po úklidu přibylo integrační #367 training_day.php: celý pár se zpětně
  nepřejmenovává na přeměření nového main. Integrační regrese/CI jsou zvlášť.

[Výsledky](RESULTS.md), [úklid](cleanup.json), [post-UAT](post-uat-state.json).
