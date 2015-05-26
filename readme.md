## Steroid Config
[![Build Status](https://travis-ci.org/ZacharieBaptiste/SteroidConfig.svg?branch=master)](https://travis-ci.org/ZacharieBaptiste/SteroidConfig)
[![Coverage Status](https://coveralls.io/repos/ZacharieBaptiste/SteroidConfig/badge.svg)](https://coveralls.io/r/ZacharieBaptiste/SteroidConfig)

#### Installation:
"steroid/config": "~1.0"

#### Usage:
```php
Config::setCacheDirectory($cache_directory);
$config = Config::instance()->load('config.txt');
$locale = $config->get('dev.locale');
$locale = Config::instance()->get('dev.locale');
```

Setting a root, usual if you defined roots in your config to separate dev and production or any other separation, all calls to the config will be prefixed with root.
Root can be set in bootstrap when config is initialized.

```php
$config = Config::instance()->setRoot('dev');
$locale = $config->get('locale');
$locale = Config::instance()->get('locale');
```

#### Config file syntax:
Documentation is still far from defined, all supported config's are listed and explained shortly in tests/files

#### Todo:
Finish readme.md

#### Changelog:

v1.1.0 - Added support for config roots
	 - Added multiline support with filter support
	 - More complete tests and config files with all supported syntaxes

v1.0.1 - The instance multione also creates an instance if not already created

v1.0.0 - Initial version
