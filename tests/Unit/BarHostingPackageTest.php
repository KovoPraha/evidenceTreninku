<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__, 2) . '/includes/bar/transfer_package.php';
require_once dirname(__DIR__, 2) . '/bin/bar-cutover-prepare.php';

final class BarHostingPackageTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/bar-hosting-package-' . bin2hex(random_bytes(6));
        foreach (['images', 'plain/data/.kis-backups', 'withgit/data/.kis-backups'] as $path) {
            mkdir($this->root . '/' . $path, 0700, true);
        }
        file_put_contents($this->root . '/withgit/.git', 'LOCALHOST synthetic Git marker');
        $image = imagecreatetruecolor(8, 4);
        imagepng($image, $this->root . '/images/tea.png');
        imagedestroy($image);
        $source = ['categories' => [['id' => 1, 'nazev' => 'LOCALHOST Drinks']],
            'products' => [['id' => 1, 'kategorie_id' => 1, 'nazev' => 'LOCALHOST Tea', 'cena' => '35.00',
                'skladovy_rezim' => 'sledovat', 'obrazek' => 'tea.png']], 'recipes' => [],
            'lucka' => ['id' => 77, 'jmeno' => 'lucka', 'role' => 'prodejce', 'schvalen' => 1,
                'heslo_hash' => password_hash('LOCALHOST-only', PASSWORD_DEFAULT)]];
        file_put_contents($this->root . '/bundle.json', json_encode(\barBuildTransferBundle($source, $this->root . '/images'), JSON_THROW_ON_ERROR));
    }

    protected function tearDown(): void
    {
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($this->root, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST);
        foreach ($iterator as $file) {
            if (!str_starts_with($file->getPathname(), $this->root . DIRECTORY_SEPARATOR)) {
                throw new \RuntimeException('Unsafe owned package cleanup.');
            }
            if ($file->isLink() || $file->isFile()) {
                unlink($file->getPathname());
            } else {
                rmdir($file->getPathname());
            }
        }
        rmdir($this->root);
    }

    private function child(string $workspace): array
    {
        $repository = dirname(__DIR__, 2);
        $script = '<?php require ' . var_export($repository . '/includes/bar/transfer_package.php', true) . '; require '
            . var_export($repository . '/bin/bar-cutover-prepare.php', true) . '; $root=' . var_export($this->root, true) . ';'
            . '$parent=$root.' . var_export('/' . $workspace . '/data/.kis-backups', true) . ';'
            . '$bundle=json_decode(file_get_contents($root."/bundle.json"),true,32,JSON_THROW_ON_ERROR);'
            . 'try { barCutoverWritePackage($bundle,$root."/images",$parent."/bar-transfer-123",$parent);'
            . '$stored=json_decode(file_get_contents($parent."/bar-transfer-123/bundle.json"),true);'
            . 'echo json_encode(["ok"=>true,"valid"=>barValidateTransferBundle($stored,$parent."/bar-transfer-123/images")===$bundle]);'
            . '} catch (InvalidArgumentException) { echo json_encode(["blocked"=>true]); }';
        file_put_contents($this->root . '/run.php', $script);
        $scope = str_replace('\\', '/', $repository . PATH_SEPARATOR . $this->root);
        $process = proc_open([PHP_BINARY, '-d', 'open_basedir="' . $scope . '"',
            '-d', 'display_errors=1', $this->root . '/run.php'], [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
        self::assertIsResource($process);
        fclose($pipes[0]);
        $out = stream_get_contents($pipes[1]);
        $err = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        self::assertSame(0, proc_close($process), $out . $err);
        self::assertSame('', $err);
        self::assertStringNotContainsString('Warning', $out);
        return json_decode($out, true, 8, JSON_THROW_ON_ERROR);
    }

    public function testHostingFilesystemBoundaryDoesNotPolluteTheManifestOrProbeOutsideIt(): void
    {
        self::assertSame(['ok' => true, 'valid' => true], $this->child('plain'));
    }

    public function testGitAncestorWithinTheAllowedScopeStillBlocksCredentialPackaging(): void
    {
        self::assertSame(['blocked' => true], $this->child('withgit'));
        self::assertDirectoryDoesNotExist($this->root . '/withgit/data/.kis-backups/bar-transfer-123');
    }

    public function testPinnedMetadataCannotBeSubstitutedAndDoesNotHideChangedImageBytes(): void
    {
        $bundle = json_decode((string)file_get_contents($this->root . '/bundle.json'), true, 32, JSON_THROW_ON_ERROR);
        $cache = \barCutoverLoadImageCache($this->root . '/bundle.json', $bundle['sha256']);
        $source = ['products' => [['id' => 1, 'obrazek' => 'tea.png']]];
        $snapshot = \barCutoverSnapshotImages($source, $this->root . '/images', $this->root . '/snapshot', $cache);
        self::assertSame($bundle['images'][0]['sha256'], $snapshot['map'][0]['sha256']);
        file_put_contents($this->root . '/images/tea.png', 'LOCALHOST-corrupted-image');
        try {
            \barCutoverSnapshotImages($source, $this->root . '/images', $this->root . '/snapshot', $cache);
            self::fail('Changed bytes were accepted from cached metadata.');
        } catch (\InvalidArgumentException) {
            self::assertTrue(true);
        }
        $bundle['images'][0]['width_px']++;
        $oldHash = $bundle['sha256'];
        $bundle['sha256'] = \barTransferHash($bundle);
        file_put_contents($this->root . '/bundle.json', json_encode($bundle, JSON_THROW_ON_ERROR));
        $this->expectException(\RuntimeException::class);
        \barCutoverLoadImageCache($this->root . '/bundle.json', $oldHash);
    }
}
