<?php

declare(strict_types=1);

namespace Horde\Timezone\Test;

use Horde\Timezone\Abbreviation;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Abbreviation::class)]
class AbbreviationTest extends TestCase
{
    public function testPercentSInsertsLetter(): void
    {
        $this->assertSame('AEST', Abbreviation::format('AE%sT', 'S', 600));
        $this->assertSame('AEDT', Abbreviation::format('AE%sT', 'D', 660));
    }

    public function testPercentSKeepsHyphenLetter(): void
    {
        $this->assertSame('UY-T', Abbreviation::format('UY%sT', '-', -180));
    }

    public function testPercentZWholeHours(): void
    {
        $this->assertSame('+10', Abbreviation::format('%z', '-', 600));
        $this->assertSame('+11', Abbreviation::format('%z', 'D', 660));
        $this->assertSame('-03', Abbreviation::format('%z', '-', -180));
        $this->assertSame('+00', Abbreviation::format('%z', '-', 0));
    }

    public function testPercentZWithMinutes(): void
    {
        $this->assertSame('+1030', Abbreviation::format('%z', '-', 630));
        $this->assertSame('-0330', Abbreviation::format('%z', '-', -210));
        $this->assertSame('+0845', Abbreviation::format('%z', '-', 525));
    }

    public function testLiteralFormatIsUnchanged(): void
    {
        $this->assertSame('AEST', Abbreviation::format('AEST', 'S', 600));
        $this->assertSame('GMT/BST', Abbreviation::format('GMT/BST', '', 60));
    }

    public function testEscapedPercent(): void
    {
        $this->assertSame('%', Abbreviation::format('%%', '', 0));
        $this->assertSame('GMT+10', Abbreviation::format('GMT%z', '', 600));
    }

    public function testLegacyOffsetHash(): void
    {
        $this->assertSame(
            '+10',
            Abbreviation::formatLegacyOffset('%z', 'S', [
                'ahead' => true,
                'hour' => '10',
                'minute' => '00',
            ])
        );
        $this->assertSame(
            '-0330',
            Abbreviation::formatLegacyOffset('%z', '', [
                'ahead' => false,
                'hour' => 3,
                'minute' => 30,
            ])
        );
        $this->assertSame(
            '+00',
            Abbreviation::formatLegacyOffset('%z', '', [
                'ahead' => false,
                'hour' => 0,
                'minute' => 0,
            ])
        );
    }
}
