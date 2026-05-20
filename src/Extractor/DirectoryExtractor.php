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

namespace Horde\Timezone\Extractor;

use DirectoryIterator;
use Horde\Timezone\Exception\TimezoneException;

/**
 * Reads timezone data from a directory of pre-extracted Olson source files.
 *
 * The directory must contain the traditional multi-file Olson timezone
 * source format (files like northamerica, europe, asia, etc. with
 * Rule/Zone/Link lines). This is the same format shipped in the IANA
 * tzdata-latest.tar.gz tarball.
 *
 * This is NOT the compiled binary .tzif format found in /usr/share/zoneinfo/.
 */
class DirectoryExtractor implements ExtractorInterface
{
    /** @var list<string> */
    private const SKIP_FILES = [
        'Makefile', 'README', 'Theory', 'SECURITY',
        'version', 'leapseconds', 'leap-seconds.list',
        'CONTRIBUTING', 'LICENSE', 'NEWS', 'calendars',
    ];

    public function extractFiles(string $source): iterable
    {
        if (!is_dir($source)) {
            throw new TimezoneException(
                sprintf('Timezone directory not found: %s', $source)
            );
        }

        $dir = new DirectoryIterator($source);
        foreach ($dir as $fileInfo) {
            if ($fileInfo->isDot() || $fileInfo->isDir()) {
                continue;
            }
            $filename = $fileInfo->getFilename();
            if ($this->shouldSkip($filename)) {
                continue;
            }
            yield file_get_contents($fileInfo->getPathname());
        }
    }

    private function shouldSkip(string $filename): bool
    {
        if (str_starts_with($filename, '.')) {
            return true;
        }
        if (str_ends_with($filename, '.awk')
            || str_ends_with($filename, '.tab')
            || str_ends_with($filename, '.zi')
            || str_ends_with($filename, '.sh')
        ) {
            return true;
        }
        return in_array($filename, self::SKIP_FILES, true);
    }
}
