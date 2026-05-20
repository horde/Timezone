<?php

namespace Horde\Timezone\Test\Unnamespaced;

use Horde\Timezone\Test\MockTimezone;
use Horde_Icalendar_Vtimezone;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;

/**
 * @author     Jan Schneider <jan@horde.org>
 * @license    http://www.horde.org/licenses/lgpl21 LGPL 2.1
 * @category   Horde
 * @package    Timezone
 * @subpackage UnitTests
 */
#[CoversNothing]
class ParseTest extends TestCase
{
    public function testBug13455()
    {
        $tz = new MockTimezone('europe_jersey');
        $this->assertInstanceOf(Horde_Icalendar_Vtimezone::class, $tz->getZone('Europe/Dublin')->toVtimezone());
    }
}
