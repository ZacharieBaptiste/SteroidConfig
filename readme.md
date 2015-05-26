## Steroid Config
[![Build Status](https://travis-ci.org/ZacharieBaptiste/SteroidConfig.svg?branch=master)](https://travis-ci.org/ZacharieBaptiste/SteroidConfig)
[![Coverage Status](https://coveralls.io/repos/ZacharieBaptiste/SteroidConfig/badge.svg)](https://coveralls.io/r/ZacharieBaptiste/SteroidConfig)
[![SensioLabsInsight](https://insight.sensiolabs.com/projects/25eab8de-8a2b-4d11-bb15-8057c5970a4a/mini.png)](https://insight.sensiolabs.com/projects/44c425af-90f6-4c25-b789-4ece28b01a2b)
[![Latest Stable Version](https://poser.pugx.org/steroid/config/v/stable.svg)](https://packagist.org/packages/steroid/config)
[![Monthly Downloads](https://poser.pugx.org/steroid/config/d/monthly.png)](https://packagist.org/packages/steroid/config)

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

#### Changelog:

v1.1.1 - Refactored parser

v1.1.0 - Added support for config roots
	 - Added multiline support with filter support
	 - More complete tests and config files with all supported syntaxes

v1.0.1 - The instance multione also creates an instance if not already created

v1.0.0 - Initial version
