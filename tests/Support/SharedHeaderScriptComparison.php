<?php

declare(strict_types=1);

/** Test-only canonical context; reject every unrecognised script or extra field. */
function sharedHeaderScriptContext(array $context, string $html): ?array
{
    $keys = ['pushPublicKey', 'serviceWorkerUrl', 'appBaseUrl', 'pushEndpoint', 'csrfToken', 'searchUrl'];
    if (array_keys($context) !== $keys || !is_string($context['csrfToken'])
        || !preg_match('/^[a-f0-9]{64}$/D', $context['csrfToken'])
    ) {
        return null;
    }
    preg_match_all('/name="csrf_token"\s+value="([a-f0-9]{64})"/i', $html, $fields);
    preg_match_all('/const CSRF_TOKEN\s*=\s*"([a-f0-9]{64})"/i', $html, $constants);
    foreach (array_merge($fields[1], $constants[1]) as $token) {
        if ($token !== $context['csrfToken']) {
            return null;
        }
    }
    $context['csrfToken'] = 'SYNTHETIC_CSRF';
    return $context;
}

/** Allow only the frozen legacy script versus its verified JSON + local bundle. */
function sharedHeaderScriptComparisonHtml(string $html): string
{
    $reference = str_replace("\r\n", "\n", (string) file_get_contents(__DIR__ . '/SharedHeaderScriptReference.php.txt'));
    $parts = preg_split('/<\?=.*?\?>/s', $reference);
    if ($parts === false || count($parts) !== 11) {
        throw new RuntimeException('Invalid frozen staff script.');
    }
    $json = '("(?:\\\\.|[^"\\\\])*"|null|true|false|-?[0-9]+(?:\.[0-9]+)?)';
    $pattern = '~' . preg_quote($parts[0], '~') . '([^"]+)' . preg_quote($parts[1], '~');
    for ($i = 2; $i < count($parts); $i++) {
        $pattern .= $json . preg_quote($parts[$i], '~');
    }
    $pattern .= '~s';
    if (preg_match_all($pattern, $html, $old, PREG_SET_ORDER) === 1) {
        if (str_contains($html, 'id="appStaffHeaderConfig"') || str_contains($html, 'assets/staff-header.')) {
            return $html;
        }
        $context = [];
        $fields = ['pushPublicKey', 'serviceWorkerUrl', 'appBaseUrl', 'pushEndpoint', 'csrfToken',
            'pushEndpoint', 'csrfToken', 'searchUrl', 'appBaseUrl'];
        foreach ($fields as $i => $key) {
            $value = json_decode($old[0][$i + 2], true, 512, JSON_THROW_ON_ERROR);
            if (array_key_exists($key, $context) && $context[$key] !== $value) {
                return $html;
            }
            $context[$key] = $value;
        }
        $context = sharedHeaderScriptContext($context, $html);
        if ($context === null) {
            return $html;
        }
        return str_replace($old[0][0], sharedHeaderScriptMarker($context), $html);
    }
    $metadata = json_decode((string) file_get_contents(__DIR__ . '/SharedHeaderScriptReference.json'), true, 512, JSON_THROW_ON_ERROR);
    $new = '~<script type="application/json" id="appStaffHeaderConfig" nonce="([^"]+)">\s*(\{.*?\})\s*</script>\s*'
        . '<script nonce="([^"]+)"\s+src="(/(?!/)[^"]*' . preg_quote($metadata['asset_path'], '~') . ')"'
        . ' integrity="' . preg_quote($metadata['asset_sri'], '~') . '" crossorigin="anonymous"></script>~s';
    if (preg_match_all($new, $html, $matches, PREG_SET_ORDER) !== 1 || $matches[0][1] !== $matches[0][3]) {
        return $html;
    }
    if (substr_count($html, 'id="appStaffHeaderConfig"') !== 1 || substr_count($html, 'assets/staff-header.') !== 1) {
        return $html;
    }
    $context = json_decode($matches[0][2], true, 512, JSON_THROW_ON_ERROR);
    $context = is_array($context) ? sharedHeaderScriptContext($context, $html) : null;
    if ($context === null) {
        return $html;
    }
    return str_replace($matches[0][0], sharedHeaderScriptMarker($context), $html);
}

function sharedHeaderScriptMarker(array $context): string
{
    return '<!-- verified-staff-header-context:' . hash('sha256', json_encode($context, JSON_THROW_ON_ERROR)) . ' -->';
}

/** Normalise only request nonce and verified per-session CSRF; preserve every other byte. */
function sharedHeaderScriptResponseHtml(array $response): string
{
    $html = str_replace("\r\n", "\n", $response['body']);
    if (preg_match("/nonce-([^']+)/", $response['headers']['content-security-policy'] ?? '', $nonce)) {
        $html = str_replace($nonce[1], 'SYNTHETIC_NONCE', $html);
    }
    $html = sharedHeaderScriptComparisonHtml($html);
    preg_match_all('/name="csrf_token"\s+value="([a-f0-9]{64})"/i', $html, $fields);
    preg_match_all('/const CSRF_TOKEN\s*=\s*"([a-f0-9]{64})"/i', $html, $constants);
    $tokens = array_values(array_unique(array_merge($fields[1], $constants[1])));
    if (count($tokens) > 1) {
        throw new RuntimeException('Inconsistent CSRF values within parent.');
    }
    return $tokens === [] ? $html : str_replace($tokens[0], 'SYNTHETIC_CSRF', $html);
}
