# Cílení událostí na soupisky (M1.6)

Událost může být určena jedné nebo více ročním soupiskám. Správce nastaví cílení pouze ve stavu `draft`, s CSRF ochranou, výslovným potvrzením a auditovaným důvodem. Po otevření přihlášek je cílení neměnné.

Prázdná množina cílů znamená výslovně veřejnou událost a zachovává dosavadní cestu. Neprázdná množina vyžaduje, aby sportovec měl k datu první části události aktivní členství alespoň v jedné cílové soupisce. Aktivní musí být také soupiska a její sezona a datum události musí ležet v sezoně.

## Bezpečnost a historie

- vazba rodič/sportovec a soupisková způsobilost se odmítnou před kapacitním mutexem;
- uvnitř registrační transakce se obě oprávnění znovu ověří;
- unikátní klíč registrace `(event_id, sportovec_id)` dál brání duplicitě i při překryvu soupisek;
- registrace ukládá seřazený JSON snapshot všech odpovídajících `team_id` a textový důvod včetně rozhodného data;
- pozdější odebrání ze soupisky historický snapshot nemění;
- při povýšení z čekací listiny se aktuální vztah a soupisková způsobilost ověří znovu;
- změna cílení mimo `draft` selže bez částečného zápisu.

Tabulka `club_event_roster_targets` je M:N vazba s identitou správce, důvodem rozhodnutí a časem. Audit `set_roster_targets` obsahuje předchozí i novou množinu ID a režim `public` nebo `roster_targeted`.
