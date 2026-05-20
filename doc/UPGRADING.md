# Upgrading Horde_Timezone

## From 2.x (lib/) to Horde\Timezone (src/)

The `src/` namespace (`Horde\Timezone`) provides a modern and strictly-typed
replacement for the legacy `Horde_Timezone` classes in `lib/`. Both APIs
coexist and can be used in parallel during migration.

### Class mapping

| Legacy (lib/)                        | Modern (src/)                                  |
|--------------------------------------|------------------------------------------------|
| `Horde_Timezone`                     | `Horde\Timezone\TimezoneDatabase`              |
| `Horde_Timezone_Zone`               | `Horde\Timezone\Zone`                          |
| `Horde_Timezone_Rule`               | `Horde\Timezone\Rule`                          |
| `Horde_Timezone_Exception`          | `Horde\Timezone\Exception\TimezoneException`   |
| -                                    | `Horde\Timezone\Exception\ZoneNotFoundException` |
| `Horde_Timezone_Extractor` (interface) | `Horde\Timezone\Extractor\ExtractorInterface` |
| `Horde_Timezone_Extractor_PharData` | `Horde\Timezone\Extractor\PharDataExtractor`   |
| `Horde_Timezone_Extractor_ArchiveTar` | `Horde\Timezone\Extractor\ArchiveTarExtractor` |
| `Horde_Timezone_Extractor_Directory` | `Horde\Timezone\Extractor\DirectoryExtractor` |

### New classes (no legacy equivalent)

| Class                                | Purpose                              |
|--------------------------------------|--------------------------------------|
| `Horde\Timezone\TimezoneDatabaseConfig` | Typed configuration (replaces `$params` array) |
| `Horde\Timezone\Offset`             | Immutable UTC offset value object    |
| `Horde\Timezone\Month`              | Backed enum for months (1-12)        |
| `Horde\Timezone\Weekday`            | Backed enum for weekdays (ISO 1-7)   |
| `Horde\Timezone\Parser\OlsonParser` | Extracted Olson database parser      |
| `Horde\Timezone\Parser\ParseResult` | Parse result DTO                     |
| `Horde\Timezone\RuleProviderInterface` | Narrow interface for rule lookup   |

### Key differences

**Configuration** - Legacy uses an associative `$params` array. The modern version uses a
readonly config object with named constructor parameters:

```php
// Legacy
$tz = new Horde_Timezone(['location' => '/path/to/tzdata/']);

// Modern
$db = new TimezoneDatabase(new TimezoneDatabaseConfig(
    location: '/path/to/tzdata/',
    cacheTtl: 604800,
));
```

**Dependency injection** - The modern API accepts optional `ExtractorInterface`
and `CacheInterface` (PSR-16) via the constructor. The legacy class
auto-detects the extraction strategy internally.

**VTIMEZONE output** - Legacy returns a `Horde_Icalendar_Vtimezone` object;
modern returns an iCalendar string directly (no `horde/icalendar` dependency):

```php
// Legacy
$vtimezone = $tz->getZone('Europe/Berlin')->toVtimezone();
$ical = $vtimezone->exportVcalendar();

// Modern
$vtimezoneString = $db->getZone('Europe/Berlin')->toVtimezone();
```

**Exceptions** - Legacy throws `Horde_Timezone_Exception` (extends
`Horde_Exception`). Modern throws SPL-based exceptions extending
`Horde\Exception\HordeRuntimeException`:

- `TimezoneException` - general errors (download failure, parse error)
- `ZoneNotFoundException` - requested zone/link not found in database

**Extraction** - Both APIs support PharData (preferred), Archive_Tar (fallback),
and directory-based extraction. The modern API selects a strategy via the
`ExtractorInterface` parameter or auto-detects based on the location path.

### Migration steps

1. Replace `new Horde_Timezone($params)` with `new TimezoneDatabase(new TimezoneDatabaseConfig(...))`.
2. Replace `->getZone($name)->toVtimezone()->exportVcalendar()` with `->getZone($name)->toVtimezone()`.
3. Catch `ZoneNotFoundException` instead of checking for generic exceptions.
4. If injecting a custom extractor, implement `ExtractorInterface`.
5. Remove `horde/icalendar` from your dependencies if it was only needed for VTIMEZONE generation.

### Backward compatibility

The `lib/` classes remain functional and unchanged. Existing code using
`Horde_Timezone` continues to work without modification. The `lib/` API is
considered deprecated but will not be removed before the next major version.
