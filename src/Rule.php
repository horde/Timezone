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

use DateTimeImmutable;
use Horde\Timezone\Exception\TimezoneException;

/**
 * Represents a named set of DST transition rules from the Olson database.
 *
 * Each rule entry defines when DST starts or ends for a given range of years.
 * The rule columns are:
 *   0: "Rule", 1: name, 2: from-year, 3: to-year, 4: type (unused),
 *   5: month, 6: day-spec, 7: time, 8: save-offset, 9: letter/abbreviation
 */
class Rule
{
    private string $name;

    /** @var list<array<int, string>> */
    private array $entries = [];

    public function __construct(string $name)
    {
        $this->name = $name;
    }

    public function getName(): string
    {
        return $this->name;
    }

    /**
     * Add a parsed Rule line.
     *
     * @param array<int, string> $columns The split columns from a Rule line.
     */
    public function addEntry(array $columns): void
    {
        $this->entries[] = $columns;
    }

    /** @return list<array<int, string>> */
    public function getEntries(): array
    {
        return $this->entries;
    }

    /**
     * Generate VTIMEZONE sub-components (STANDARD/DAYLIGHT) for this ruleset.
     *
     * @param string $tzName The timezone name abbreviation pattern (may contain %s).
     * @param Offset $baseOffset The base UTC offset for the zone.
     * @param DateTimeImmutable $from Start of period to generate rules for.
     * @param DateTimeImmutable|null $to End of period.
     *
     * @return list<string> iCalendar component blocks (BEGIN:STANDARD...END:STANDARD etc.)
     */
    public function toVtimezoneComponents(
        string $tzName,
        Offset $baseOffset,
        DateTimeImmutable $from,
        ?DateTimeImmutable $to = null,
    ): array {
        $components = [];
        $startYear = (int) $from->format('Y');
        $endYear = $to !== null ? (int) $to->format('Y') : null;

        foreach ($this->entries as $ruleNo => $rule) {
            $toYear = $rule[3];
            if ($toYear[0] === 'o') {
                // "only" means same as from-year
                $toYear = $rule[2];
            }

            if ($toYear[0] !== 'm' && (int) $toYear < $startYear) {
                continue;
            }
            if ($endYear !== null && $rule[2][0] !== 'm' && (int) $rule[2] > $endYear) {
                break;
            }

            $effectiveFrom = $rule[2];
            if ($effectiveFrom[0] !== 'm' && (int) $effectiveFrom < $startYear) {
                $effectiveFrom = (string) $startYear;
            }

            $month = Month::fromAbbreviation($rule[5]);
            $time = $this->parseTime($rule[7]);
            $modifier = $time['modifier'];

            $firstDate = $this->getFirstMatch($rule, (int) $effectiveFrom);
            $firstDate = $firstDate->setTime($time['hour'], $time['minute']);

            $previousOffset = $this->findPreviousOffset($firstDate, $ruleNo, $baseOffset);

            if ($rule[8] === '0' || $rule[8] === '-') {
                $type = 'STANDARD';
                $offsetTo = $baseOffset;
            } else {
                $type = 'DAYLIGHT';
                $offsetTo = $baseOffset->add($this->parseOffsetString($rule[8]));
            }

            // Adjust DTSTART based on time modifier
            $firstDate = $this->adjustForModifier($firstDate, $modifier, $previousOffset, $baseOffset);

            $lines = [];
            $lines[] = 'BEGIN:' . $type;
            $lines[] = 'TZOFFSETFROM:' . $previousOffset->toIcal();
            $lines[] = 'TZOFFSETTO:' . $offsetTo->toIcal();
            $lines[] = 'DTSTART:' . $firstDate->format('Ymd\THis');

            // RRULE if recurring
            if ($effectiveFrom !== $toYear) {
                $rrule = $this->buildRrule($rule, $month, $toYear, $modifier, $baseOffset, $previousOffset, $time);
                if ($rrule !== null) {
                    $lines[] = 'RRULE:' . $rrule;
                }
            }

            $lines[] = 'TZNAME:' . sprintf($tzName, $rule[9]);
            $lines[] = 'END:' . $type;
            $components[] = implode("\r\n", $lines);
        }

        return $components;
    }

    /**
     * Parse a time specification from a rule (e.g. "2:00", "2:00s", "1:00u").
     *
     * @return array{hour: int, minute: int, modifier: string}
     */
    private function parseTime(string $time): array
    {
        preg_match('/(\d+)(?::(\d+))?(?::(\d+))?([wsguz])?/', $time, $match);
        $modifier = 'w';
        if (isset($match[4])) {
            $modifier = ($match[4] === 'g' || $match[4] === 'z') ? 'u' : $match[4];
        }
        return [
            'hour' => (int) $match[1],
            'minute' => (int) ($match[2] ?? 0),
            'modifier' => $modifier,
        ];
    }

    /**
     * Parse an offset string like "1:00" or "-0:30" into minutes.
     */
    private function parseOffsetString(string $offset): int
    {
        preg_match('/(-)?(\d+):(\d+)/', $offset, $match);
        $minutes = (int) $match[2] * 60 + (int) $match[3];
        return ($match[1] === '-') ? -$minutes : $minutes;
    }

    /**
     * Find the first date matching a rule's day specification for a given year.
     */
    private function getFirstMatch(array $rule, int $year): DateTimeImmutable
    {
        $month = Month::fromAbbreviation($rule[5])->value;
        $daySpec = $rule[6];

        if (preg_match('/^\d+$/', $daySpec)) {
            return new DateTimeImmutable(sprintf('%04d-%02d-%02d', $year, $month, (int) $daySpec));
        }

        if (str_starts_with($daySpec, 'last')) {
            $weekday = Weekday::fromAbbreviation(substr($daySpec, 4));
            $lastDay = (int) date('t', mktime(0, 0, 0, $month, 1, $year));
            $date = new DateTimeImmutable(sprintf('%04d-%02d-%02d', $year, $month, $lastDay));
            while ((int) $date->format('N') !== $weekday->value) {
                $date = $date->modify('-1 day');
            }
            return $date;
        }

        if (str_contains($daySpec, '>=')) {
            [$weekdayStr, $day] = explode('>=', $daySpec);
            $weekday = Weekday::fromAbbreviation($weekdayStr);
            $date = new DateTimeImmutable(sprintf('%04d-%02d-%02d', $year, $month, (int) $day));
            while ((int) $date->format('N') !== $weekday->value) {
                $date = $date->modify('+1 day');
            }
            return $date;
        }

        if (str_contains($daySpec, '<=')) {
            [$weekdayStr, $day] = explode('<=', $daySpec);
            $weekday = Weekday::fromAbbreviation($weekdayStr);
            $date = new DateTimeImmutable(sprintf('%04d-%02d-%02d', $year, $month, (int) $day));
            while ((int) $date->format('N') !== $weekday->value) {
                $date = $date->modify('-1 day');
            }
            return $date;
        }

        throw new TimezoneException('Cannot parse rule day specification: ' . $daySpec);
    }

    /**
     * Find the UTC offset that was in effect just before the given rule.
     */
    private function findPreviousOffset(
        DateTimeImmutable $date,
        int $ruleNo,
        Offset $baseOffset,
    ): Offset {
        if ($ruleNo === 0) {
            return $baseOffset;
        }

        $closest = null;
        $closestOffset = $baseOffset;
        $dateYear = (int) $date->format('Y');

        for ($i = $ruleNo - 1; $i >= 0; $i--) {
            $entry = $this->entries[$i];
            $end = $entry[3][0] === 'o' ? $entry[2] : $entry[3];

            $checkYear = $dateYear;
            if ($end[0] !== 'm') {
                $checkYear = min($dateYear, (int) $end);
            }

            $ruleDate = $this->getFirstMatch($entry, $checkYear);
            if ($ruleDate > $date) {
                $ruleDate = $this->getFirstMatch($entry, $checkYear - 1);
            }

            $diff = (int) $date->diff($ruleDate)->days;
            if ($closest === null || $diff < $closest) {
                $closest = $diff;
                $closestOffset = ($entry[8] !== '0' && $entry[8] !== '-')
                    ? $baseOffset->add($this->parseOffsetString($entry[8]))
                    : $baseOffset;
            }
        }

        return $closestOffset;
    }

    private function adjustForModifier(
        DateTimeImmutable $date,
        string $modifier,
        Offset $previousOffset,
        Offset $baseOffset,
    ): DateTimeImmutable {
        switch ($modifier) {
            case 's':
                // Standard time: adjust from standard to wall
                $adjust = $previousOffset->toMinutes() - $baseOffset->toMinutes();
                if ($adjust !== 0) {
                    $date = $date->modify(sprintf('%+d minutes', $adjust));
                }
                break;
            case 'u':
                // UTC: adjust from UTC to wall
                $adjust = $previousOffset->toMinutes();
                if ($adjust !== 0) {
                    $date = $date->modify(sprintf('%+d minutes', $adjust));
                }
                break;
        }
        return $date;
    }

    private function buildRrule(
        array $rule,
        Month $month,
        string $toYear,
        string $modifier,
        Offset $baseOffset,
        Offset $previousOffset,
        array $time,
    ): ?string {
        $daySpec = $rule[6];
        $until = '';

        if ($toYear[0] !== 'm') {
            $last = $this->getFirstMatch($rule, (int) $toYear);
            $last = $last->setTime($time['hour'], $time['minute']);
            // Convert UNTIL to UTC
            switch ($modifier) {
                case 's':
                    $adjust = -$baseOffset->toMinutes();
                    if ($adjust !== 0) {
                        $last = $last->modify(sprintf('%+d minutes', $adjust));
                    }
                    break;
                case 'w':
                    $adjust = -$previousOffset->toMinutes();
                    if ($adjust !== 0) {
                        $last = $last->modify(sprintf('%+d minutes', $adjust));
                    }
                    break;
            }
            $until = ';UNTIL=' . $last->format('Ymd\THis') . 'Z';
        }

        if (preg_match('/^\d+$/', $daySpec)) {
            return 'FREQ=YEARLY;BYMONTH=' . $month->value . ';BYMONTHDAY=' . $daySpec . $until;
        }

        if (str_starts_with($daySpec, 'last')) {
            $wd = strtoupper(substr($daySpec, 4, 2));
            return 'FREQ=YEARLY;BYDAY=-1' . $wd . ';BYMONTH=' . $month->value . $until;
        }

        if (str_contains($daySpec, '>=')) {
            [$weekday, $day] = explode('>=', $daySpec);
            $wd = strtoupper(substr($weekday, 0, 2));
            $days = [];
            $fromYear = $rule[2][0] === 'm' ? (int) $rule[2] : (int) $rule[2];
            $lastDay = min(
                (int) date('t', mktime(0, 0, 0, $month->value, 1, $fromYear)),
                (int) $day + 6
            );
            for ($i = (int) $day; (int) $day > 1 && $i <= $lastDay; $i++) {
                $days[] = $i;
            }
            return 'FREQ=YEARLY;BYMONTH=' . $month->value
                . ($days ? ';BYMONTHDAY=' . implode(',', $days) : '')
                . ';BYDAY=1' . $wd . $until;
        }

        if (str_contains($daySpec, '<=')) {
            [$weekday, $day] = explode('<=', $daySpec);
            $wd = strtoupper(substr($weekday, 0, 2));
            $days = [];
            for ($i = 1; $i <= (int) $day; $i++) {
                $days[] = $i;
            }
            return 'FREQ=YEARLY;BYMONTH=' . $month->value
                . ';BYMONTHDAY=' . implode(',', $days)
                . ';BYDAY=-1' . $wd . $until;
        }

        return null;
    }
}
