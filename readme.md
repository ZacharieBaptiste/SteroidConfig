## Steroid Config
[![Build Status](https://travis-ci.org/ZacharieBaptiste/SteroidConfig.svg?branch=master)](https://travis-ci.org/ZacharieBaptiste/SteroidConfig)

#### Installation:
"steroid/config": "~1.0"

#### Usage:
```php
Config::setCacheDirectory($cache_directory);
$config = Config::instance()->load('config.txt');
$locale = $config->get('dev.locale');
$locale = Config::instance()->get('dev.locale');
```

#### Config file syntax:
See tests, explanation coming soon.

#### Todo:
Finish readme.md


#### Changelog:

v1.0.1 - The instance multione also creates an instance if not already created

v1.0.0 - Initial version
