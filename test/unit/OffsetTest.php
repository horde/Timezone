<?php

declare(strict_types=1);

namespace Horde\Timezone\Test;

use Horde\Timezone\Offset;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Offset::class)]
class OffsetTest extends TestCase
{
    public function testFromStringPositive(): void
    {
        $offset = Offset::fromString('+01:00');
        $this->assertSame(60, $offset->toMinutes());
        $this->assertTrue($offset->isAhead());
        $this->assertSame(1, $offset->getHour());
        $this->assertSame(0, $offset->getMinute());
    }

    public function testFromStringNegative(): void
    {
        $offset = Offset::fromString('-03:30');
        $this->assertSame(-210, $offset->toMinutes());
        $this->assertFalse($offset->isAhead());
        $this->assertSame(3, $offset->getHour());
        $this->assertSame(30, $offset->getMinute());
    }

    public function testFromStringNoSign(): void
    {
        $offset = Offset::fromString('5:45');
        $this->assertSame(345, $offset->toMinutes());
        $this->assertTrue($offset->isAhead());
    }

    public function testAdd(): void
    {
        $offset = Offset::fromString('-08:00');
        $new = $offset->add(60);
        $this->assertSame(-420, $new->toMinutes());
        $this->assertSame(-480, $offset->toMinutes()); // immutable
    }

    public function testToIcalPositive(): void
    {
        $offset = Offset::fromString('+05:30');
        $this->assertSame('+0530', $offset->toIcal());
    }

    public function testToIcalNegative(): void
    {
        $offset = Offset::fromString('-08:00');
        $this->assertSame('-0800', $offset->toIcal());
    }

    public function testToIcalZero(): void
    {
        $offset = new Offset(0);
        $this->assertSame('+0000', $offset->toIcal());
    }
}
