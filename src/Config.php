<?php
/**
 * Created by PhpStorm.
 * User: Zacharie
 * Date: 21/05/15
 * Time: 22:50
 */

namespace Steroid\Config;

class Config
{
    private static $instances = [];

    private $config;
    private $root;
    private $config_read_from_cache;

    private static $cache_enabled;
    private static $cache_dir;

    public function __construct($namespace = 'default')
    {
        if (is_string($namespace) && isset(self::$instances[$namespace])) {
            trigger_error(
                __CLASS__ . " namespace '{$namespace}' has already been initialized, overwriting previous",
                E_USER_WARNING
            );
        }

        $this->reset();

        if (is_array($namespace)) {
            $this->config = $namespace;
        } else {
            self::$instances[$namespace] = $this;
        }
    }

    public function reset()
    {
        $this->config_read_from_cache = false;
        $this->config = [];
        $this->root = null;

        return $this;
    }

    public static function resetAll()
    {
        self::$instances = [];
    }

    public static function setCacheDirectory($path)
    {
        self::$cache_enabled = false;
        self::$cache_dir = null;

        if ($path !== null) {
            if (!is_dir($path)) {
                if (mkdir($path, 0700)) {
                    self::$cache_enabled = true;
                    self::$cache_dir = rtrim($path, DIRECTORY_SEPARATOR);
                }
            } elseif (!is_writable($path)) {
                if (chmod($path, 0700)) {
                    self::$cache_enabled = true;
                    self::$cache_dir = rtrim($path, DIRECTORY_SEPARATOR);
                }
            } else {
                self::$cache_enabled = true;
                self::$cache_dir = rtrim($path, DIRECTORY_SEPARATOR);
            }
        }
    }

    /* Multitone */

    public static function instance($namespace = 'default')
    {
        // Temporary instance using an array object
        if (is_array($namespace)) {
            return (new self($namespace));
        }

        // Return old instance
        if (isset(self::$instances[$namespace])) {
            return self::$instances[$namespace];
        }

        // Create instance
        return new self($namespace);
    }

    /* Set */

    public function set(array $config) {
        $this->config_read_from_cache = false;
        $this->config = $config;

        return $this;
    }

    /* Load */

    public function load($file_path)
    {
        $cache_file_name = null;
        $cache_file = null;
        $config = false;
        $filemtime = null;

        $this->config_read_from_cache = false;

        if (self::$cache_enabled) {
            $cache_file_name = sha1($file_path) . '.php';
            $cache_file = self::$cache_dir . DIRECTORY_SEPARATOR . $cache_file_name;

            if (file_exists($cache_file)) {
                $filemtime = filemtime($file_path);

                list ($cache_filemtime, $cached_config) = include $cache_file;

                // Only used cached config if cache is based on config files filemtime
                if ($cache_filemtime === $filemtime) {
                    $this->config_read_from_cache = true;
                    $config = $cached_config;
                }
            }
        }

        if ($config === false) {
            $config = Parser::parse($file_path);
        }

        if (!is_array($config)) {
            trigger_error(__CLASS__ . " Could not load config from '{$file_path}'", E_USER_ERROR);
            return;
        }

        if (self::$cache_enabled) {
            if ($filemtime === null) {
                $filemtime = filemtime($file_path);
            }
            Output::write($config, $cache_file, $file_path, $filemtime);
        }

        $this->config = $config;

        return $this;
    }

    public function isReadFromCache()
    {
        return $this->config_read_from_cache;
    }

    /* Conf lookup */

    public function setRoot()
    {
        $argc = func_num_args();

        if ($argc === 0) {
            $this->root = null;
        } else {
            $this->root = func_get_args();
        }

        return $this;
    }

    public function get()
    {
        $keys = $this->root !== null ? $this->root : [];
        if (func_num_args()) {
            $keys = array_merge($keys, func_get_args());
        }

        $part = $this->config;
        foreach ($keys as $key) {
            if (strpos($key, '.') !== false) {
                $key_parts = explode('.', $key);
                foreach ($key_parts as $key_part) {
                    if (isset($part[$key_part])) {
                        $part = $part[$key_part];
                    } else {
                        return null;
                    }
                }
            } elseif (isset($part[$key])) {
                $part = $part[$key];
            } else {
                return null;
            }
        }

        return $part;
    }
}
