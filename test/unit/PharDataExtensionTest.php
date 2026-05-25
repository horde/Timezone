<?php

declare(strict_types=1);

namespace Horde\Timezone\Test;

use Horde_Timezone;
use Phar;
use PharData;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * Tests that PharData extraction works correctly when temp files
 * are renamed to include an archive extension.
 *
 * Regression test for PHP 8.3+ which rejects extensionless filenames.
 */
#[CoversClass(Horde_Timezone::class)]
class PharDataExtensionTest extends TestCase
{
    private string $tarball = '';

    protected function setUp(): void
    {
        if (!class_exists(PharData::class)) {
            $this->markTestSkipped('PharData extension not available');
        }

        $fixtureDir = dirname(__DIR__) . '/fixtures';
        $this->tarball = tempnam(sys_get_temp_dir(), 'tzfix_') . '.tar.gz';

        $tar = str_replace('.gz', '', $this->tarball);
        $phar = new PharData($tar);
        $phar->addFile($fixtureDir . '/northamerica', 'northamerica');
        $phar->compress(Phar::GZ);
        unlink($tar);
    }

    protected function tearDown(): void
    {
        if ($this->tarball !== '' && file_exists($this->tarball)) {
            unlink($this->tarball);
        }
    }

    public function testPharDataRejectsExtensionlessFile(): void
    {
        $noExt = tempnam(sys_get_temp_dir(), 'vfs');
        copy($this->tarball, $noExt);

        $this->expectException(\UnexpectedValueException::class);
        try {
            new PharData($noExt);
        } finally {
            unlink($noExt);
        }
    }

    public function testLocalTarballWorksViaPharData(): void
    {
        $tz = new Horde_Timezone([
            'location' => 'file://' . $this->tarball,
        ]);

        $zone = $tz->getZone('America/Los_Angeles');
        $this->assertNotNull($zone);
    }

    public function testGetArchiveExtensionExtractsTarGz(): void
    {
        $tz = new TestableTimezone();
        $this->assertSame('.tar.gz', $tz->publicGetArchiveExtension('/tz/tzdata-latest.tar.gz'));
    }

    public function testGetArchiveExtensionExtractsTar(): void
    {
        $tz = new TestableTimezone();
        $this->assertSame('.tar', $tz->publicGetArchiveExtension('/tz/data.tar'));
    }

    public function testGetArchiveExtensionExtractsTgz(): void
    {
        $tz = new TestableTimezone();
        $this->assertSame('.tgz', $tz->publicGetArchiveExtension('/path/file.tgz'));
    }

    public function testGetArchiveExtensionReturnsEmptyForUnknown(): void
    {
        $tz = new TestableTimezone();
        $this->assertSame('', $tz->publicGetArchiveExtension('/path/noextension'));
    }

    public function testExtractorGuardSkipsPharForExtensionlessFile(): void
    {
        $tz = new TestableTimezone();
        $this->assertFalse($tz->publicHasPharCompatibleExtension('/tmp/vfs49ujpqssl3so'));
        $this->assertTrue($tz->publicHasPharCompatibleExtension('/tmp/tzdata.tar.gz'));
    }
}

class TestableTimezone extends Horde_Timezone
{
    public function __construct()
    {
        parent::__construct();
    }

    public function publicGetArchiveExtension(string $path): string
    {
        return $this->_getArchiveExtension($path);
    }

    public function publicHasPharCompatibleExtension(string $path): bool
    {
        return $this->_hasPharCompatibleExtension($path);
    }
}
