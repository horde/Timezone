<?php
namespace Horde\Timezone\Test\Unnamespaced;
use Horde\Test\TestCase;
use Horde\Timezone\Test\MockTimezone;

/**
 * @author     Jan Schneider <jan@horde.org>
 * @license    http://www.horde.org/licenses/lgpl21 LGPL 2.1
 * @category   Horde
 * @package    Timezone
 * @subpackage UnitTests
 */
class IcalendarTest extends TestCase
{
    public function testEurope()
    {
        $tz = new MockTimezone('europe_jersey');
        $this->assertStringEqualsFile(
            dirname(__DIR__) . '/fixtures/europe_jersey.ics',
            $tz->getZone('Europe/Jersey')->toVtimezone()->exportVcalendar()
        );
        $tz = new MockTimezone('europe_berlin');
        $this->assertStringEqualsFile(
            dirname(__DIR__) . '/fixtures/europe_berlin.ics',
            $tz->getZone('Europe/Berlin')->toVtimezone()->exportVcalendar()
        );
    }

    public function testAliases()
    {
        $tz = new MockTimezone('europe_berlin');
        $this->assertStringEqualsFile(
            dirname(__DIR__) . '/fixtures/europe_berlin.ics',
            $tz->getZone('Europe/Berlin')->toVtimezone()->exportVcalendar()
        );
        $this->assertStringEqualsFile(
            dirname(__DIR__) . '/fixtures/europe_berlin.ics',
            $tz->getZone('W. Europe Standard Time')->toVtimezone()->exportVcalendar()
        );
        $this->assertStringEqualsFile(
            dirname(__DIR__) . '/fixtures/europe_berlin.ics',
            $tz->getZone('W. Europe')->toVtimezone()->exportVcalendar()
        );
        $this->assertStringEqualsFile(
            dirname(__DIR__) . '/fixtures/europe_berlin.ics',
            $tz->getZone('CET')->toVtimezone()->exportVcalendar()
        );
    }

    public function testLosAngeles()
    {
        $tz = new MockTimezone('northamerica');
        $this->assertStringEqualsFile(
            dirname(__DIR__) . '/fixtures/losangeles.ics',
            $tz->getZone('America/Los_Angeles')->toVtimezone()->exportVcalendar()
        );
    }

    public function testEtc()
    {
        $tz = new MockTimezone('etcetera');
        $this->assertStringEqualsFile(
            dirname(__DIR__) . '/fixtures/etcetera.ics',
            $tz->getZone('Etc/UTC')->toVtimezone()->exportVcalendar()
        );
    }

    public function testBug14221()
    {
        $tz = new MockTimezone('budapest');
        $this->assertStringEqualsFile(
            dirname(__DIR__) . '/fixtures/budapest.ics',
            $tz->getZone('Europe/Budapest')->toVtimezone()->exportVcalendar()
        );
    }

    public function testBug14162()
    {
        $tz = new MockTimezone('uruguay');
        $this->assertStringEqualsFile(
            dirname(__DIR__) . '/fixtures/uruguay.ics',
            $tz->getZone('America/Montevideo')->toVtimezone()->exportVcalendar()
        );
    }
}
