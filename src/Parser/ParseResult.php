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
use Horde\Timezone\RuleProviderInterface;
use Horde\Timezone\Zone;

/**
 * Result container for OlsonParser.
 *
 * Holds parsed zones, rules, and links from the Olson timezone database.
 */
class ParseResult implements RuleProviderInterface
{
    /** @var array<string, Zone> */
    private array $zones = [];

    /** @var array<string, Rule> */
    private array $rules = [];

    /** @var array<string, string> Alias => canonical zone name */
    private array $links = [];

    public function addZone(string $name, Zone $zone): void
    {
        $this->zones[$name] = $zone;
    }

    public function addRule(string $name, Rule $rule): void
    {
        $this->rules[$name] = $rule;
    }

    public function addLink(string $alias, string $target): void
    {
        $this->links[$alias] = $target;
    }

    public function hasZone(string $name): bool
    {
        return isset($this->zones[$name]);
    }

    public function getZone(string $name): ?Zone
    {
        return $this->zones[$name] ?? null;
    }

    public function getRule(string $name): Rule
    {
        if (!isset($this->rules[$name])) {
            throw new \Horde\Timezone\Exception\TimezoneException(
                sprintf('Timezone rule %s not found', $name)
            );
        }
        return $this->rules[$name];
    }

    public function hasRule(string $name): bool
    {
        return isset($this->rules[$name]);
    }

    /**
     * Resolves a link (alias) to its canonical zone name.
     */
    public function resolveLink(string $name): string
    {
        return $this->links[$name] ?? $name;
    }

    /** @return array<string, Zone> */
    public function getZones(): array
    {
        return $this->zones;
    }

    /** @return array<string, Rule> */
    public function getRules(): array
    {
        return $this->rules;
    }

    /** @return array<string, string> */
    public function getLinks(): array
    {
        return $this->links;
    }
}
