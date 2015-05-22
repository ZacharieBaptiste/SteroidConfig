<?php

namespace Steroid\Config\Test;

use Steroid\Config\Config;

class ConfigTest extends \PHPUnit_Framework_TestCase
{
    private $expected_arr = [
        'dev' => [
            'locale' => 'en_UK',
            'thumbnails' => [
                'large' => [
                    'width' => 640,
                    'height' => 480,
                ],
                'small' => [
                    'width' => 80,
                    'height' => 60,
                ],
            ],
        ],
        'production' => [
            'locale' => 'en_US',
            'thumbnails' => [
                'large' => [
                    'width' => 640,
                    'height' => 480,
                ],
                'small' => [
                    'width' => 80,
                    'height' => 60,
                ],
            ],
        ],
    ];

    private function clearCacheDir()
    {
        $cached_files = scandir(__DIR__ . DIRECTORY_SEPARATOR . 'cache');
        foreach ($cached_files as $file) {
            if (strpos($file, '.php') !== false) {
                $data = file_get_contents(__DIR__ . DIRECTORY_SEPARATOR . 'cache' . DIRECTORY_SEPARATOR . $file);
                if (strpos($data, ' cache ') !== false) {
                    unlink(__DIR__ . DIRECTORY_SEPARATOR . 'cache' . DIRECTORY_SEPARATOR . $file);
                }
            }
        }
    }

    private function cacheDirContainsCacheFiles()
    {
        if (!is_dir(__DIR__ . DIRECTORY_SEPARATOR . 'cache')) {
            return false;
        }

        $cached_files = scandir(__DIR__ . DIRECTORY_SEPARATOR . 'cache');
        foreach ($cached_files as $file) {
            if (strpos($file, '.php') !== false) {
                $data = file_get_contents(__DIR__ . DIRECTORY_SEPARATOR . 'cache' . DIRECTORY_SEPARATOR . $file);
                if (strpos($data, ' cache ') !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public function testReadConfigWithoutCache()
    {
        $this->clearCacheDir();
        Config::clear();

        $config = (new Config())->load(__DIR__ . DIRECTORY_SEPARATOR . 'files' . DIRECTORY_SEPARATOR . 'config.txt');

        $arr = $config->get();

        $this->assertTrue($arr === $this->expected_arr);

        $this->assertFalse($this->cacheDirContainsCacheFiles(), "Cache file exists");
    }

    public function testReadConfigWithCache()
    {
        $this->clearCacheDir();
        Config::clear();

        Config::setCacheDirectory(__DIR__ . DIRECTORY_SEPARATOR . 'cache');
        $this->assertTrue(is_dir(__DIR__ . DIRECTORY_SEPARATOR . 'cache'), "Cache directory can't be created");
        $this->assertTrue(is_writable(__DIR__ . DIRECTORY_SEPARATOR . 'cache'), "Cache directory isn't writable");

        $this->assertFalse($this->cacheDirContainsCacheFiles(), "Cache files shouldn't exists");

        // First read, shouldn't be read from cache
        $config = (new Config())->load(__DIR__ . DIRECTORY_SEPARATOR . 'files' . DIRECTORY_SEPARATOR . 'config.txt');
        $this->assertFalse($config->isReadFromCache(), "Config was read from cache");

        $arr = $config->get();
        $this->assertTrue($arr === $this->expected_arr);

        Config::clear();

        // Re-read from config, check if read from cache
        $config = (new Config())->load(__DIR__ . DIRECTORY_SEPARATOR . 'files' . DIRECTORY_SEPARATOR . 'config.txt');
        $this->assertTrue($this->cacheDirContainsCacheFiles(), "Cache file doesn't exists");
        $this->assertTrue($config->isReadFromCache(), "Config wasn't read from cache");
    }


    public function testGetKeys()
    {
        $this->clearCacheDir();
        Config::clear();

        Config::setCacheDirectory(__DIR__ . DIRECTORY_SEPARATOR . 'cache');
        (new Config())->load(__DIR__ . DIRECTORY_SEPARATOR . 'files' . DIRECTORY_SEPARATOR . 'config.txt');

        $config = Config::instance();
        $this->assertTrue($config->get('dev.locale') === 'en_UK', 'Dev locale should be en_UK');
        $this->assertTrue($config->get('production.locale') === 'en_US', 'Production locale should be en_US');
        $this->assertTrue($config->get('production', 'locale') === 'en_US', "Get by separated keys doesn't work");
    }
}
