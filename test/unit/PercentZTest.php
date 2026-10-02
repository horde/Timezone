<?php

declare(strict_types=1);

namespace Horde\Timezone\Test;

use DateTimeImmutable;
use Horde\Timezone\Offset;
use Horde\Timezone\Rule;
use Horde\Timezone\RuleProviderInterface;
use Horde\Timezone\Zone;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Rule::class)]
#[CoversClass(Zone::class)]
class PercentZTest extends TestCase
{
    public function testLegacyRulesetExport(): void
    {
        $tz = new MockTimezone('percent_z');
        $export = $tz->getZone('Test/PercentZ')->toVtimezone()->exportVcalendar();

        $this->assertStringContainsString('TZNAME:+11', $export);
        $this->assertStringContainsString('TZNAME:+10', $export);
        $this->assertStringNotContainsString('TZNAME:%z', $export);
    }

    public function testLegacyFixedOffsetExport(): void
    {
        $tz = new MockTimezone('percent_z');

        $fixed = $tz->getZone('Test/FixedZ')->toVtimezone()->exportVcalendar();
        $this->assertStringContainsString('TZNAME:+03', $fixed);
        $this->assertStringNotContainsString('TZNAME:%z', $fixed);

        $negative = $tz->getZone('Test/NegZ')->toVtimezone()->exportVcalendar();
        $this->assertStringContainsString('TZNAME:-0330', $negative);
    }

    public function testModernRulesetExport(): void
    {
        $provider = new class implements RuleProviderInterface {
            public function getRule(string $name): Rule
            {
                $rule = new Rule($name);
                $rule->addEntry(['Rule', $name, '2010', 'max', '-', 'Apr', '1', '2:00', '1:00', 'D']);
                $rule->addEntry(['Rule', $name, '2010', 'max', '-', 'Oct', '1', '2:00', '0', 'S']);

                return $rule;
            }
        };

        $zone = new Zone('Test/PercentZ', $provider);
        $zone->addTransition(['10:00', '-', 'LMT', '2000', 'Jan', '1']);
        $zone->addTransition(['10:00', 'TestPctZ', '%z']);

        $export = $zone->toVtimezone(new DateTimeImmutable('2000-01-01'));

        $this->assertStringContainsString('TZNAME:+11', $export);
        $this->assertStringContainsString('TZNAME:+10', $export);
        $this->assertStringNotContainsString('TZNAME:%z', $export);
    }

    public function testModernFixedOffsetExport(): void
    {
        $provider = new class implements RuleProviderInterface {
            public function getRule(string $name): Rule
            {
                return new Rule($name);
            }
        };

        $zone = new Zone('Test/FixedZ', $provider);
        $zone->addTransition(['0:00', '-', 'LMT', '2000', 'Jan', '1']);
        $zone->addTransition(['-3:30', '-', '%z']);

        $export = $zone->toVtimezone();

        $this->assertStringContainsString('TZNAME:-0330', $export);
        $this->assertStringContainsString('TZOFFSETTO:-0330', $export);
        $this->assertStringNotContainsString('%z', $export);
    }

    public function testModernRuleComponentUsesOffsetTo(): void
    {
        $rule = new Rule('TestPctZ');
        $rule->addEntry(['Rule', 'TestPctZ', '2010', 'max', '-', 'Apr', '1', '2:00', '1:00', 'D']);

        $components = $rule->toVtimezoneComponents(
            '%z',
            Offset::fromString('10:00'),
            new DateTimeImmutable('2000-01-01')
        );

        $this->assertCount(1, $components);
        $this->assertStringContainsString('TZNAME:+11', $components[0]);
        $this->assertStringContainsString('TZOFFSETTO:+1100', $components[0]);
    }
}
