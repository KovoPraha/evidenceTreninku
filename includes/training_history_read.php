<?php

declare(strict_types=1);

require_once __DIR__ . '/training_roster_bridge.php';

/** Read projection for the two staff history routes; no authorization or write cache. */
final class TrainingHistoryRead
{
    public const SIZE = 25;

    public static function page(PDO $pdo, string $sql, array $params, int $team, int $requested): array
    {
        [$select, $from] = self::parts($sql);
        // Only these fields are needed for historical scope and exact whole-result statistics.
        $candidates = self::read($pdo, 'SELECT t.id,t.datum,t.kategorie,t.delka ' . $from
            . ' GROUP BY t.id ORDER BY t.datum DESC,t.id DESC', $params);
        if ($team > 0) {
            $candidates = trainingRosterBridgeFilterTrainingRows($pdo, $candidates, $team);
        }
        $total = count($candidates);
        $pages = max(1, (int) ceil($total / self::SIZE));
        $page = min(max(1, min(1000000, $requested)), $pages);
        $hours = array_sum(array_column($candidates, 'delka'));
        $categories = [];
        foreach ($candidates as $row) {
            $category = $row['kategorie'] ?? '';
            if ($category !== '') {
                $categories[$category] = ($categories[$category] ?? 0) + 1;
            }
        }
        $ids = array_column(array_slice($candidates, ($page - 1) * self::SIZE, self::SIZE), 'id');
        unset($candidates);
        $rows = [];
        if ($ids !== []) {
            $marks = implode(',', array_fill(0, count($ids), '?'));
            // Recheck the original predicates, including the trainer subset used by GROUP_CONCAT.
            $rows = self::read($pdo, $select . $from . ' AND t.id IN (' . $marks . ')'
                . ' GROUP BY t.id ORDER BY t.datum DESC,t.id DESC', [...$params, ...$ids]);
        }
        return ['rows' => $rows, 'total' => $total, 'page' => $page, 'pages' => $pages,
            'hours' => $hours, 'categories' => $categories];
    }

    /** SQL is supplied only by the two internal routes, never by request parameters. */
    public static function parts(string $sql): array
    {
        $from = strpos($sql, 'FROM treninky t');
        $end = strrpos($sql, ' GROUP BY t.id ORDER BY t.datum DESC');
        if ($from === false || $end === false || $end <= $from) {
            throw new InvalidArgumentException('Expected internal training history query.');
        }
        $body = substr($sql, $from, $end - $from);
        $wherePosition = strpos($body, 'WHERE ');
        if ($wherePosition === false) {
            throw new InvalidArgumentException('Expected internal history predicates.');
        }
        $where = substr($body, $wherePosition);
        // These LEFT JOINs contribute no projected fields. Unfiltered multiplicity is
        // already removed by GROUP BY and DISTINCT, so omit them unless WHERE uses them.
        foreach (['tg' => 'trenink_skupina', 'tp' => 'trenink_podskupina'] as $alias => $table) {
            if (!str_contains($where, $alias . '.')) {
                $body = preg_replace('~LEFT\s+JOIN\s+' . $table . '\s+' . $alias
                    . '\s+ON\s+t\.id\s*=\s*' . $alias . '\.trenink_id~i', ' ', $body);
            }
        }
        return [substr($sql, 0, $from), $body];
    }

    public static function requested(array $input): int
    {
        $value = $input['history_page'] ?? '';
        return is_scalar($value) ? max(1, min(1000000, (int) $value)) : 1;
    }

    public static function navigation(array $result, array $filters): void
    {
        $escape = static fn (string $s): string => htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
        echo '<nav class="my-3" aria-label="Stránkování tréninků"><p role="status">Nalezeno: '
            . (int) $result['total'] . ' · strana ' . (int) $result['page'] . ' z ' . (int) $result['pages'] . '</p>';
        foreach (['Předchozí' => $result['page'] - 1, 'Další' => $result['page'] + 1] as $label => $page) {
            if ($page >= 1 && $page <= $result['pages']) {
                echo '<a class="btn btn-outline-secondary me-2" href="?'
                    . $escape(http_build_query($filters + ['history_page' => $page])) . '">' . $label . '</a>';
            }
        }
        echo '</nav>';
    }

    private static function read(PDO $pdo, string $sql, array $params): array
    {
        $query = $pdo->prepare($sql);
        try {
            $query->execute($params);
            return $query->fetchAll(PDO::FETCH_ASSOC);
        } finally {
            $query->closeCursor();
        }
    }
}
