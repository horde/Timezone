<?php

declare(strict_types=1);

/**
 * Copyright 2026 Horde LLC (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (LGPL). If you
 * did not receive this file, see http://www.horde.org/licenses/lgpl21.
 *
 * @package Timezone
 */

namespace Horde\Timezone\Parser;

use Horde\Timezone\Rule;
use Horde\Timezone\Zone;

/**
 * Parses Olson timezone database source files.
 *
 * Handles the traditional multi-file text format with Rule, Zone, and Link
 * entries. Zone continuation lines (starting with whitespace) are associated
 * with the preceding Zone entry.
 */
class OlsonParser
{
    /**
     * Parse the text content of one tzdata source file into the result.
     */
    public function parse(string $content, ParseResult $result): void
    {
        $lines = explode("\n", $content);
        $currentZone = null;

        foreach ($lines as $line) {
            $line = rtrim($line);

            if ($line === '' || $line[0] === '#') {
                continue;
            }

            // Strip inline comments
            $line = preg_replace('/\s*#.*$/', '', $line);
            $line = rtrim($line);
            if ($line === '') {
                continue;
            }

            $columns = preg_split('/\s+/', $line);

            // Remove leading empty element from continuation lines (start with whitespace)
            if ($columns[0] === '') {
                array_shift($columns);
            }

            switch ($columns[0]) {
                case 'Rule':
                    $this->parseRule($columns, $result);
                    $currentZone = null;
                    break;

                case 'Link':
                    $result->addLink($columns[2], $columns[1]);
                    $currentZone = null;
                    break;

                case 'Zone':
                    $currentZone = $columns[1];
                    if (!$result->hasZone($currentZone)) {
                        $result->addZone($currentZone, new Zone($currentZone, $result));
                    }
                    $zoneColumns = array_slice($columns, 2);
                    $result->getZone($currentZone)->addTransition($zoneColumns);
                    break;

                default:
                    // Continuation line for previous Zone
                    if ($currentZone !== null && $result->hasZone($currentZone)) {
                        $result->getZone($currentZone)->addTransition($columns);
                    }
                    break;
            }
        }
    }

    private function parseRule(array $columns, ParseResult $result): void
    {
        $name = $columns[1];
        if (!$result->hasRule($name)) {
            $result->addRule($name, new Rule($name));
        }
        $result->getRule($name)->addEntry($columns);
    }
}
