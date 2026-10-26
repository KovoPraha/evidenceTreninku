<?php
declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class DeploymentExposureTest extends TestCase
{
    public function testHistoricalArtifactsAndEveryDotPathAreDenied(): void
    {
        $htaccess = (string) file_get_contents(dirname(__DIR__, 2) . '/.htaccess');
        preg_match_all('/^\s*RewriteRule\s+(\S+)\s+-\s+\[F,L(?:,NC)?\]/m', $htaccess, $rules);
        foreach ([
            'ebook.pdf', '.phpstan-cache/resultCache.php', '.claude/settings.json',
            '.codex-work/session.txt', '.ssh/id_ed25519', '.git/config',
            '.php-cs-fixer.dist.php', 'assets/.hidden/probe.txt', 'booking/.secret',
            '.well-known/acme-challenge/synthetic-token', '.well-known/security.txt',
        ] as $path) {
            $denied = false;
            foreach ($rules[1] as $rule) {
                $denied = $denied || preg_match('~' . str_replace('~', '\\~', $rule) . '~i', $path) === 1;
            }
            self::assertTrue($denied, 'Historical artifact is still reachable: ' . $path);
        }
    }

    // These directories intentionally contain public application entry points/assets.
    private const PUBLIC_DIRECTORIES = [
        'assets', 'auth', 'booking', 'icons', 'templates',
        'auditlog', 'jizdy', 'servis', 'uctenky', 'udalosti', 'vozidla', 'zatezove_testy',
    ];

    public function testEveryTrackedNonPublicDirectoryIsExcludedOrDenied(): void
    {
        $root = dirname(__DIR__, 2);
        $command = 'git -C ' . escapeshellarg($root) . ' ls-files -z';
        exec($command, $output, $status);
        self::assertSame(0, $status, 'Tracked-directory inventory requires git.');
        $directories = [];
        foreach (explode("\0", implode("\n", $output)) as $path) {
            if (str_contains($path, '/')) {
                $directories[explode('/', $path, 2)[0]] = true;
            }
        }
        self::assertNotEmpty($directories);
        $workflow = (string) file_get_contents($root . '/.github/workflows/deploy-production.yml');
        preg_match_all('/rsync -a[^\n]*\n(?:(?!\s*rsync)[^\n]*\\\\+\r?\n)*/', $workflow, $blocks);
        self::assertCount(2, $blocks[0], 'Check both upload and activation rsync blocks.');
        $htaccess = (string) file_get_contents($root . '/.htaccess');
        preg_match_all('/^\s*RewriteRule\s+(\S+)\s+-\s+\[F,L(?:,NC)?\]/m', $htaccess, $rules);
        foreach (array_keys($directories) as $directory) {
            if (in_array($directory, self::PUBLIC_DIRECTORIES, true)) {
                continue;
            }
            $denied = false;
            foreach ($rules[1] as $rule) {
                $denied = $denied || preg_match('~' . str_replace('~', '\\~', $rule) . '~i', $directory . '/probe.txt') === 1;
            }
            foreach ($blocks[0] as $index => $block) {
                preg_match_all("/--exclude='([^']+)'/", $block, $excludes);
                $excluded = false;
                foreach ($excludes[1] as $pattern) {
                    $excluded = $excluded || fnmatch(rtrim($pattern, '/'), $directory);
                }
                self::assertTrue($denied || $excluded, $directory . ' is exposed by rsync block ' . ($index + 1));
            }
        }
        foreach (['outputs/', 'tools/', 'ebook.pdf'] as $excluded) {
            foreach ($blocks[0] as $block) {
                self::assertStringContainsString("--exclude='" . $excluded . "'", $block);
            }
        }
        self::assertStringContainsString('^(?:outputs|tools)(?:/|$) - [F,L,NC]', $htaccess);
    }
}
