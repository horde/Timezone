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

use Horde\Timezone\Exception\TimezoneException;
use Horde\Timezone\Exception\ZoneNotFoundException;
use Horde\Timezone\Extractor\ArchiveTarExtractor;
use Horde\Timezone\Extractor\DirectoryExtractor;
use Horde\Timezone\Extractor\ExtractorInterface;
use Horde\Timezone\Extractor\PharDataExtractor;
use Horde\Timezone\Parser\OlsonParser;
use Horde\Timezone\Parser\ParseResult;
use PharData;
use Psr\SimpleCache\CacheInterface;

/**
 * Main entry point for loading, parsing, and querying the Olson timezone database.
 *
 * Usage:
 *   $db = new TimezoneDatabase(new TimezoneDatabaseConfig(
 *       location: '/path/to/tzdata/'
 *   ));
 *   echo $db->getZone('America/New_York')->toVtimezone();
 */
class TimezoneDatabase implements RuleProviderInterface
{
    private TimezoneDatabaseConfig $config;
    private ?ExtractorInterface $extractor;
    private ?CacheInterface $cache;
    private ?ParseResult $result = null;

    public function __construct(
        ?TimezoneDatabaseConfig $config = null,
        ?ExtractorInterface $extractor = null,
        ?CacheInterface $cache = null,
    ) {
        $this->config = $config ?? new TimezoneDatabaseConfig();
        $this->extractor = $extractor;
        $this->cache = $cache;
    }

    /**
     * Returns the Zone object for the given timezone identifier.
     *
     * Supports standard timezone IDs (e.g. "America/New_York") and
     * common aliases (resolved via Link entries in the database).
     *
     * @throws ZoneNotFoundException If the timezone is not found.
     */
    public function getZone(string $zone): Zone
    {
        $this->ensureLoaded();

        // Resolve aliases
        $canonical = $this->result->resolveLink($zone);

        $zoneObj = $this->result->getZone($canonical);
        if ($zoneObj === null) {
            throw new ZoneNotFoundException(
                sprintf('Timezone %s not found', $zone)
            );
        }

        $zoneObj->setTzid($zone);
        return $zoneObj;
    }

    /**
     * Returns the named rule set.
     *
     * @throws TimezoneException If the rule is not found.
     */
    public function getRule(string $name): Rule
    {
        $this->ensureLoaded();
        return $this->result->getRule($name);
    }

    /**
     * Force (re)loading the timezone database.
     */
    public function load(): void
    {
        $this->result = null;
        $this->ensureLoaded();
    }

    private function ensureLoaded(): void
    {
        if ($this->result !== null) {
            return;
        }

        // Try cache first
        if ($this->cache !== null) {
            $cached = $this->cache->get('horde_timezone_v2');
            if ($cached instanceof ParseResult) {
                $this->result = $cached;
                return;
            }
        }

        $source = $this->resolveSource();
        $extractor = $this->resolveExtractor($source);
        $parser = new OlsonParser();
        $this->result = new ParseResult();

        foreach ($extractor->extractFiles($source) as $content) {
            if ($content !== '' && $content !== false) {
                $parser->parse($content, $this->result);
            }
        }

        // Store in cache
        if ($this->cache !== null) {
            $this->cache->set('horde_timezone_v2', $this->result, $this->config->cacheTtl);
        }
    }

    private function resolveSource(): string
    {
        $location = $this->config->location;

        if (is_dir($location)) {
            return $location;
        }

        if (str_starts_with($location, 'file://')) {
            $path = substr($location, 7);
            if (is_dir($path)) {
                return $path;
            }
            if (is_file($path)) {
                return $path;
            }
            throw new TimezoneException(
                sprintf('Timezone source not found: %s', $location)
            );
        }

        // Remote URLs are not supported in the src/ implementation.
        // Use the extractor with a pre-downloaded file or directory.
        if (is_file($location)) {
            return $location;
        }

        throw new TimezoneException(
            'Remote timezone database downloads are not supported in the modern API. '
            . 'Provide a local tarball path or a directory of extracted tzdata files.'
        );
    }

    private function resolveExtractor(string $source): ExtractorInterface
    {
        if ($this->extractor !== null) {
            return $this->extractor;
        }

        if (is_dir($source)) {
            return new DirectoryExtractor();
        }

        if (class_exists(PharData::class)) {
            return new PharDataExtractor();
        }

        return new ArchiveTarExtractor();
    }
}
