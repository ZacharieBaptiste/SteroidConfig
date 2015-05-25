<?php

namespace Steroid\Config\Test;

use Steroid\Config\Config;

class ConfigTest extends \PHPUnit_Framework_TestCase
{
    private $expected_arr = [
        'key' => 'value',
        'key2' => [
            'primary' => 'value',
            'secondary' => 'value',
        ],
        'dev' => [
            'locale' => 'en_UK',
            'thumbnails' => [
                'large' => [
                    'width' => 640,
                    'height' => 480,
                ],
                'small' => [
                    'width' => 40,
                    'height' => 30,
                ],
            ],
            'database' => [
                'slave' => [
                    'host' => 'slave.localhost',
                    'username' => 'root',
                    'password' => 'root',
                ],
                'master' => [
                    'host' => 'localhost',
                    'username' => 'root',
                    'password' => 'root',
                ],
            ],
            'raw_text' => 'Lorem Ipsum is simply dummy text of the printing and typesetting industry.
Lorem Ipsum has been the industry\'s standard dummy text ever since the 1500s,
when an unknown printer took a galley of type and scrambled it to make a type specimen book.',
            'nl2br_text' => 'Lorem Ipsum is simply dummy text of the printing and typesetting industry.<br />
Lorem Ipsum has been the industry\'s standard dummy text ever since the 1500s,<br />
when an unknown printer took a galley of type and scrambled it to make a type specimen book.',
            'special_case' => [
                1 => '',
                2 => '[',
                3 => 'Lorem Ipsum is simply dummy text of the printing and typesetting industry.
]Lorem Ipsum has been the industry\'s standard dummy text ever since the 1500s,
when an unknown printer took a galley of type and scrambled it to make a type specimen book.',
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
            'database' => [
                'slave' => [
                    'host' => '10.0.10.2',
                    'username' => 'root',
                    'password' => 12345,
                ],
                'master' => [
                    'host' => '10.0.10.1',
                    'username' => 'root',
                    'password' => 12345,
                ],
            ],
            'raw_text' => 'Lorem Ipsum is simply dummy text of the printing and typesetting industry.
Lorem Ipsum has been the industry\'s standard dummy text ever since the 1500s,
when an unknown printer took a galley of type and scrambled it to make a type specimen book.',
            'nl2br_text' => 'Lorem Ipsum is simply dummy text of the printing and typesetting industry.<br />
Lorem Ipsum has been the industry\'s standard dummy text ever since the 1500s,<br />
when an unknown printer took a galley of type and scrambled it to make a type specimen book.',
            'special_case' => [
                1 => '',
                2 => '[',
                3 => 'Lorem Ipsum is simply dummy text of the printing and typesetting industry.
]Lorem Ipsum has been the industry\'s standard dummy text ever since the 1500s,
when an unknown printer took a galley of type and scrambled it to make a type specimen book.',
            ],
        ],
        'special_case_root_key' => 'This key will not be in the same level as dev and production keys',
    ];

    public function setUp()
    {
        $this->clearCacheDir();
        Config::resetAll();
        Config::setCacheDirectory(null);
    }

    public function tearDown()
    {
        $this->clearCacheDir();
        Config::resetAll();
        Config::setCacheDirectory(null);
    }

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

    public function testSetCacheDirectory()
    {
        Config::setCacheDirectory(__DIR__ . DIRECTORY_SEPARATOR . 'cache');
        $this->assertTrue(is_dir(__DIR__ . DIRECTORY_SEPARATOR . 'cache'), "Cache directory can't be created");
        $this->assertTrue(is_writable(__DIR__ . DIRECTORY_SEPARATOR . 'cache'), "Cache directory isn't writable");
    }

    public function testSetConfigWithArray()
    {
        $config = Config::instance()->set($this->expected_arr);
        $arr = $config->get();
        $this->assertTrue($arr === $this->expected_arr);
        $this->assertFalse($config->isReadFromCache(), "Config was read from cache");
    }

    public function testLoadConfigFileWithoutCache()
    {
        $config = Config::instance()->load(__DIR__ . DIRECTORY_SEPARATOR . 'files' . DIRECTORY_SEPARATOR . 'config.txt');

        $arr = $config->get();

        $this->assertTrue($arr === $this->expected_arr);

        $this->assertFalse($this->cacheDirContainsCacheFiles(), "Cache file exists");
    }

    public function testLoadConfigFileWithCache()
    {
        Config::setCacheDirectory(__DIR__ . DIRECTORY_SEPARATOR . 'cache');

        // First read, shouldn't be read from cache
        $config = Config::instance()->load(__DIR__ . DIRECTORY_SEPARATOR . 'files' . DIRECTORY_SEPARATOR . 'config.txt');
        $this->assertFalse($config->isReadFromCache(), "Config was read from cache");
        $arr = $config->get();
        $this->assertTrue($arr === $this->expected_arr);

        Config::resetAll();

        // Re-read from config, check if read from cache
        $config = Config::instance()->load(__DIR__ . DIRECTORY_SEPARATOR . 'files' . DIRECTORY_SEPARATOR . 'config.txt');
        $this->assertTrue($this->cacheDirContainsCacheFiles(), "Cache file doesn't exists");
        $this->assertTrue($config->isReadFromCache(), "Config wasn't read from cache");

        Config::resetAll();
        Config::setCacheDirectory(null);

        // Re-read from config again, check that it's not read from cache even if cache exists
        $config = Config::instance()->load(__DIR__ . DIRECTORY_SEPARATOR . 'files' . DIRECTORY_SEPARATOR . 'config.txt');
        $this->assertTrue($this->cacheDirContainsCacheFiles(), "Cache file doesn't exists");
        $this->assertFalse($config->isReadFromCache(), "Config was read from cache");
    }

    public function testGetKeys()
    {
        $config = Config::instance()->set($this->expected_arr);
        $this->assertTrue($config->get('dev.locale') === 'en_UK', 'Dev locale should be en_UK');
        $this->assertTrue($config->get('production.locale') === 'en_US', 'Production locale should be en_US');
        $this->assertTrue($config->get('production', 'locale') === 'en_US', "Get by separated keys doesn't work");
    }

    public function testGetKeysFromSubConfig()
    {
        $config = Config::instance()->set($this->expected_arr);
        $thumbnails = $config->get('production.thumbnails', 'large');

        $width = Config::instance($thumbnails)->get('width');

        $this->assertTrue($width === $this->expected_arr['production']['thumbnails']['large']['width']);
    }

    public function testGetKeysFromRootedSubConfig()
    {
        $config = Config::instance()->set($this->expected_arr)->setRoot('production');
        $large_thumbnails = $config->get('thumbnails', 'large');

        $width = Config::instance($large_thumbnails)->get('width');

        $this->assertTrue($width === $this->expected_arr['production']['thumbnails']['large']['width']);
    }

}
