<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
$root = dirname(__DIR__, 2);
$base = 'f9b27e4ca0e12769e702a3ff3c170fcb7d05b09e';
$signature = static function (string $source): array {
    $result = [];
    foreach (token_get_all($source) as $token) {
        if (is_array($token)) {
            if (in_array($token[0], [T_OPEN_TAG, T_CLOSE_TAG, T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true)) {
                continue;
            }
            $type = token_name($token[0]);
            $last = array_key_last($result);
            if ($type === 'T_INLINE_HTML' && $last !== null && is_array($result[$last]) && $result[$last][0] === 'T_INLINE_HTML') {
                $result[$last][1] .= $token[1];
                continue;
            }
            $result[] = [$type, $token[1]];
        } else {
            $result[] = $token;
        }
    }
    return $result;
};

// Only pinned guard braces and a value-identical split of one long SQL literal.
$edits = json_decode('{
  "club_calendar.php": [
    [
      140,
      0,
      [
        "{"
      ]
    ],
    [
      143,
      0,
      [
        "}"
      ]
    ],
    [
      154,
      0,
      [
        "{"
      ]
    ],
    [
      161,
      0,
      [
        "}"
      ]
    ],
    [
      313,
      0,
      [
        "{"
      ]
    ],
    [
      320,
      0,
      [
        "}"
      ]
    ],
    [
      387,
      0,
      [
        "{"
      ]
    ],
    [
      393,
      0,
      [
        "}"
      ]
    ],
    [
      394,
      0,
      [
        "{"
      ]
    ],
    [
      697,
      0,
      [
        "{"
      ]
    ],
    [
      704,
      0,
      [
        "}"
      ]
    ],
    [
      883,
      0,
      [
        "{"
      ]
    ],
    [
      890,
      0,
      [
        "}"
      ]
    ],
    [
      955,
      0,
      [
        "}"
      ]
    ],
    [
      1026,
      0,
      [
        "{"
      ]
    ],
    [
      1032,
      0,
      [
        "}"
      ]
    ],
    [
      1090,
      0,
      [
        "{"
      ]
    ],
    [
      1103,
      0,
      [
        "}"
      ]
    ],
    [
      1145,
      1,
      [
        [
          "T_CONSTANT_ENCAPSED_STRING",
          "\\"SELECT id,jmeno,prijmeni FROM sportovci WHERE CONCAT(prijmeni COLLATE utf8mb4_unicode_ci,\' \',jmeno COLLATE utf8mb4_unicode_ci) LIKE ? OR \\""
        ],
        ".",
        [
          "T_CONSTANT_ENCAPSED_STRING",
          "\\"CONCAT(jmeno COLLATE utf8mb4_unicode_ci,\' \',prijmeni COLLATE utf8mb4_unicode_ci) LIKE ? OR id=? ORDER BY prijmeni,jmeno,id LIMIT 25\\""
        ]
      ]
    ],
    [
      1280,
      0,
      [
        "{"
      ]
    ],
    [
      1302,
      0,
      [
        "}"
      ]
    ]
  ],
  "eshop_catalog_admin.php": [
    [
      143,
      0,
      [
        "{"
      ]
    ],
    [
      149,
      0,
      [
        "}"
      ]
    ],
    [
      150,
      0,
      [
        "{"
      ]
    ],
    [
      423,
      0,
      [
        "{"
      ]
    ],
    [
      430,
      0,
      [
        "}"
      ]
    ],
    [
      477,
      0,
      [
        "{"
      ]
    ],
    [
      488,
      0,
      [
        "}"
      ]
    ],
    [
      516,
      0,
      [
        "}"
      ]
    ]
  ]
}', true, 512, JSON_THROW_ON_ERROR);
$tag = '<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" '
    . 'integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">';
$routes = [];
foreach ($edits as $path => $changes) {
    $process = proc_open(['git','show',$base . ':' . $path], [1 => ['pipe','w'],2 => STDERR], $pipes, $root);
    if (!is_resource($process)) {
        throw new RuntimeException('Baseline required.');
    }
    $before = str_replace("\r\n", "\n", (string)stream_get_contents($pipes[1]));
    fclose($pipes[1]);
    if (proc_close($process) !== 0 || substr_count($before, $tag) !== 1) {
        throw new RuntimeException('Exact source required.');
    }
    $tokens = $signature(str_replace($tag, '', $before));
    foreach (array_reverse($changes) as [$position,$length,$replacement]) {
        array_splice($tokens, $position, $length, $replacement);
    }
    $after = str_replace("\r\n", "\n", (string)file_get_contents($root . '/' . $path));
    if ($tokens !== $signature($after)) {
        throw new RuntimeException('Unexpected PHP token or literal HTML: ' . $path);
    }
    $routes[$path] = ['sha256' => hash('sha256', $after),'literal_html_except_exact_css_equal' => true,
        'php_tokens_except_pinned_changes_equal' => true,'pinned_changes' => $changes];
}
echo json_encode(['complete' => true,'base' => $base,'routes' => $routes], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR),"\n";
