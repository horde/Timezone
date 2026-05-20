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
 * Provides access to named transition rules.
 *
 * This narrow interface decouples Zone from the full TimezoneDatabase,
 * breaking the circular reference that existed in the legacy lib/ code.
 */
interface RuleProviderInterface
{
    /**
     * Returns the named rule set.
     *
     * @throws Exception\TimezoneException if rule not found
     */
    public function getRule(string $name): Rule;
}
