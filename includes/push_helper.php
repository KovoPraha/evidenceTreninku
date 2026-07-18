<?php
/**
 * includes/push_helper.php
 * Helper pro odesílání Web Push notifikací.
 *
 * Vyžaduje: composer require minishlink/web-push
 * Pokud knihovna není dostupná, funkce tiše selže (zaloguje chybu).
 *
 * VAPID klíče: vygenerujte jednou příkazem:
 *   vendor/bin/web-push generate-vapid-keys
 * Nebo online: https://vapidkeys.com/
 * Uložte do nastaveni: push_vapid_public, push_vapid_private, push_vapid_subject
 */

/**
 * Odešle Web Push notifikaci jednomu nebo více trenérům.
 *
 * @param PDO    $pdo
 * @param array  $payload  ['title', 'body', 'url', 'tag']
 * @param int[]  $trenerIds  Pole ID trenérů ([] = všichni odběratelé)
 */
function sendPushNotification(PDO $pdo, array $payload, array $trenerIds = []): void
{
    // Kontrola dostupnosti knihovny
    if (!class_exists('\Minishlink\WebPush\WebPush')) {
        error_log('push_helper: minishlink/web-push není nainstalována. Spusťte: composer require minishlink/web-push');
        return;
    }

    // Načíst VAPID klíče z nastaveni
    $stKeys = $pdo->query("SELECT klic, hodnota FROM nastaveni WHERE klic IN ('push_vapid_public','push_vapid_private','push_vapid_subject')");
    $keys   = [];
    foreach ($stKeys->fetchAll(PDO::FETCH_ASSOC) as $r) { $keys[$r['klic']] = $r['hodnota']; }

    if (empty($keys['push_vapid_public']) || empty($keys['push_vapid_private'])) {
        error_log('push_helper: VAPID klíče nejsou nastaveny v tabulce nastaveni.');
        return;
    }

    // Načíst subscriptions
    if (!empty($trenerIds)) {
        $in   = implode(',', array_fill(0, count($trenerIds), '?'));
        $stmt = $pdo->prepare("SELECT * FROM push_subscriptions WHERE trener_id IN ($in)");
        $stmt->execute($trenerIds);
    } else {
        $stmt = $pdo->query("SELECT * FROM push_subscriptions");
    }
    $subs = $stmt->fetchAll(PDO::FETCH_ASSOC);
    if (empty($subs)) return;

    $auth = [
        'VAPID' => [
            'subject'    => $keys['push_vapid_subject'] ?? 'mailto:evidence@kovopraha.cz',
            'publicKey'  => $keys['push_vapid_public'],
            'privateKey' => $keys['push_vapid_private'],
        ],
    ];

    $webPush = new \Minishlink\WebPush\WebPush($auth);
    $json    = json_encode(array_merge([
        'title' => 'Evidence tréninků',
        'body'  => '',
        'url'   => 'https://data.kovopraha.cz/evidence/',
        'tag'   => 'evidence',
    ], $payload), JSON_UNESCAPED_UNICODE);

    $invalidEndpoints = [];
    foreach ($subs as $sub) {
        $subscription = \Minishlink\WebPush\Subscription::create([
            'endpoint' => $sub['endpoint'],
            'keys'     => ['p256dh' => $sub['p256dh'], 'auth' => $sub['auth']],
        ]);
        $webPush->queueNotification($subscription, $json);
    }

    foreach ($webPush->flush() as $report) {
        if (!$report->isSuccess()) {
            error_log('push_helper: neúspěch pro endpoint ' . substr($report->getRequest()->getUri(), 0, 80));
            // Pokud je subscription neplatná (410 Gone), smazat ji
            if (in_array($report->getResponse()?->getStatusCode(), [404, 410], true)) {
                $ep = (string)$report->getRequest()->getUri();
                $pdo->prepare("DELETE FROM push_subscriptions WHERE endpoint=?")->execute([$ep]);
            }
        }
    }
}
