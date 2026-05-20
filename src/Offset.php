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

/**
 * Immutable value object representing a UTC offset.
 *
 * Replaces the untyped array hash used in the legacy lib/ code:
 *   ['ahead' => bool, 'hour' => int, 'minute' => int]
 */
class Offset
{
    private readonly int $totalMinutes;

    /**
     * @param int $totalMinutes Signed offset in minutes from UTC.
     *     Positive = ahead of UTC (east), negative = behind UTC (west).
     */
    public function __construct(int $totalMinutes)
    {
        $this->totalMinutes = $totalMinutes;
    }

    /**
     * Parse an offset string like "-03:30" or "+01:00" or "1:00".
     */
    public static function fromString(string $offset): self
    {
        preg_match('/(-)?(\d+):(\d+)/', $offset, $match);
        $minutes = (int) $match[2] * 60 + (int) $match[3];
        if ($match[1] === '-') {
            $minutes = -$minutes;
        }
        return new self($minutes);
    }

    public function toMinutes(): int
    {
        return $this->totalMinutes;
    }

    public function isAhead(): bool
    {
        return $this->totalMinutes >= 0;
    }

    public function getHour(): int
    {
        return intdiv(abs($this->totalMinutes), 60);
    }

    public function getMinute(): int
    {
        return abs($this->totalMinutes) % 60;
    }

    /**
     * Add minutes to this offset, returning a new Offset.
     */
    public function add(int $minutes): self
    {
        return new self($this->totalMinutes + $minutes);
    }

    /**
     * Format as iCalendar UTCOFFSET value (e.g. "+0100", "-0330").
     */
    public function toIcal(): string
    {
        $sign = $this->totalMinutes >= 0 ? '+' : '-';
        return sprintf('%s%02d%02d', $sign, $this->getHour(), $this->getMinute());
    }
}
