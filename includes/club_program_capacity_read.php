<?php

declare(strict_types=1);

require_once __DIR__ . '/club_program.php';

/** Bounded administrative GET only; canonical locking and checkout readers stay independent. */
final class ClubProgramCapacityRead
{
    private DateTimeImmutable $now;

    public function __construct(private PDO $pdo, private ClubProgramReadContext $context, ?DateTimeImmutable $now = null)
    {
        $context->assertConnection($pdo);
        $this->now = ($now ?? new DateTimeImmutable('now', new DateTimeZone('Europe/Prague')))
            ->setTimezone(new DateTimeZone('Europe/Prague'));
    }

    /** @param list<array<string,mixed>> $offers
     * @return array<int,array<string,mixed>>
     */
    public function states(array $offers): array
    {
        $requests = [];
        foreach ($offers as $offer) {
            $teams = clubProgramPhysicalTeamIds($this->pdo, $offer, $this->context);
            foreach ($teams !== [] ? $teams : [0] as $teamId) {
                $requests[] = ['offer' => $offer, 'team' => $teamId];
            }
        }
        $byOffer = [];
        foreach (array_chunk($requests, 80) as $batch) {
            $activeSql = $activeParams = $heldSql = $heldParams = [];
            foreach ($batch as $target => $request) {
                $plan = $this->plan($request['offer'], $request['team']);
                $batch[$target]['shared'] = $plan['shared'];
                // Ordinals are internal integers, not user-supplied identifiers or parameters.
                $activeSql[] = 'SELECT ' . $target . ' AS _read_target,' . $plan['active'];
                array_push($activeParams, ...$plan['active_params']);
                if ($plan['held'] !== null) {
                    $heldSql[] = 'SELECT ' . $target . ' AS _read_target,' . $plan['held'];
                    array_push($heldParams, ...$plan['held_params']);
                }
            }
            $active = $this->pdo->prepare(implode(' UNION ALL ', $activeSql));
            $active->execute($activeParams);
            $people = [];
            while (($row = $active->fetch(PDO::FETCH_NUM)) !== false) {
                $people[(int) $row[0]][(int) $row[1]] = true;
            }
            $held = null;
            $current = false;
            if ($heldSql !== []) {
                // Numeric ordinals keep every target contiguous without materializing PHP row arrays.
                $held = $this->pdo->prepare(implode(' UNION ALL ', $heldSql) . ' ORDER BY _read_target');
                $held->execute($heldParams);
                $current = $held->fetch(PDO::FETCH_ASSOC);
            }
            foreach ($batch as $target => $request) {
                $heldRows = (static function () use ($held, &$current, $target): Generator {
                    while ($current !== false && (int) $current['_read_target'] === $target) {
                        yield $current;
                        $current = $held->fetch(PDO::FETCH_ASSOC);
                    }
                })();
                $offer = $request['offer'];
                $byOffer[(int) $offer['id']][$request['team']] = clubProgramCapacityFromRows(
                    $people[$target] ?? [],
                    $heldRows,
                    $request['shared'],
                    0,
                    $offer['capacity'] ?? null
                );
            }
            if ($current !== false) {
                throw new LogicException('Unconsumed administrative capacity target.');
            }
        }
        $result = [];
        foreach ($byOffer as $offerId => $states) {
            if (count($states) === 1) {
                $result[$offerId] = reset($states);
                continue;
            }
            $most = array_reduce($states, static fn (?array $carry, array $state): array => $carry === null
                || $state['occupied_count'] > $carry['occupied_count'] ? $state : $carry, null);
            $most['available_count'] = min(array_column($states, 'available_count'));
            $most['physical_team_states'] = $states;
            $result[$offerId] = $most;
        }
        return $result;
    }

    /** Mirrors canonical selection predicates; differential tests guard schema and payment fallbacks.
     * @param array<string,mixed> $offer
     * @return array{active:string,active_params:list<mixed>,held:?string,held_params:list<mixed>,shared:bool}
     */
    private function plan(array $offer, int $teamId): array
    {
        $offerId = (int) ($offer['id'] ?? $offer['offer_id'] ?? 0);
        $productId = (int) ($offer['product_id'] ?? 0);
        $variantId = (int) ($offer['variant_id'] ?? 0);
        if (min($offerId, $productId, $variantId) < 1) {
            throw new LogicException('Výpočet kapacity vyžaduje nabídku, produkt a variantu.');
        }
        $startsOn = (string) ($offer['starts_on'] ?? '');
        $endsOn = (string) ($offer['ends_on'] ?? '');
        $shared = $teamId > 0 && clubProgramDateOrNull($startsOn) !== null && clubProgramDateOrNull($endsOn) !== null
            && $this->context->columnExists($this->pdo, 'club_program_enrollments', 'sportovec_id');
        $linked = $shared && $this->context->tableExists($this->pdo, 'club_program_physical_teams');
        $teamCondition = $linked
            ? '(x.team_id=? OR EXISTS(SELECT 1 FROM club_program_physical_teams pt WHERE pt.program_id=x.program_id AND pt.team_id=?))'
            : 'x.team_id=?';
        $overlapParams = $linked ? [$teamId, $teamId, $endsOn, $startsOn] : [$teamId, $endsOn, $startsOn];
        $active = $shared
            ? "e.sportovec_id FROM club_program_enrollments e JOIN club_program_offers x ON x.id=e.offer_id WHERE {$teamCondition} "
                . "AND x.starts_on<=? AND x.ends_on>=? AND e.status='active'"
            : "id FROM club_program_enrollments WHERE offer_id=? AND status='active'";
        $plan = ['active' => $active, 'active_params' => $shared ? $overlapParams : [$offerId],
            'held' => null, 'held_params' => [], 'shared' => $shared];
        if (!$this->context->columnExists($this->pdo, 'shop_order_items', 'variant_id')
            || !$this->context->columnExists($this->pdo, 'shop_orders', 'payment_expires_at')) {
            return $plan;
        }
        $beneficiary = $this->context->columnExists($this->pdo, 'shop_order_items', 'beneficiary_sportovec_id');
        $request = $this->context->columnExists($this->pdo, 'shop_order_items', 'athlete_registration_request_id');
        $beneficiarySelect = $beneficiary ? 'oi.beneficiary_sportovec_id' : 'NULL AS beneficiary_sportovec_id';
        $requestSelect = $request ? 'oi.athlete_registration_request_id' : 'NULL AS athlete_registration_request_id';
        $held = "oi.id,oi.quantity,{$beneficiarySelect},{$requestSelect} FROM shop_order_items oi JOIN shop_orders o ON o.id=oi.order_id ";
        if ($shared) {
            $held .= $this->context->columnExists($this->pdo, 'shop_order_items', 'club_program_offer_id')
                ? 'JOIN club_program_offers x ON x.id=oi.club_program_offer_id AND x.product_id=oi.product_id AND x.variant_id=oi.variant_id '
                : 'JOIN club_program_offers x ON x.product_id=oi.product_id AND x.variant_id=oi.variant_id ';
        }
        $held .= $shared ? "WHERE {$teamCondition} AND x.starts_on<=? AND x.ends_on>=? " : 'WHERE oi.product_id=? AND oi.variant_id=? ';
        $held .= "AND ((o.status='placed' AND o.payment_status='pending' AND o.payment_expires_at IS NOT NULL AND o.payment_expires_at>=?)";
        if ($beneficiary && $request) {
            $held .= " OR (o.status IN ('processing','ready','completed') AND o.payment_status='paid' "
                . 'AND oi.beneficiary_sportovec_id IS NULL AND oi.athlete_registration_request_id>0)';
        }
        $held .= ') ';
        if ($request && $this->context->columnExists($this->pdo, 'account_person_claim_requests', 'status')) {
            $held .= 'AND NOT EXISTS(SELECT 1 FROM account_person_claim_requests r WHERE r.id=oi.athlete_registration_request_id '
                . "AND r.status IN ('rejected','cancelled')) ";
        }
        if ($this->context->columnExists($this->pdo, 'club_program_enrollments', 'source_order_item_id')) {
            $held .= "AND NOT EXISTS(SELECT 1 FROM club_program_enrollments e WHERE e.source_order_item_id=oi.id AND e.status='active') ";
        }
        $plan['held'] = $held;
        $plan['held_params'] = array_merge($shared ? $overlapParams : [$productId, $variantId], [$this->now->format('Y-m-d H:i:s')]);
        return $plan;
    }
}
