<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
$root = dirname(__DIR__, 2);
$base = '832048619c3b5c4977e614a5c746979e03a30815';
$signature = static function (string $source): array {
    $result = [];
    foreach (token_get_all($source) as $token) {
        if (is_array($token)) {
            if (in_array($token[0], [T_OPEN_TAG, T_CLOSE_TAG, T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true)) {
                continue;
            }
            $result[] = [token_name($token[0]), $token[1]];
        } else {
            $result[] = $token;
        }
    }
    return $result;
};
$process = proc_open(['git', 'show', $base . ':includes/shop_category.php'], [1 => ['pipe', 'w'], 2 => STDERR], $pipes, $root);
if (!is_resource($process)) {
    throw new RuntimeException('Immutable source required.');
}
$before = str_replace("\r\n", "\n", (string)stream_get_contents($pipes[1]));
fclose($pipes[1]);
if (proc_close($process) !== 0) {
    throw new RuntimeException('Immutable source unavailable.');
}
$after = str_replace("\r\n", "\n", (string)file_get_contents($root . '/includes/shop_category.php'));
$scope = static function (string $source): array {
    $marker = 'function shopStorefrontCategoryMenu(';
    if (substr_count($source, $marker) !== 1) {
        throw new RuntimeException('One menu declaration required.');
    }
    $start = strpos($source, $marker);
    $tail = substr($source, $start);
    $depth = 0;
    $consumed = 0;
    foreach (token_get_all('<?php ' . $tail) as $token) {
        $consumed += strlen(is_array($token) ? $token[1] : $token);
        if ($token === '{') {
            $depth++;
        }
        if ($token === '}' && --$depth === 0) {
            if (trim(substr($tail, $consumed - 6)) !== '') {
                throw new RuntimeException('Code outside menu scope.');
            }
            return [substr($source, 0, $start), $tail];
        }
    }
    throw new RuntimeException('Discovery scope unavailable.');
};
[$originalPrefix, $originalFunction] = $scope($before);
[$currentPrefix] = $scope($after);
$frozen = str_replace("\r\n", "\n", (string)file_get_contents(__DIR__ . '/CategoryMenuBaseline.txt'));
if ($frozen !== $originalFunction) {
    throw new RuntimeException('Frozen algorithm differs from immutable baseline.');
}
// Exact brace-only formatting edits outside the functional optimization.
$edits = json_decode('[
  [
    99,
    0,
    [
      "{"
    ]
  ],
  [
    106,
    0,
    [
      "}"
    ]
  ],
  [
    124,
    0,
    [
      "{"
    ]
  ],
  [
    131,
    0,
    [
      "}"
    ]
  ],
  [
    166,
    0,
    [
      "{"
    ]
  ],
  [
    169,
    0,
    [
      "}"
    ]
  ],
  [
    315,
    0,
    [
      "{"
    ]
  ],
  [
    322,
    0,
    [
      "}"
    ]
  ],
  [
    427,
    0,
    [
      "{"
    ]
  ],
  [
    434,
    0,
    [
      "}"
    ]
  ],
  [
    623,
    0,
    [
      "{"
    ]
  ],
  [
    627,
    0,
    [
      "}"
    ]
  ],
  [
    664,
    0,
    [
      "{"
    ]
  ],
  [
    666,
    0,
    [
      "}"
    ]
  ],
  [
    680,
    0,
    [
      "{"
    ]
  ],
  [
    693,
    0,
    [
      "{"
    ]
  ],
  [
    699,
    0,
    [
      "}",
      "}"
    ]
  ],
  [
    751,
    0,
    [
      "{"
    ]
  ],
  [
    755,
    0,
    [
      "}"
    ]
  ],
  [
    873,
    0,
    [
      "{"
    ]
  ],
  [
    878,
    0,
    [
      "}"
    ]
  ],
  [
    944,
    0,
    [
      "{"
    ]
  ],
  [
    946,
    0,
    [
      "}"
    ]
  ],
  [
    1000,
    0,
    [
      "{"
    ]
  ],
  [
    1025,
    0,
    [
      "}"
    ]
  ],
  [
    1121,
    0,
    [
      "{"
    ]
  ],
  [
    1123,
    0,
    [
      "}"
    ]
  ]
]', true, 512, JSON_THROW_ON_ERROR);
$expected = $signature($originalPrefix);
foreach (array_reverse($edits) as [$position, $length, $replacement]) {
    array_splice($expected, $position, $length, $replacement);
}
if ($expected !== $signature($currentPrefix)) {
    throw new RuntimeException('Other category logic changed.');
}
echo json_encode(['complete' => true, 'base' => $base, 'source_sha256' => hash('sha256', $after),
    'frozen_sha256' => hash('sha256', $frozen), 'only_menu_function_changed' => true,
    'other_tokens_except_pinned_braces_equal' => true, 'pinned_braces' => $edits], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR), "\n";
