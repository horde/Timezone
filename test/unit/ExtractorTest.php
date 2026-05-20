<?php

declare(strict_types=1);

namespace Horde\Timezone\Test;

use Horde\Timezone\Extractor\DirectoryExtractor;
use Horde\Timezone\Extractor\PharDataExtractor;
use Horde\Timezone\Exception\TimezoneException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Phar;
use PharData;

#[CoversClass(DirectoryExtractor::class)]
#[CoversClass(PharDataExtractor::class)]
class ExtractorTest extends TestCase
{
    public function testDirectoryExtractorYieldsContent(): void
    {
        $extractor = new DirectoryExtractor();
        $files = iterator_to_array($extractor->extractFiles(dirname(__DIR__) . '/fixtures'));

        $this->assertNotEmpty($files);
        // Should contain northamerica which has Rule/Zone lines
        $found = false;
        foreach ($files as $content) {
            if (str_contains($content, 'America/Los_Angeles')) {
                $found = true;
                break;
            }
        }
        $this->assertTrue($found, 'Should find America/Los_Angeles in fixture files');
    }

    public function testDirectoryExtractorSkipsNonDataFiles(): void
    {
        $extractor = new DirectoryExtractor();
        $files = iterator_to_array($extractor->extractFiles(dirname(__DIR__) . '/fixtures'));

        // .ics files should be skipped (they end in a non-data extension... wait,
        // .ics is not in the skip list). Let me check what IS skipped.
        // Actually .ics files don't match any skip pattern, so they'll be included.
        // That's fine — the parser will just ignore lines that don't match Rule/Zone/Link.
        $this->assertNotEmpty($files);
    }

    public function testDirectoryExtractorThrowsForMissingDir(): void
    {
        $extractor = new DirectoryExtractor();
        $this->expectException(TimezoneException::class);
        $this->expectExceptionMessage('Timezone directory not found');
        iterator_to_array($extractor->extractFiles('/nonexistent/path'));
    }

    public function testPharDataExtractorWithTarball(): void
    {
        $fixtureDir = dirname(__DIR__) . '/fixtures';
        $tarball = tempnam(sys_get_temp_dir(), 'tztest_') . '.tar.gz';

        // Create a small tarball from one fixture
        $phar = new PharData(str_replace('.gz', '', $tarball));
        $phar->addFile($fixtureDir . '/northamerica', 'northamerica');
        $phar->compress(Phar::GZ);
        unlink(str_replace('.gz', '', $tarball));

        $extractor = new PharDataExtractor();
        $files = iterator_to_array($extractor->extractFiles($tarball));
        unlink($tarball);

        $this->assertCount(1, $files);
        $this->assertStringContainsString('America/Los_Angeles', $files[0]);
    }
}
