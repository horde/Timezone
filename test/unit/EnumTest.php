<?php

declare(strict_types=1);

namespace Horde\Timezone\Test;

use Horde\Timezone\Month;
use Horde\Timezone\Weekday;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use ValueError;

#[CoversClass(Month::class)]
#[CoversClass(Weekday::class)]
class EnumTest extends TestCase
{
    public function testMonthFromAbbreviation(): void
    {
        $this->assertSame(Month::Jan, Month::fromAbbreviation('Jan'));
        $this->assertSame(Month::Mar, Month::fromAbbreviation('March'));
        $this->assertSame(Month::Dec, Month::fromAbbreviation('Dec'));
    }

    public function testMonthValues(): void
    {
        $this->assertSame(1, Month::Jan->value);
        $this->assertSame(12, Month::Dec->value);
    }

    public function testMonthInvalidAbbreviation(): void
    {
        $this->expectException(ValueError::class);
        Month::fromAbbreviation('Xyz');
    }

    public function testWeekdayFromAbbreviation(): void
    {
        $this->assertSame(Weekday::Mon, Weekday::fromAbbreviation('Mon'));
        $this->assertSame(Weekday::Sun, Weekday::fromAbbreviation('Sunday'));
        $this->assertSame(Weekday::Fri, Weekday::fromAbbreviation('Fri'));
    }

    public function testWeekdayValues(): void
    {
        $this->assertSame(1, Weekday::Mon->value);
        $this->assertSame(7, Weekday::Sun->value);
    }

    public function testWeekdayInvalidAbbreviation(): void
    {
        $this->expectException(ValueError::class);
        Weekday::fromAbbreviation('Xyz');
    }
}
