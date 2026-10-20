<?php
declare(strict_types=1);

require_once __DIR__ . '/app_url.php';

/**
 * Decisions and their outbox row commit together. Never include internal decision
 * notes, birth numbers, medical details or bearer links in these messages.
 */
function parentJourneyNotificationEnqueueDecision(PDO $pdo, int $requestId, bool $refundRequired = false): int
{
    if (!$pdo->inTransaction() || $requestId < 1) {
        throw new LogicException('Oznámení rozhodnutí vyžaduje transakci žádosti.');
    }
    $statement = $pdo->prepare(
        'SELECT c.status,vu.email FROM account_person_claim_requests c '
        . 'JOIN verejni_uzivatele vu ON vu.id=c.account_id WHERE c.id=?'
    );
    $statement->execute([$requestId]);
    $claim = $statement->fetch(PDO::FETCH_ASSOC);
    $status = (string)($claim['status'] ?? '');
    $text = match ($status) {
        'approved' => 'Klub schválil vaši žádost o připojení sportovce k účtu. '
            . 'Připojenou osobu najdete v části Moje osoby. Samotné schválení údajů ještě nepotvrzuje účast v kroužku; '
            . 'stav zakoupeného období a zařazení do soupisky najdete v části Moje kroužky.',
        'rejected' => 'Klub vaši žádost o připojení sportovce neschválil. '
            . 'Žádost najdete v části Moje osoby; další postup prosím domluvte s klubem.',
        'cancelled' => 'Vaše žádost o připojení sportovce byla zrušena. Stav žádosti najdete v části Moje osoby.',
        default => throw new RuntimeException('Žádost nemá konečné rozhodnutí k oznámení.'),
    };
    if ($refundRequired) {
        $text .= "\n\nU související zrušené objednávky evidujeme přijatou platbu. "
            . 'Vrácení peněz musí vyřídit správce klubu; tato zpráva nepotvrzuje, že již byly peníze vráceny.';
    }
    $body = "Dobrý den,\n\nŽádost #" . $requestId . "\n" . $text
        . "\n\nMoje osoby: " . appUrl('booking/moje_osoby.php')
        . "\nMoje kroužky: " . appUrl('booking/moje_programy.php')
        . "\n\nKlub KOVO Praha";
    $label = match ($status) {
        'approved' => 'schválena',
        'rejected' => 'neschválena',
        'cancelled' => 'zrušena',
    };
    return parentJourneyNotificationInsert(
        $pdo, 'claim:' . $requestId . ':' . $status, 'identity_' . $status,
        (string)$claim['email'], 'Žádost #' . $requestId . ' byla ' . $label, $body
    );
}

/** Used only for activation after the payment transition has already completed. */
function parentJourneyNotificationEnqueueActivation(PDO $pdo, int $enrollmentId): int
{
    if (!$pdo->inTransaction() || $enrollmentId < 1) {
        throw new LogicException('Oznámení aktivace vyžaduje transakci účasti.');
    }
    $statement = $pdo->prepare(
        'SELECT vu.email,o.public_code,co.name,co.starts_on,co.ends_on FROM club_program_enrollments e '
        . 'JOIN club_program_offers co ON co.id=e.offer_id '
        . 'JOIN shop_order_items oi ON oi.id=e.source_order_item_id '
        . 'JOIN shop_orders o ON o.id=oi.order_id '
        . "JOIN verejni_uzivatele vu ON vu.id=o.account_id WHERE e.id=? AND e.status='active'"
    );
    $statement->execute([$enrollmentId]);
    $row = $statement->fetch(PDO::FETCH_ASSOC);
    if (!$row) throw new RuntimeException('Aktivní účast k oznámení nebyla nalezena.');
    $body = "Dobrý den,\n\nKroužek z objednávky " . $row['public_code']
        . " byl aktivován a sportovec byl zařazen do soupisky.\n"
        . 'Období: ' . $row['name'] . ' (' . $row['starts_on'] . ' – ' . $row['ends_on'] . ')'
        . "\n\nPodrobnosti a aktuální stav: " . appUrl('booking/moje_programy.php')
        . "\n\nKlub KOVO Praha";
    return parentJourneyNotificationInsert(
        $pdo, 'enrollment:' . $enrollmentId . ':activated', 'program_activated',
        (string)$row['email'], 'Kroužek aktivován – objednávka ' . $row['public_code'], $body
    );
}

function parentJourneyNotificationInsert(
    PDO $pdo, string $key, string $type, string $email, string $subject, string $body
): int {
    if (!$pdo->inTransaction()) throw new LogicException('Oznámení vyžaduje otevřenou transakci.');
    if (!filter_var($email, FILTER_VALIDATE_EMAIL) || preg_match('/[\r\n]/', $email . $subject)) {
        throw new RuntimeException('Oznámení nemá platného příjemce nebo předmět.');
    }
    try {
        $pdo->prepare(
            'INSERT INTO club_event_notifications '
            . '(dedupe_key,notification_type,recipient_email,recipient_name,subject_plain,body_plain) '
            . "VALUES (?,?,?,'',?,?)"
        )->execute([$key, $type, $email, $subject, $body]);
        return (int)$pdo->lastInsertId();
    } catch (PDOException $exception) {
        if ((string)$exception->getCode() !== '23000') throw $exception;
        $existing = $pdo->prepare('SELECT id FROM club_event_notifications WHERE dedupe_key=?');
        $existing->execute([$key]);
        $id = (int)$existing->fetchColumn();
        if ($id < 1) throw $exception;
        return $id;
    }
}
