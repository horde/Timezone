<?php

declare(strict_types=1);

/**
 * Copyright 2026 Horde LLC (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (LGPL). If you
 * did not receive this file, see http://www.horde.org/licenses/lgpl21.
 *
 * @author  Horde Project <horde@lists.horde.org>
 * @package Timezone
 */

/**
 * Interface for timezone data extraction backends.
 *
 * Implementations extract the plaintext Olson timezone source files from
 * various sources (tarballs, directories) and yield their contents.
 *
 * @author  Horde Project <horde@lists.horde.org>
 * @package Timezone
 */
interface Horde_Timezone_Extractor
{
    /**
     * Yields file contents from the timezone data source.
     *
     * Each yielded string is the full text content of one tzdata source file.
     *
     * @param string $source Path to a tarball or directory.
     *
     * @return iterable<string>
     * @throws Horde_Timezone_Exception
     */
    public function extractFiles(string $source): iterable;
}
