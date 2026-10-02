<?php

declare(strict_types=1);

/**
 * Copyright 2026 The Horde Project (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (LGPL). If you
 * did not receive this file, see http://www.horde.org/licenses/lgpl21.
 *
 * @author    Torben Dannhauer <torben@dannhauer.de>
 * @category  Horde
 * @copyright 2026 The Horde Project
 * @license   http://www.horde.org/licenses/lgpl21 LGPL 2.1
 * @package   Timezone
 */

namespace Horde\Timezone;

/**
 * Expands an Olson FORMAT field into an iCalendar TZNAME value.
 *
 * The timezone database uses %s for the rule's LETTERS column and %z for the
 * UTC offset (±hh, or ±hhmm when minutes are non-zero). %% is a literal
 * percent sign. A FORMAT with neither marker is already the abbreviation.
 */
class Abbreviation
{
    /**
     * Expand an Olson FORMAT string.
     *
     * LETTERS is inserted unchanged for %s, including "-", which this
     * library has always written through (for example UY%sT with letter
     * "-" becomes UY-T).
     */
    public static function format(string $format, string $letters, int $offsetMinutes): string
    {
        if (!str_contains($format, '%')) {
            return $format;
        }

        $numeric = self::numeric($offsetMinutes);
        $result = '';
        $length = strlen($format);
        for ($i = 0; $i < $length; $i++) {
            if ($format[$i] !== '%' || $i + 1 >= $length) {
                $result .= $format[$i];
                continue;
            }
            $specifier = $format[++$i];
            switch ($specifier) {
                case 's':
                    $result .= $letters;
                    break;
                case 'z':
                    $result .= $numeric;
                    break;
                case '%':
                    $result .= '%';
                    break;
                default:
                    $result .= '%' . $specifier;
                    break;
            }
        }

        return $result;
    }

    /**
     * Expand a FORMAT string using a legacy offset hash.
     *
     * The hash is the one Horde_Timezone_Zone and Horde_Timezone_Rule use:
     * ['ahead' => bool, 'hour' => int, 'minute' => int].
     *
     * @param array{ahead?: bool, hour?: int|string, minute?: int|string} $offset
     */
    public static function formatLegacyOffset(string $format, string $letters, array $offset): string
    {
        $minutes = ((int) ($offset['hour'] ?? 0)) * 60 + (int) ($offset['minute'] ?? 0);
        if (empty($offset['ahead'])) {
            $minutes *= -1;
        }

        return self::format($format, $letters, $minutes);
    }

    /**
     * Format a UTC offset the way zic expands %z.
     *
     * Whole hours use ±hh (+10, -03, +00). A non-zero minute part uses
     * ±hhmm (+1030, -0330).
     */
    public static function numeric(int $offsetMinutes): string
    {
        $sign = $offsetMinutes < 0 ? '-' : '+';
        $minutes = abs($offsetMinutes);
        $hours = intdiv($minutes, 60);
        $mins = $minutes % 60;
        if ($mins === 0) {
            return sprintf('%s%02d', $sign, $hours);
        }

        return sprintf('%s%02d%02d', $sign, $hours, $mins);
    }
}
