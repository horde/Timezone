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
 * Configuration for TimezoneDatabase.
 *
 * Replaces the untyped array $params pattern with a typed, readonly object.
 */
class TimezoneDatabaseConfig
{
    /**
     * @param string $location Path or URL to the timezone data source.
     *     Can be an FTP/HTTP URL to a tarball, a file:// path, or
     *     a directory containing pre-extracted Olson source files.
     * @param int $cacheTtl Cache lifetime in seconds (default: 7 days).
     * @param string|null $tempDir Temporary directory for downloads.
     */
    public function __construct(
        public readonly string $location = 'ftp://ftp.iana.org/tz/tzdata-latest.tar.gz',
        public readonly int $cacheTtl = 604800,
        public readonly ?string $tempDir = null,
    ) {}
}
