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
 * Represents a single timezone (Zone entry) from the Olson database.
 *
 * A zone consists of a series of transitions, each defining the UTC offset,
 * the DST rule in effect, and the abbreviation format for that period.
 *
 * Zone columns: 0: gmtoff, 1: rules ("-" = none, or a rule name, or a fixed offset),
 * 2: format (abbreviation), 3+: UNTIL (year month day time)
 */
class Zone
{
    private string $name;
    private string $tzid;
    private RuleProviderInterface $ruleProvider;

    /** @var list<array<int, string>> Transition lines */
    private array $transitions = [];

    public function __construct(string $name, RuleProviderInterface $ruleProvider)
    {
        $this->name = $name;
        $this->tzid = $name;
        $this->ruleProvider = $ruleProvider;
    }

    public function getName(): string
    {
        return $this->name;
    }

    /**
     * Set the timezone ID used in output (may differ from canonical name due to aliases).
     */
    public function setTzid(string $tzid): void
    {
        $this->tzid = $tzid;
    }

    /**
     * Add a transition line (parsed zone columns without the "Zone" keyword and name).
     *
     * @param array<int, string> $columns
     */
    public function addTransition(array $columns): void
    {
        $this->transitions[] = $columns;
    }

    /**
     * Export this zone as a VTIMEZONE iCalendar component string.
     *
     * @param DateTimeImmutable|null $from Earliest date to include rules for.
     * @param DateTimeImmutable|null $to Latest date.
     *
     * @return string A complete VTIMEZONE component.
     * @throws TimezoneException
     */
    public function toVtimezone(?DateTimeImmutable $from = null, ?DateTimeImmutable $to = null): string
    {
        if (empty($this->transitions)) {
            throw new TimezoneException('No transitions found for timezone ' . $this->name);
        }

        $lines = [];
        $lines[] = 'BEGIN:VTIMEZONE';
        $lines[] = 'TZID:' . $this->tzid;

        if (count($this->transitions[0]) <= 3) {
            // Zone has no UNTIL date — minimal VTIMEZONE
            $lines[] = 'END:VTIMEZONE';
            return implode("\r\n", $lines) . "\r\n";
        }

        $startDate = $this->getTransitionDate(0);
        $startOffset = $this->getTransitionOffset(0);

        for ($i = 1, $c = count($this->transitions); $i < $c; $i++) {
            $transition = $this->transitions[$i];

            if ($to !== null && $startDate > $to) {
                $startDate = $this->getTransitionDate($i);
                $startOffset = $this->getTransitionOffset($i);
                continue;
            }

            $endDate = count($transition) > 3 ? $this->getTransitionDate($i) : null;
            if ($from !== null && $endDate !== null && $endDate < $from) {
                $startDate = $endDate;
                $startOffset = $this->getTransitionOffset($i);
                continue;
            }

            $name = $transition[2];

            if ($transition[1] === '-') {
                // Standard time, no DST rule
                $newOffset = $this->getTransitionOffset($i);
                $lines[] = 'BEGIN:STANDARD';
                $lines[] = 'DTSTART:' . $startDate->format('Ymd\THis');
                $lines[] = 'TZOFFSETFROM:' . $startOffset->toIcal();
                $lines[] = 'TZOFFSETTO:' . $newOffset->toIcal();
                $lines[] = 'TZNAME:' . $name;
                $lines[] = 'END:STANDARD';
                $startOffset = $newOffset;
            } elseif (preg_match('/\d+(:(\d+))?/', $transition[1])) {
                // Individual fixed offset (daylight)
                $newOffset = $this->getTransitionOffset($i);
                $lines[] = 'BEGIN:DAYLIGHT';
                $lines[] = 'DTSTART:' . $startDate->format('Ymd\THis');
                $lines[] = 'TZOFFSETFROM:' . $startOffset->toIcal();
                $lines[] = 'TZOFFSETTO:' . $newOffset->toIcal();
                $lines[] = 'TZNAME:' . $name;
                $lines[] = 'END:DAYLIGHT';
                $startOffset = $newOffset;
            } else {
                // Represented by a ruleset
                $baseOffset = $this->getTransitionOffset($i);
                $rule = $this->ruleProvider->getRule($transition[1]);
                $ruleFrom = ($from !== null && $from > $startDate) ? $from : $startDate;
                $ruleTo = ($to !== null && $endDate !== null && $to < $endDate) ? $to : $endDate;

                $components = $rule->toVtimezoneComponents(
                    $name,
                    $baseOffset,
                    $ruleFrom,
                    $ruleTo,
                );
                foreach ($components as $component) {
                    $lines[] = $component;
                }
                $startOffset = $baseOffset;
            }

            $startDate = $endDate;
        }

        $lines[] = 'END:VTIMEZONE';
        return implode("\r\n", $lines) . "\r\n";
    }

    /**
     * Parse the UNTIL date from a zone transition line.
     */
    private function getTransitionDate(int $line): DateTimeImmutable
    {
        $date = array_slice($this->transitions[$line], 3);
        $year = (int) $date[0];
        $month = isset($date[1]) ? Month::fromAbbreviation($date[1])->value : 1;
        $day = isset($date[2]) ? (int) $date[2] : 1;

        $hour = 0;
        $minute = 0;
        if (isset($date[3]) && $date[3] !== '-') {
            preg_match('/(\d+)(?::(\d+))?(?::(\d+))?/', $date[3], $match);
            $hour = (int) $match[1];
            $minute = (int) ($match[2] ?? 0);
        }

        return new DateTimeImmutable(
            sprintf('%04d-%02d-%02dT%02d:%02d:00', $year, $month, $day, $hour, $minute)
        );
    }

    /**
     * Parse the UTC offset from a zone transition line.
     */
    private function getTransitionOffset(int $line): Offset
    {
        return Offset::fromString($this->transitions[$line][0]);
    }
}
