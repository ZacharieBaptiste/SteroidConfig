Steroid Config

Usage

Config::setCacheDirectory($cache_directory);
$config = (new Config())->load('config.txt');
$locale = $config->get('dev.locale');
$locale = Config::instance()->get('dev.locale');


Todo

Finish readme.md