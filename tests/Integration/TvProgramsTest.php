<?php

declare(strict_types=1);

namespace Tests\Integration;

use PDO;
use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__, 2) . '/includes/tv_program.php';
require_once dirname(__DIR__, 2) . '/includes/staff_workspaces.php';

final class TvProgramsTest extends TestCase
{
    private PDO $pdo;
    private string $storage;
    private string|false $previousStorage;

    protected function setUp(): void
    {
        $this->previousStorage = getenv('APP_PRIVATE_STORAGE_ROOT');
        $this->storage = sys_get_temp_dir() . '/evidence-tv-test-' . bin2hex(random_bytes(8));
        putenv('APP_PRIVATE_STORAGE_ROOT=' . $this->storage);
        $this->pdo = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        $this->pdo->exec('PRAGMA foreign_keys=ON');
        $this->pdo->exec('CREATE TABLE treneri(id INTEGER PRIMARY KEY)');
        $this->pdo->exec('INSERT INTO treneri VALUES(1),(2)');
        $this->pdo->exec('CREATE TABLE staff_positions(code TEXT PRIMARY KEY,label TEXT,sort_order INTEGER,active INTEGER)');
        $migration = require dirname(__DIR__, 2) . '/migrations/20261104180000_tv_programs.php';
        $migration['up']($this->pdo);
        $migration['up']($this->pdo);
        self::assertTrue($migration['verify']($this->pdo));
    }

    protected function tearDown(): void
    {
        if (is_dir($this->storage . '/tv-images')) {
            foreach (glob($this->storage . '/tv-images/*') ?: [] as $file) {
                unlink($file);
            }
            rmdir($this->storage . '/tv-images');
            rmdir($this->storage);
        }
        $this->previousStorage === false ? putenv('APP_PRIVATE_STORAGE_ROOT') : putenv('APP_PRIVATE_STORAGE_ROOT=' . $this->previousStorage);
        $_SESSION = [];
    }

    private function document(string $text = 'Vítejte v areálu'): array
    {
        return ['name' => 'Recepce', 'slides' => [[
            'id' => 'welcome', 'title' => 'Úvod', 'enabled' => true, 'duration' => 10,
            'background' => '#112233', 'fit' => 'contain', 'asset_id' => null,
            'blocks' => [[
                'text' => $text, 'x' => 120, 'y' => 80, 'width' => 1200, 'font_size' => 72,
                'bold' => true, 'align' => 'center', 'color' => '#ffffff',
                'background' => '#000000', 'background_opacity' => 0.5,
            ]],
        ]]];
    }

    private function image(): int
    {
        $source = tempnam(sys_get_temp_dir(), 'tv-image');
        $image = imagecreatetruecolor(32, 18);
        imagepng($image, $source);
        imagedestroy($image);
        try {
            return \tvUploadAsset($this->pdo, $source, 1, false)['id'];
        } finally {
            unlink($source);
        }
    }

    public function testDraftPublicationAndSubsequentEditsHaveSeparateVisibility(): void
    {
        $id = \tvCreateProgram($this->pdo, 'Recepce', 1);
        self::assertNull(\tvPublicProgram($this->pdo, $id));
        $saved = \tvMutateProgram($this->pdo, $id, 1, 1, 'save', $this->document('První'));
        self::assertNull(\tvPublicProgram($this->pdo, $id));
        $published = \tvMutateProgram($this->pdo, $id, $saved['version'], 1, 'publish', $this->document('První'));
        \tvMutateProgram($this->pdo, $id, $published['version'], 2, 'save', $this->document('Rozpracováno'));
        self::assertSame('První', \tvPublicProgram($this->pdo, $id)['document']['slides'][0]['blocks'][0]['text']);
        self::assertSame('Rozpracováno', \tvDecodeDocument(\tvProgram($this->pdo, $id)['draft_json'])['slides'][0]['blocks'][0]['text']);
        self::assertSame(4, (int)$this->pdo->query('SELECT COUNT(*) FROM tv_program_events')->fetchColumn());
    }

    public function testDisabledSlideTextsAndAssetsNeverAppearInPublication(): void
    {
        $id = \tvCreateProgram($this->pdo, 'Recepce', 1);
        $asset = $this->image();
        $document = $this->document('Veřejný text');
        $hidden = $document['slides'][0];
        $hidden['id'] = 'private';
        $hidden['enabled'] = false;
        $hidden['asset_id'] = $asset;
        $hidden['blocks'][0]['text'] = 'Tajný koncept';
        $document['slides'][] = $hidden;
        \tvMutateProgram($this->pdo, $id, 1, 1, 'publish', $document);
        self::assertCount(1, \tvPublicProgram($this->pdo, $id)['document']['slides']);
        self::assertStringNotContainsString('Tajný', \tvJson(\tvPublicProgram($this->pdo, $id)));
        self::assertNull(\tvAsset($this->pdo, $asset));
        self::assertNotNull(\tvAsset($this->pdo, $asset, true));
    }

    public function testImagesRequireACurrentPublishedReferenceIncludingSharedUse(): void
    {
        $first = \tvCreateProgram($this->pdo, 'Recepce', 1);
        $second = \tvCreateProgram($this->pdo, 'Bar', 1);
        $asset = $this->image();
        self::assertNull(\tvAsset($this->pdo, $asset));
        $doc = $this->document();
        $doc['slides'][0]['asset_id'] = $asset;
        \tvMutateProgram($this->pdo, $first, 1, 1, 'publish', $doc);
        \tvMutateProgram($this->pdo, $second, 1, 1, 'publish', $doc);
        self::assertNotNull(\tvAsset($this->pdo, $asset));
        \tvMutateProgram($this->pdo, $first, 2, 1, 'archive');
        self::assertNotNull(\tvAsset($this->pdo, $asset));
        \tvMutateProgram($this->pdo, $second, 2, 1, 'publish', $this->document());
        self::assertNull(\tvAsset($this->pdo, $asset));
    }

    public function testRestoringOldRevisionCreatesOnlyADraftAndOtherProgramsAreRejected(): void
    {
        $id = \tvCreateProgram($this->pdo, 'Recepce', 1);
        $first = \tvMutateProgram($this->pdo, $id, 1, 1, 'publish', $this->document('První'));
        \tvMutateProgram($this->pdo, $id, 2, 1, 'publish', $this->document('Druhý'));
        $restored = \tvMutateProgram($this->pdo, $id, 3, 2, 'restore_revision', null, $first['published_revision_id']);
        self::assertSame('První', $restored['document']['slides'][0]['blocks'][0]['text']);
        self::assertSame('Druhý', \tvPublicProgram($this->pdo, $id)['document']['slides'][0]['blocks'][0]['text']);
        $other = \tvCreateProgram($this->pdo, 'Bar', 1);
        $this->expectException(\InvalidArgumentException::class);
        \tvMutateProgram($this->pdo, $other, 1, 1, 'restore_revision', null, $first['published_revision_id']);
    }

    public function testArchivalStopsPlaybackAndRestorationRequiresPublication(): void
    {
        $id = \tvCreateProgram($this->pdo, 'Recepce', 1);
        \tvMutateProgram($this->pdo, $id, 1, 1, 'publish', $this->document());
        \tvMutateProgram($this->pdo, $id, 2, 1, 'archive');
        self::assertNull(\tvPublicProgram($this->pdo, $id));
        \tvMutateProgram($this->pdo, $id, 3, 1, 'unarchive');
        self::assertNull(\tvPublicProgram($this->pdo, $id));
        self::assertCount(1, \tvDecodeDocument(\tvProgram($this->pdo, $id)['draft_json'])['slides']);
    }

    public function testStaleWriterCannotOverwriteOrPublishAndDoesNotCreateAuditOrRevision(): void
    {
        $id = \tvCreateProgram($this->pdo, 'Recepce', 1);
        \tvMutateProgram($this->pdo, $id, 1, 1, 'save', $this->document('První'));
        try {
            \tvMutateProgram($this->pdo, $id, 1, 2, 'publish', $this->document('Přepsáno'));
            self::fail('Stale publication must fail.');
        } catch (\TvConflictException $e) {
            self::assertSame(0, (int)$this->pdo->query('SELECT COUNT(*) FROM tv_program_revisions')->fetchColumn());
            self::assertSame(2, (int)$this->pdo->query('SELECT COUNT(*) FROM tv_program_events')->fetchColumn());
            self::assertSame('První', \tvDecodeDocument(\tvProgram($this->pdo, $id)['draft_json'])['slides'][0]['blocks'][0]['text']);
        }
    }

    public function testMissingAssetRollsBackPublication(): void
    {
        $id = \tvCreateProgram($this->pdo, 'Recepce', 1);
        $doc = $this->document();
        $doc['slides'][0]['asset_id'] = 123;
        try {
            \tvMutateProgram($this->pdo, $id, 1, 1, 'publish', $doc);
            self::fail('Missing image must fail.');
        } catch (\InvalidArgumentException $e) {
            self::assertNull(\tvPublicProgram($this->pdo, $id));
            self::assertSame(1, (int)\tvProgram($this->pdo, $id)['lock_version']);
        }
    }

    public function testEmptyAndSingleSlideProgramsAreValidAndInvalidLayoutIsRejected(): void
    {
        self::assertSame([], \tvNormalizeDocument(['name' => 'Prázdný', 'slides' => []])['slides']);
        $doc = $this->document('</script><script>alert(1)</script>');
        self::assertSame($doc['slides'][0]['blocks'][0]['text'], \tvNormalizeDocument($doc)['slides'][0]['blocks'][0]['text']);
        $doc['slides'][0]['blocks'][0]['x'] = 1900;
        $this->expectException(\InvalidArgumentException::class);
        \tvNormalizeDocument($doc);
    }

    public function testDuplicateSlidesAndUnboundedDurationAreRejected(): void
    {
        foreach (['duplicate', 'duration', 'color', 'font', 'boolean'] as $case) {
            $doc = $this->document();
            if ($case === 'duplicate') {
                $doc['slides'][] = $doc['slides'][0];
            } elseif ($case === 'duration') {
                $doc['slides'][0]['duration'] = 0;
            } elseif ($case === 'color') {
                $doc['slides'][0]['background'] = 'url(javascript:alert(1))';
            } elseif ($case === 'font') {
                $doc['slides'][0]['blocks'][0]['font_size'] = 10000;
            } else {
                $doc['slides'][0]['enabled'] = 'false';
            }
            try {
                \tvNormalizeDocument($doc);
                self::fail('Invalid case accepted: ' . $case);
            } catch (\InvalidArgumentException $e) {
                self::assertNotSame('', $e->getMessage());
            }
        }
    }

    public function testUploadedImagesAreReencodedAndNoStoragePathIsPublic(): void
    {
        $asset = $this->image();
        $meta = \tvAsset($this->pdo, $asset, true);
        self::assertSame('image/jpeg', (new \finfo(FILEINFO_MIME_TYPE))->file(\privateStorageResolve($meta['storage_key'])));
        self::assertSame(32, (int)$meta['width_px']);
        self::assertStringStartsWith('private://tv-images/', $meta['storage_key']);
        $source = tempnam(sys_get_temp_dir(), 'bad-tv-image');
        file_put_contents($source, '<?php echo "bad";');
        try {
            $this->expectException(\SecureUploadException::class);
            \tvUploadAsset($this->pdo, $source, 1, false);
        } finally {
            unlink($source);
        }
    }

    public function testTvPositionIsSeparateFromOtherWorkspaces(): void
    {
        self::assertSame(['tv_operator'], \staffRouteAllowedPositions('tv_programs_admin.php'));
        self::assertSame('trener', \staffPositionDefinitions()['tv_operator']['legacy_role']);
        $_SESSION = ['trener_id' => 1, 'role' => 'admin', 'staff_positions' => ['tv_operator'], 'staff_active_position' => 'tv_operator'];
        self::assertSame(['tv_operator'], \staffAvailablePositions());
        self::assertNotContains('tv_operator', \staffRouteAllowedPositions('sprava_sportovcu.php'));
    }
}
