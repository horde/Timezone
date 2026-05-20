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

namespace Horde\Timezone;

use ValueError;

/**
 * Backed enum for weekday abbreviations used in the Olson timezone database.
 *
 * Values correspond to ISO-8601 weekday numbering (1=Monday, 7=Sunday).
 */
enum Weekday: int
{
    case Mon = 1;
    case Tue = 2;
    case Wed = 3;
    case Thu = 4;
    case Fri = 5;
    case Sat = 6;
    case Sun = 7;

    /**
     * Create from a weekday abbreviation (first 3 chars used).
     */
    public static function fromAbbreviation(string $abbr): self
    {
        $key = substr($abbr, 0, 3);
        foreach (self::cases() as $case) {
            if ($case->name === $key) {
                return $case;
            }
        }
        throw new ValueError(sprintf('Invalid weekday abbreviation: %s', $abbr));
    }
}
