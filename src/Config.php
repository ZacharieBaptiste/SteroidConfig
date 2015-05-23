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
    private $config_read_from_cache;

    private static $cache_enabled;
    private static $cache_dir;

    private static $previous_namespace;

    public function __construct($namespace = 'default', array $config = [], $file_path = null)
    {
        if (isset(self::$instances[$namespace])) {
            trigger_error(
                __CLASS__ . " Config namespace '{$namespace}' has already been initialized, overwriting previous",
                E_USER_WARNING
            );
        }

        $this->config_read_from_cache = false;
        $this->config = $config;

        if ($file_path !== null) {
            $this->load($file_path);
        }

        self::$previous_namespace = $namespace;
        self::$instances[$namespace] = $this;
    }

    public static function clear($namespace = 'default')
    {
        if (isset(self::$instances[$namespace])) {
            unset(self::$instances[$namespace]);
        }
    }

    public static function setCacheDirectory($path)
    {
        self::$cache_enabled = false;
        self::$cache_dir = rtrim($path, DIRECTORY_SEPARATOR);

        if (!is_dir(self::$cache_dir)) {
            if (mkdir(self::$cache_dir, 0700)) {
                self::$cache_enabled = true;
            }
        } elseif (!is_writable(self::$cache_dir)) {
            if (chmod(self::$cache_dir, 0700)) {
                self::$cache_enabled = true;
            }
        } else {
            self::$cache_enabled = true;
        }
    }

    /* Multitone */

    public static function instance($namespace = 'default', array $config = [], $file_path = null)
    {
        if ($namespace === null) {
            if (self::$previous_namespace === null) {
                return null;
            }

            $namespace = self::$previous_namespace;
        }

        if (!isset(self::$instances[$namespace])) {
            return new Config($namespace, $config, $file_path);
        }

        self::$previous_namespace = $namespace;

        return self::$instances[$namespace];
    }

    /* Load */

    public function load($file_path)
    {
        $cache_file_name = null;
        $cache_file = null;
        $config = false;
        $filemtime = null;

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

        self::merge($this->config, $config);

        return $this;
    }

    public function isReadFromCache()
    {
        return $this->config_read_from_cache;
    }

    /* Conf lookup */

    public function get()
    {
        $args = func_get_args();
        return $this->getArray($args);
    }

    private function getArray()
    {
        $args = func_get_args();
        $args_count = func_num_args();

        if ($args_count === 2) {
            $root = $args[0];
            $arr = $args[1];
        } elseif ($args_count === 1) {
            $root = $this->config;
            $arr = $args[0];
        } else {
            return null;
        }

        foreach ($arr as $node) {
            if (strpos($node, '.') !== false) {
                $nodes = explode('.', $node);
                foreach ($nodes as $n) {
                    if (isset($root[$n])) {
                        $root = &$root[$n];
                    } else {
                        return null;
                    }
                }
            } else {
                if (isset($root[$node])) {
                    $root = &$root[$node];
                } else {
                    return null;
                }
            }
        }

        return $root;
    }

    public static function merge(array &$dst_config, array $config)
    {
        foreach ($config as $key => $value) {
            if (is_string($key)) {
                if (is_array($value) && array_key_exists($key, $dst_config) && is_array($dst_config[$key])) {
                    self::merge($dst_config[$key], $value);
                } else {
                    $dst_config[$key] = $value;
                }
            } else {
                $dst_config[] = $value;
            }
        }
    }
}
