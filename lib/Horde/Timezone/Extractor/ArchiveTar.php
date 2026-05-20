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
 * Extracts timezone data from a tarball using PEAR's Archive_Tar.
 *
 * This is a fallback for environments where the phar extension is disabled.
 * Performs constructor introspection to detect incompatible Archive_Tar
 * versions and provides actionable error messages.
 *
 * @author  Horde Project <horde@lists.horde.org>
 * @package Timezone
 */
class Horde_Timezone_Extractor_ArchiveTar implements Horde_Timezone_Extractor
{
    public function extractFiles(string $source): iterable
    {
        if (!class_exists('Archive_Tar')) {
            throw new Horde_Timezone_Exception(
                'Cannot extract timezone database: PharData extension is disabled '
                . 'and Archive_Tar is not installed. '
                . 'Install via: composer require pear/archive_tar'
            );
        }

        $rc = new ReflectionClass('Archive_Tar');
        $ctor = $rc->getConstructor();
        if ($ctor === null || $ctor->getNumberOfParameters() === 0) {
            throw new Horde_Timezone_Exception(
                'The installed Archive_Tar version is incompatible (constructor '
                . 'does not accept parameters). Please upgrade via: '
                . 'composer require pear/archive_tar'
            );
        }

        $tar = new Archive_Tar($source);
        foreach ($tar->listContent() as $file) {
            if ($file['typeflag'] != 0) {
                continue;
            }
            yield $tar->extractInString($file['filename']);
        }
    }
}
