<?php
declare(strict_types=1);

/** Presentation only: activation continues to enforce all transactional checks. */
function clubProgramAccountItemState(array $item): array
{
    $state = static fn(string $code, string $label, string $detail, string $tone = 'secondary', bool $activate = false): array =>
        ['code'=>$code, 'label'=>$label, 'detail'=>$detail, 'tone'=>$tone, 'can_activate'=>$activate];
    $payment = (string)($item['payment_status'] ?? '');
    if ($payment === 'refund_required') return $state('refund_required', 'Čeká na vrácení peněz', 'Účast není aktivní. Vrácení přijaté platby vyřizuje klub.', 'warning');
    if ($payment === 'refunded') return $state('refunded', 'Peníze vráceny', 'Účast byla zrušena a klub potvrdil vrácení platby.');
    if (($item['order_status'] ?? '') === 'cancelled' || $payment === 'cancelled') return $state('cancelled', 'Objednávka zrušena', 'Z této objednávky nelze aktivovat účast.');
    if (in_array($item['request_status'] ?? '', ['rejected','cancelled'], true)) {
        return $state('request_closed', ($item['request_status'] === 'rejected' ? 'Registrace zamítnuta' : 'Registrace zrušena'), 'Podrobnosti žádosti najdete v Moje osoby.', 'warning');
    }
    if (!empty($item['enrollment_id'])) {
        if (($item['enrollment_status'] ?? '') !== 'active') return $state('enrollment_cancelled', 'Účast ukončena', 'Dřívější aktivace již neopravňuje k účasti.');
        if (($item['roster_status'] ?? '') !== 'active') return $state('roster_review', 'Soupiska vyžaduje kontrolu', 'Účast je evidována, ale členství v soupisce není aktivní. Kontaktujte klub.', 'warning');
        return $state('active', 'Účast aktivována', 'Zařazení do soupisky a platnost období najdete níže.', 'success');
    }
    if (!(bool)($item['account_active'] ?? false)) return $state('account_inactive', 'Účet není aktivní', 'Další postup ověřte s klubem.', 'warning');
    if (!(bool)($item['email_overeno'] ?? false)) return $state('email_pending', 'Ověřte e-mail', 'Dokončete ověření e-mailu, aby klub mohl schválit přihlášku.', 'warning');
    if ((int)($item['line_amount_minor'] ?? 0) > 0 && $payment !== 'paid') {
        return $state('payment_pending', 'Čeká na potvrzení platby', 'Stav platby a pokyny najdete v detailu objednávky.', 'warning');
    }
    if (empty($item['beneficiary_sportovec_id'])) return $state('identity_pending', 'Čeká na kontrolu sportovce', ($payment === 'paid' ? 'Platbu evidujeme. ' : '').'Po schválení žádosti klubem se účast aktivuje automaticky.', 'info');
    if (($item['offer_status'] ?? '') !== 'active'
        || (int)($item['quantity'] ?? 0) !== 1
        || (int)($item['line_amount_minor'] ?? -1) < 0
        || empty($item['terms_valid'])
        || !empty($item['duplicate_enrollment'])
        || (!empty($item['roster_status']) && $item['roster_status'] !== 'active' && ($item['roster_source'] ?? '') !== 'shop')
        || ((int)$item['line_amount_minor'] > 0 && !in_array($item['order_status'] ?? '', ['processing','ready','completed'], true))) {
        return $state('review', 'Aktivaci ověří klub', 'Objednávka vyžaduje kontrolu před zařazením do soupisky.', 'warning');
    }
    return $state('ready', 'Připraveno k aktivaci', 'Aktivací se sportovec zařadí do soupisky zakoupeného období.', 'info', true);
}

function clubProgramAccountPaymentLabel(string $status): string
{
    return ['pending'=>'Čeká na potvrzení', 'paid'=>'Zaplaceno', 'cancelled'=>'Předpis zrušen',
        'refund_required'=>'Čeká na vrácení', 'refunded'=>'Peníze vráceny'][$status] ?? 'Stav ověřuje klub';
}

function clubProgramAccountEnrollmentLabel(array $enrollment, ?string $today = null): string
{
    if (($enrollment['status'] ?? '') !== 'active') return 'Účast ukončena';
    $today ??= date('Y-m-d');
    if ((string)$enrollment['valid_to'] < $today) return 'Období skončilo';
    if (($enrollment['roster_status'] ?? '') !== 'active') return 'Soupiska vyžaduje kontrolu';
    return (string)$enrollment['valid_from'] > $today ? 'Aktivováno pro budoucí období' : 'Aktivní účast';
}
