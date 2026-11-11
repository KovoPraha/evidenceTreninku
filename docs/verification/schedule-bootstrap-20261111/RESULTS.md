# Plánovač a duplicitní Bootstrap CSS

Výchozí main 90745940, před implementací. Přenos, parita, CI a deploy čekají.

[PR #451](https://github.com/KovoPraha/evidenceTreninku/pull/451), první běh
[42975738756](https://github.com/KovoPraha/evidenceTreninku/actions/runs/42975738756).
Celé HTTP na obou DB prošlo před browser krokem. MariaDB 10.3 potvrdila 350
shodných checksumů. Běh 11.4 skončil před Chrome na předpokladu o příznaku
Secure při předání syntetické session. Fixture nyní přebírá skutečný příznak.
První PHPUnit selhal na explicitním seznamu stránek se sdíleným CSS.
Seznam obsahuje i plánovač a cílený test znovu prošel. Finální CI se opakuje.

Druhý běh 42976040733 znovu potvrdil HTTP, ale Chrome před navigací odmítl koncový středník v předávané funkci. Funkce nyní prochází i kontrolou po zabalení do výrazu. Lokální sada 23 testů a 138 kontrol PASS.
