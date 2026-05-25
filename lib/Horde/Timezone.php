<?php

use Horde\Util\Util;

/**
 * Copyright 2011-2026 Horde LLC (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (LGPL). If you
 * did not receive this file, see http://www.horde.org/licenses/lgpl21.
 *
 * @author  Jan Schneider <jan@horde.org>
 * @package Timezone
 */

/**
 * Base class for loading, parsing, and working with timezones.
 *
 * This class is the central point to fetch timezone information from the
 * timezone (Olson) database, parse it, cache it, and generate VTIMEZONE
 * objects.
 *
 * Usage:
 * <code>
 * $tz = new Horde_Timezone();
 * $tz->getZone('America/New_York')->toVtimezone()->exportVcalendar();
 * </code>
 *
 * Documentation on the database file formats can be found at
 * ftp://ftp.iana.org/tz/tz-how-to.html
 *
 * @author  Jan Schneider <jan@horde.org>
 * @package Timezone
 */
class Horde_Timezone
{
    /**
     * Any configuration parameters for this class.
     *
     * @var array
     */
    protected $_params;

    /**
     * File location of the downloaded timezone database.
     *
     * @var string
     */
    protected $_tmpfile;

    /**
     * List of all Zone entries parsed into Horde_Timezone_Zone objects.
     *
     * @var array
     */
    protected $_zones = [];

    /**
     * List of all Rule entries parsed into Horde_Timezone_Rule objects.
     *
     * @var array
     */
    protected $_rules = [];

    /**
     * Alias map of all Link entries.
     *
     * @var array
     */
    protected $_links = [];

    /**
     * List to map month descriptions used in the timezone database.
     *
     * @var array
     */
    protected static $_months = ['Jan' => 1,
        'Feb' => 2,
        'Mar' => 3,
        'Apr' => 4,
        'May' => 5,
        'Jun' => 6,
        'Jul' => 7,
        'Aug' => 8,
        'Sep' => 9,
        'Oct' => 10,
        'Nov' => 11,
        'Dec' => 12];

    /**
     * Constructor.
     *
     * @param array $params  List of optional class parameters. Possible
     *                       options:
     *                       - location: (string) Location of the timezone
     *                         database. Can be a URL (ftp:// or http://) for
     *                         downloading a tarball, a file:// path to a local
     *                         tarball, or a directory path containing
     *                         pre-extracted Olson timezone source files.
     *                         Defaults to ftp.iana.org/tz/tzdata-latest.tar.gz.
     *                       - client: (Horde_Http_Client) A preconfigured
     *                         HTTP client for downloading via HTTP.
     *                       - temp: (string) Temporary directory.
     *                       - cache: (Horde_Cache) A cache object.
     *                       - cachettl: (integer) Cache lifetime in seconds,
     *                         defaults to 7 days.
     *                       - extractor: (Horde_Timezone_Extractor) An explicit
     *                         extractor instance, bypassing auto-detection.
     */
    public function __construct(array $params = [])
    {
        $this->_params = array_merge(
            ['location' => 'ftp://ftp.iana.org/tz/tzdata-latest.tar.gz',
                'cachettl' => 604800],
            $params
        );
    }

    /**
     * Returns the month number of a month name.
     *
     * @param string $month  A month name.
     *
     * @return integer  The month's number.
     */
    public static function getMonth($month)
    {
        return self::$_months[substr($month, 0, 3)];
    }

    /**
     * Returns an object representing an invidual timezone.
     *
     * Maps to a "Zone" entry in the timezone database. Works with
     * zone aliases and other common timezone names too.
     *
     * @param string $zone  A timezone name.
     *
     * @return Horde_Timezone_Zone  A timezone object.
     */
    public function getZone($zone)
    {
        if (!$this->_zones) {
            $this->_extractAndParse();
        }
        $zone = Horde_Date::getTimezoneAlias($zone);
        $alias = $this->_links[$zone] ?? $zone;
        if (!isset($this->_zones[$alias])) {
            throw new Horde_Timezone_Exception(sprintf('Timezone %s not found', $zone));
        }
        $this->_zones[$alias]->setTzid($zone);
        return $this->_zones[$alias];
    }

    /**
     * Returns an object representing a set of named transition rules.
     *
     * Maps to a list Rule entries of the same name in the timezone database.
     *
     * @param string $rule  A rule name.
     *
     * @return Horde_Timezone_Rule  A rule object.
     */
    public function getRule($rule)
    {
        if (!$this->_rules) {
            $this->_extractAndParse();
        }
        if (!isset($this->_rules[$rule])) {
            throw new Horde_Timezone_Exception(sprintf('Timezone rule %s not found', $rule));
        }
        return $this->_rules[$rule];
    }

    /**
     * Downloads a timezone database.
     *
     * @throws Horde_Timezone_Exception if downloading fails.
     */
    protected function _download()
    {
        $url = @parse_url($this->_params['location']);
        if (!isset($url['scheme'])) {
            throw new Horde_Timezone_Exception('"location" parameter is missing an URL scheme.');
        }
        if (!in_array($url['scheme'], ['http', 'ftp', 'file'])) {
            throw new Horde_Timezone_Exception(sprintf('Unsupported URL scheme "%s"', $url['scheme']));
        }
        if ($url['scheme'] == 'http') {
            if (isset($this->_params['client'])) {
                $client = $this->_params['client'];
            } else {
                $client = new Horde_Http_Client();
            }
            $response = $client->get($this->_params['location']);
            $this->_tmpfile = Util::getTempFile(
                '',
                true,
                $this->_params['temp'] ?? ''
            );
            stream_copy_to_stream($response->getStream(), fopen($this->_tmpfile, 'w'));
        } elseif ($url['scheme'] == 'ftp') {
            try {
                $vfs = new Horde_Vfs_Ftp(['hostspec' => $url['host'],
                    'username' => 'anonymous',
                    'password' => 'anonymous',
                    'pasv' => true]);
                $this->_tmpfile = $vfs->readFile(
                    dirname($url['path']),
                    basename($url['path'])
                );
            } catch (Horde_Vfs_Exception $e) {
                throw new Horde_Timezone_Exception($e);
            }
        } else {
            $this->_tmpfile = $url['path'];
            unset($php_errormsg);
            if (!is_readable($this->_tmpfile)) {
                $e = new Horde_Timezone_Exception(sprintf('Unable to open file %s.', $this->_params['location']));
                if (isset($php_errormsg)) {
                    $e->details = $php_errormsg;
                }
                throw $e;
            }
            return;
        }

        // PharData requires a recognized archive extension in the filename.
        // Temp files from getTempFile/VFS have no extension, so rename.
        $ext = $this->_getArchiveExtension($url['path']);
        if ($ext !== '') {
            $target = $this->_tmpfile . $ext;
            rename($this->_tmpfile, $target);
            $this->_tmpfile = $target;
            Util::deleteAtShutdown($target);
        }
    }

    /**
     * Derives the archive extension from a URL path.
     *
     * @param string $path  The URL path component (e.g. /tz/tzdata-latest.tar.gz).
     *
     * @return string  The extension including dot (e.g. ".tar.gz") or empty string.
     */
    protected function _getArchiveExtension(string $path): string
    {
        $basename = basename($path);

        if (preg_match('/(\\.tar\\.gz|\\.tar\\.bz2|\\.tgz|\\.tar)$/i', $basename, $m)) {
            return $m[1];
        }

        return '';
    }

    /**
     * Checks whether a file path has an extension that PharData can handle.
     *
     * @param string $path  A file path.
     *
     * @return bool
     */
    protected function _hasPharCompatibleExtension(string $path): bool
    {
        return $this->_getArchiveExtension($path) !== '';
    }

    /**
     * Unpacks the downloaded timezone database and parses all files.
     */
    protected function _extractAndParse()
    {
        if (isset($this->_params['cache'])) {
            $result = @unserialize(
                $this->_params['cache']->get(
                    'horde_timezone',
                    $this->_params['cachettl']
                )
            );
            if ($result) {
                $this->_zones = $result['zones'];
                $this->_rules = $result['rules'];
                $this->_links = $result['links'];
                return;
            }
        }

        $source = $this->_getSource();
        $extractor = $this->_getExtractor($source);

        foreach ($extractor->extractFiles($source) as $content) {
            if ($content !== false && $content !== '') {
                $this->_parse($content);
            }
        }

        if (isset($this->_params['cache'])) {
            $this->_params['cache']->set(
                'horde_timezone',
                serialize([
                    'zones' => $this->_zones,
                    'rules' => $this->_rules,
                    'links' => $this->_links,
                ]),
                $this->_params['cachettl']
            );
        }
    }

    /**
     * Returns the source path for timezone data.
     *
     * If the location parameter points to a directory, returns it directly
     * for use with the directory extractor. Otherwise downloads the tarball
     * and returns the temp file path.
     *
     * @return string A file path (tarball) or directory path.
     */
    protected function _getSource()
    {
        if (is_dir($this->_params['location'])) {
            return $this->_params['location'];
        }

        if (strpos($this->_params['location'], 'file://') === 0) {
            $path = substr($this->_params['location'], 7);
            if (is_dir($path)) {
                return $path;
            }
        }

        if (!$this->_tmpfile) {
            $this->_download();
        }

        return $this->_tmpfile;
    }

    /**
     * Returns the appropriate extractor for the given source.
     *
     * Selection order:
     * - Directory source: Horde_Timezone_Extractor_Directory
     * - Tarball source with PharData available: Horde_Timezone_Extractor_PharData
     * - Tarball source without PharData: Horde_Timezone_Extractor_ArchiveTar
     *
     * @param string $source  A file or directory path.
     *
     * @return Horde_Timezone_Extractor
     * @throws Horde_Timezone_Exception
     */
    protected function _getExtractor($source)
    {
        if (isset($this->_params['extractor'])) {
            return $this->_params['extractor'];
        }

        if (is_dir($source)) {
            return new Horde_Timezone_Extractor_Directory();
        }

        if (class_exists('PharData') && $this->_hasPharCompatibleExtension($source)) {
            return new Horde_Timezone_Extractor_PharData();
        }

        return new Horde_Timezone_Extractor_ArchiveTar();
    }

    /**
     * Parses a file from the timezone database.
     *
     * @param string $file  A file location.
     */
    protected function _parse($file)
    {
        $stream = new Horde_Support_StringStream($file);
        $fp = $stream->fopen();
        $zone = null;
        while (($line = fgets($fp)) !== false) {
            $line = trim($line);
            if (!strlen($line) || $line[0] == '#') {
                continue;
            }
            $column = preg_split('/\s+/', preg_replace('/\s*#.*$/', '', $line));
            switch ($column[0]) {
                case 'Rule':
                    if (!isset($this->_rules[$column[1]])) {
                        $this->_rules[$column[1]] = new Horde_Timezone_Rule($column[1]);
                    }
                    $this->_rules[$column[1]]->add($column);
                    $zone = null;
                    break;

                case 'Link':
                    $this->_links[$column[2]] = $column[1];
                    $zone = null;
                    break;

                case 'Zone':
                    $zone = $column[1];
                    $this->_zones[$zone] = new Horde_Timezone_Zone($zone, $this);
                    array_splice($column, 0, 2);
                    // Fall through.

                    // no break
                default:
                    if (empty($zone) || !isset($this->_zones[$zone])) {
                        break;
                    }
                    $this->_zones[$zone]->add($column);
                    break;
            }
        }
    }
}
