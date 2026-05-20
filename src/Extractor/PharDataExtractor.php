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

use PharData;
use RecursiveIteratorIterator;

/**
 * Extracts timezone data from a tarball using PHP's built-in PharData.
 *
 * This is the preferred extraction method as it requires no external
 * dependencies (phar extension is part of PHP core).
 */
class PharDataExtractor implements ExtractorInterface
{
    public function extractFiles(string $source): iterable
    {
        $phar = new PharData($source);
        foreach (new RecursiveIteratorIterator($phar) as $file) {
            if ($file->isFile()) {
                yield file_get_contents($file->getPathname());
            }
        }
    }
}
