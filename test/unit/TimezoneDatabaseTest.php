<?php

declare(strict_types=1);

namespace Horde\Timezone\Test;

use Horde\Timezone\Exception\ZoneNotFoundException;
use Horde\Timezone\Parser\OlsonParser;
use Horde\Timezone\Parser\ParseResult;
use Horde\Timezone\TimezoneDatabase;
use Horde\Timezone\TimezoneDatabaseConfig;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(TimezoneDatabase::class)]
#[CoversClass(TimezoneDatabaseConfig::class)]
#[CoversClass(OlsonParser::class)]
#[CoversClass(ParseResult::class)]
class TimezoneDatabaseTest extends TestCase
{
    private TimezoneDatabase $db;

    protected function setUp(): void
    {
        $this->db = new TimezoneDatabase(new TimezoneDatabaseConfig(
            location: dirname(__DIR__) . '/fixtures'
        ));
    }

    public function testGetZone(): void
    {
        $zone = $this->db->getZone('America/Los_Angeles');
        $this->assertSame('America/Los_Angeles', $zone->getName());
    }

    public function testGetZoneNotFound(): void
    {
        $this->expectException(ZoneNotFoundException::class);
        $this->db->getZone('Nowhere/Nonexistent');
    }

    public function testGetZoneProducesVtimezone(): void
    {
        $zone = $this->db->getZone('America/Los_Angeles');
        $vtimezone = $zone->toVtimezone();

        $this->assertStringStartsWith("BEGIN:VTIMEZONE\r\n", $vtimezone);
        $this->assertStringEndsWith("END:VTIMEZONE\r\n", $vtimezone);
        $this->assertStringContainsString('TZID:America/Los_Angeles', $vtimezone);
    }

    public function testEuropeBerlin(): void
    {
        $zone = $this->db->getZone('Europe/Berlin');
        $vtimezone = $zone->toVtimezone();

        $this->assertStringContainsString('TZID:Europe/Berlin', $vtimezone);
        $this->assertStringContainsString('BEGIN:DAYLIGHT', $vtimezone);
        $this->assertStringContainsString('BEGIN:STANDARD', $vtimezone);
    }

    public function testEtcUtc(): void
    {
        $zone = $this->db->getZone('Etc/UTC');
        $vtimezone = $zone->toVtimezone();

        $this->assertStringContainsString('TZID:Etc/UTC', $vtimezone);
    }

    public function testGetRule(): void
    {
        $rule = $this->db->getRule('US');
        $this->assertSame('US', $rule->getName());
        $this->assertNotEmpty($rule->getEntries());
    }

    public function testParserHandlesLinks(): void
    {
        $parser = new OlsonParser();
        $result = new ParseResult();
        $parser->parse("Link America/Los_Angeles US/Pacific\n", $result);

        $this->assertSame('America/Los_Angeles', $result->resolveLink('US/Pacific'));
    }

    public function testParserHandlesRules(): void
    {
        $parser = new OlsonParser();
        $result = new ParseResult();
        $content = "Rule\tUS\t2007\tmax\t-\tMar\tSun>=8\t2:00\t1:00\tD\n";
        $parser->parse($content, $result);

        $this->assertTrue($result->hasRule('US'));
        $rule = $result->getRule('US');
        $this->assertCount(1, $rule->getEntries());
    }

    public function testConfigDefaults(): void
    {
        $config = new TimezoneDatabaseConfig();
        $this->assertSame('ftp://ftp.iana.org/tz/tzdata-latest.tar.gz', $config->location);
        $this->assertSame(604800, $config->cacheTtl);
        $this->assertNull($config->tempDir);
    }
}
