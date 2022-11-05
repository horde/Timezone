<?php
/**
 * @author     Jan Schneider <jan@horde.org>
 * @license    http://www.horde.org/licenses/lgpl21 LGPL 2.1
 * @category   Horde
 * @package    Timezone
 * @subpackage UnitTests
 */
namespace Horde\Timezone;
use Horde_Test_Case;
use Horde_Timezone_Mock;

class ParseTest extends Horde_Test_Case
{
    public function testBug13455()
    {
        $tz = new Horde_Timezone_Mock('europe_jersey');
        $tz->getZone('Europe/Dublin')->toVtimezone();

        $this->markTestIncomplete();
    }
}
