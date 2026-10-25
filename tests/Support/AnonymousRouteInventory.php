<?php
declare(strict_types=1);

// Explicit exceptions, reviewed as routes rather than inferred from source strings.
function anonymousPublicRoutes(): array
{
    return [
        'index.php' => 'Public landing page.',
        'login.php' => 'Staff sign-in.',
        'registrace.php' => 'Public account registration entry.',
        'sitemap.php' => 'Public search-engine sitemap.',
        'pub.php' => 'Published group view gated by an unguessable link.',
        'sportovec_treninky.php' => 'Published athlete view gated by a token.',
        'public_profile_access.php' => 'Token exchange for a published profile.',
        'ajax_sportovec_poznamka.php' => 'Published profile token plus CSRF and athlete/family authorization.',
        'program_skupiny.php' => 'Published group programme, token-scoped.',
        'booking/cyklisticke_krouzky.php' => 'Public course catalogue.',
        'booking/eshop.php' => 'Public shop catalogue.',
        'booking/kalendar.php' => 'Public lesson calendar.',
        'booking/klubovy_kalendar.php' => 'Public club calendar; private participation requires sign-in.',
        'booking/krouzky.php' => 'Public event offers; registration requires account.',
        'booking/nove_heslo.php' => 'Password reset with one-time token.',
        'booking/objednavka.php' => 'Guest order access with secret access token or account.',
        'booking/overeni.php' => 'One-time email verification.',
        'booking/potvrdit.php' => 'One-time booking approval token.',
        'booking/prihlaseni.php' => 'Customer sign-in.',
        'booking/produkt.php' => 'Public product detail.',
        'booking/prvni_heslo.php' => 'First password with one-time token.',
        'booking/registrace.php' => 'Customer registration.',
        'booking/rodinny_kalendar.php' => 'Private calendar subscription using bearer token.',
        'booking/rychla_prihlaska.php' => 'Public course checkout entry.',
        'booking/rychly_nakup.php' => 'Public guest checkout entry.',
        'booking/sportovec_prihlaseni.php' => 'Athlete sign-in.',
        'booking/stripe_webhook.php' => 'Provider callback; signature verified separately.',
        'booking/sumup_webhook.php' => 'Provider callback; server-side payment retrieval.',
        'booking/treninky.php' => 'Published training schedule.',
        'booking/velodrom.php' => 'Public velodrome offers; reservations require account.',
        'booking/verejny_kalendar.php' => 'Published calendar feed.',
        'booking/zapomenute_heslo.php' => 'Password reset request.',
        'booking/znovu_overit_email.php' => 'Email verification request.',
    ];
}

function anonymousLibraryFiles(): array
{
    return [
        'auth/sso_bridge.php' => 'Function-only optional SSO adapter.',
        'booking/waiting_list.php' => 'Function-only queue helper.',
        'config.example.php' => 'Example constants, never an application route.',
        'csrf_helper.php' => 'CSRF library; Apache FilesMatch denies direct access.',
        'db.php' => 'Database bootstrap; Apache FilesMatch denies direct access.',
        'hlavicka.php' => 'Shared HTML include, no standalone page contract.',
        'report_tyden_lib.php' => 'Function-only weekly report library.',
    ];
}

function anonymousEntryPoints(string $root): array
{
    $excluded = ['includes', 'vendor', 'tests', 'migrations', 'bin', 'tools', 'database', '.git'];
    $directory = new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS);
    $filter = new RecursiveCallbackFilterIterator($directory, static function ($file) use ($excluded): bool {
        return !$file->isDir() || !in_array($file->getFilename(), $excluded, true);
    });
    $routes = [];
    foreach (new RecursiveIteratorIterator($filter) as $file) {
        if (!$file->isFile() || $file->getExtension() !== 'php') {
            continue;
        }
        $relative = str_replace('\\', '/', substr($file->getPathname(), strlen($root) + 1));
        if ($relative === 'config.php' || isset(anonymousLibraryFiles()[$relative])) {
            continue;
        }
        $routes[] = $relative;
    }
    sort($routes);
    return $routes;
}
