## Steroid Config

#### Installation:
"steroid/config": "1.0.0"

#### Usage:
```php
Config::setCacheDirectory($cache_directory);
$config = (new Config())->load('config.txt');
$locale = $config->get('dev.locale');
$locale = Config::instance()->get('dev.locale');
```

#### Config file syntax:
See tests, explanation coming soon.

#### Todo:
Finish readme.md
