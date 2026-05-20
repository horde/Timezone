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
 * Backed enum for month abbreviations used in the Olson timezone database.
 */
enum Month: int
{
    case Jan = 1;
    case Feb = 2;
    case Mar = 3;
    case Apr = 4;
    case May = 5;
    case Jun = 6;
    case Jul = 7;
    case Aug = 8;
    case Sep = 9;
    case Oct = 10;
    case Nov = 11;
    case Dec = 12;

    /**
     * Create from a month abbreviation (first 3 chars used).
     */
    public static function fromAbbreviation(string $abbr): self
    {
        $key = substr($abbr, 0, 3);
        foreach (self::cases() as $case) {
            if ($case->name === $key) {
                return $case;
            }
        }
        throw new ValueError(sprintf('Invalid month abbreviation: %s', $abbr));
    }
}
