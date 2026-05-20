# Horde Timezone

Parses the [IANA timezone database](https://www.iana.org/time-zones) (Olson
format) and generates iCalendar VTIMEZONE components.

## Installation

```bash
composer require horde/timezone
```

## Quick start

```php
use Horde\Timezone\TimezoneDatabase;
use Horde\Timezone\TimezoneDatabaseConfig;

$db = new TimezoneDatabase(new TimezoneDatabaseConfig(
    location: '/path/to/tzdata/',
));

echo $db->getZone('America/New_York')->toVtimezone();
```

The `location` parameter accepts:

- A directory path containing unpacked Olson source files
- A path to a `.tar.gz` tarball
- A URL to download (default: `ftp://ftp.iana.org/tz/tzdata-latest.tar.gz`)

## Legacy API

The PSR-0 `Horde_Timezone` class in `lib/` remains available for backward
compatibility. See [doc/UPGRADING.md](doc/UPGRADING.md)
for migration guidance to the modern `Horde\Timezone` namespace.

## License

LGPL-2.1-only. See [LICENSE](LICENSE) for details.
