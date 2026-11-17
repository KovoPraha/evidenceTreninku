<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
$root = dirname(__DIR__, 2);
$base = 'f8f1c17511a6826411b309d24910f37042dd5bf9';
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
$process = proc_open(['git', 'show', $base . ':includes/shop_attribute.php'], [1 => ['pipe', 'w'], 2 => STDERR], $pipes, $root);
if (!is_resource($process)) {
    throw new RuntimeException('Immutable source required.');
}
$before = str_replace("\r\n", "\n", (string)stream_get_contents($pipes[1]));
fclose($pipes[1]);
if (proc_close($process) !== 0) {
    throw new RuntimeException('Immutable source unavailable.');
}
$after = str_replace("\r\n", "\n", (string)file_get_contents($root . '/includes/shop_attribute.php'));
$scope = static function (string $source): array {
    $marker = 'function shopAttributeDiscoveredKeys(';
    if (substr_count($source, $marker) !== 1) {
        throw new RuntimeException('One discovery declaration required.');
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
                throw new RuntimeException('Code outside discovery scope.');
            }
            return [substr($source, 0, $start), $tail];
        }
    }
    throw new RuntimeException('Discovery scope unavailable.');
};
[$originalPrefix, $originalFunction] = $scope($before);
[$currentPrefix] = $scope($after);
$frozen = str_replace("\r\n", "\n", (string)file_get_contents(__DIR__ . '/AttributeDiscoveryBaseline.txt'));
if ($frozen !== $originalFunction) {
    throw new RuntimeException('Frozen algorithm differs from immutable baseline.');
}
// Exact brace-only formatting edits outside the functional optimization.
$edits = json_decode('[
  [
    112,
    0,
    [
      "{"
    ]
  ],
  [
    116,
    0,
    [
      "}"
    ]
  ],
  [
    288,
    0,
    [
      "{"
    ]
  ],
  [
    304,
    0,
    [
      "}"
    ]
  ],
  [
    336,
    0,
    [
      "{"
    ]
  ],
  [
    343,
    0,
    [
      "}"
    ]
  ],
  [
    350,
    0,
    [
      "{"
    ]
  ],
  [
    357,
    0,
    [
      "}"
    ]
  ],
  [
    363,
    0,
    [
      "{"
    ]
  ],
  [
    366,
    0,
    [
      "}"
    ]
  ],
  [
    495,
    0,
    [
      "{"
    ]
  ],
  [
    502,
    0,
    [
      "}"
    ]
  ],
  [
    592,
    0,
    [
      "{"
    ]
  ],
  [
    594,
    0,
    [
      "}"
    ]
  ],
  [
    618,
    0,
    [
      "{"
    ]
  ],
  [
    625,
    0,
    [
      "}"
    ]
  ],
  [
    652,
    0,
    [
      "{"
    ]
  ],
  [
    663,
    0,
    [
      "}"
    ]
  ],
  [
    683,
    0,
    [
      "{"
    ]
  ],
  [
    694,
    0,
    [
      "}"
    ]
  ],
  [
    739,
    0,
    [
      "{"
    ]
  ],
  [
    750,
    0,
    [
      "}"
    ]
  ],
  [
    796,
    0,
    [
      "{"
    ]
  ],
  [
    807,
    0,
    [
      "}"
    ]
  ],
  [
    827,
    0,
    [
      "{"
    ]
  ],
  [
    834,
    0,
    [
      "}"
    ]
  ],
  [
    924,
    0,
    [
      "{"
    ]
  ],
  [
    947,
    0,
    [
      "}"
    ]
  ],
  [
    990,
    0,
    [
      "{"
    ]
  ],
  [
    994,
    0,
    [
      "}"
    ]
  ],
  [
    1002,
    0,
    [
      "{"
    ]
  ],
  [
    1020,
    0,
    [
      "}"
    ]
  ],
  [
    1063,
    0,
    [
      "{"
    ]
  ],
  [
    1070,
    0,
    [
      "}"
    ]
  ],
  [
    1112,
    0,
    [
      "{"
    ]
  ],
  [
    1114,
    0,
    [
      "}"
    ]
  ],
  [
    1127,
    0,
    [
      "{"
    ]
  ],
  [
    1129,
    0,
    [
      "}"
    ]
  ],
  [
    1183,
    0,
    [
      "{"
    ]
  ],
  [
    1185,
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
    throw new RuntimeException('Other attribute logic changed.');
}
echo json_encode(['complete' => true, 'base' => $base, 'source_sha256' => hash('sha256', $after),
    'frozen_sha256' => hash('sha256', $frozen), 'only_discovery_function_changed' => true,
    'other_tokens_except_pinned_braces_equal' => true, 'pinned_braces' => $edits], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR), "\n";
