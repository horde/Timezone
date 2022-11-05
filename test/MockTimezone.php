<?php
namespace Horde\Timezone\Test;
use Horde_Timezone;

/**
 * @author     Jan Schneider <jan@horde.org>
 * @license    http://www.horde.org/licenses/lgpl21 LGPL 2.1
 * @category   Horde
 * @package    Timezone
 * @subpackage UnitTests
 */
class MockTimezone extends Horde_Timezone
{
    protected $_zone;

    public function __construct($zone)
    {
        parent::__construct();
        $this->_zone = $zone;
    }

    protected function _download()
    {
    }

    protected function _extractAndParse()
    {
        $this->_parse(file_get_contents(__DIR__ . '/fixtures/' . $this->_zone));
    }
}
