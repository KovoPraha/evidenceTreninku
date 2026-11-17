<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
$root = dirname(__DIR__, 2);
$base = 'e30fdc97a369245478c25fd14c220df45aec3e18';
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
  "eshop_categories_admin.php": [
    [
      110,
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
      117,
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
      494,
      0,
      [
        "{"
      ]
    ],
    [
      533,
      0,
      [
        "}"
      ]
    ],
    [
      661,
      1,
      [
        [
          "T_CONSTANT_ENCAPSED_STRING",
          "\'SELECT p.id,p.name,p.origin,p.catalog_status,COUNT(pc.id) category_count FROM shop_products p \'"
        ],
        ".",
        [
          "T_CONSTANT_ENCAPSED_STRING",
          "\'LEFT JOIN shop_product_categories pc ON pc.product_id=p.id GROUP BY p.id,p.name,p.origin,p.catalog_status ORDER BY p.name,p.id\'"
        ]
      ]
    ],
    [
      699,
      0,
      [
        "{"
      ]
    ],
    [
      710,
      0,
      [
        "}"
      ]
    ],
    [
      778,
      0,
      [
        "{"
      ]
    ],
    [
      786,
      0,
      [
        "}"
      ]
    ],
    [
      804,
      1,
      [
        [
          "T_INLINE_HTML",
          "<!doctype html><html lang=\\"cs\\"><head><meta charset=\\"utf-8\\"><meta name=\\"viewport\\" content=\\"width=device-width,initial-scale=1\\"><title>Kategorie e-shopu</title>"
        ],
        [
          "T_ECHO",
          "echo"
        ],
        [
          "T_CONSTANT_ENCAPSED_STRING",
          "\'<link rel=\\"stylesheet\\" href=\\"https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css\\"\'"
        ],
        ".",
        [
          "T_CONSTANT_ENCAPSED_STRING",
          "\' integrity=\\"sha384-XGjxtQfXaH2tnPFa9x+ruJTuLE3Aa6LhHSWRr1XeTyhezb4abCG4ccI5AkVDxqC+\\" crossorigin=\\"anonymous\\">\'"
        ],
        ";",
        [
          "T_INLINE_HTML",
          "</head><body class=\\"bg-light\\">"
        ]
      ]
    ],
    [
      1055,
      0,
      [
        "{"
      ]
    ],
    [
      1057,
      0,
      [
        "}"
      ]
    ]
  ],
  "eshop_attributes_admin.php": [
    [
      110,
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
      117,
      0,
      [
        "{"
      ]
    ],
    [
      194,
      0,
      [
        "}"
      ]
    ],
    [
      265,
      0,
      [
        "{"
      ]
    ],
    [
      271,
      0,
      [
        "}"
      ]
    ],
    [
      383,
      1,
      [
        [
          "T_INLINE_HTML",
          "<!doctype html><html lang=\\"cs\\"><head><meta charset=\\"utf-8\\"><meta name=\\"viewport\\" content=\\"width=device-width,initial-scale=1\\"><title>Parametry e-shopu</title>"
        ],
        [
          "T_ECHO",
          "echo"
        ],
        [
          "T_CONSTANT_ENCAPSED_STRING",
          "\'<link rel=\\"stylesheet\\" href=\\"https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css\\"\'"
        ],
        ".",
        [
          "T_CONSTANT_ENCAPSED_STRING",
          "\' integrity=\\"sha384-XGjxtQfXaH2tnPFa9x+ruJTuLE3Aa6LhHSWRr1XeTyhezb4abCG4ccI5AkVDxqC+\\" crossorigin=\\"anonymous\\">\'"
        ],
        ";",
        [
          "T_INLINE_HTML",
          "</head><body class=\\"bg-light\\">"
        ]
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
