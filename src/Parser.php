<?php
/**
 * Created by PhpStorm.
 * User: Zacharie
 * Date: 21/05/15
 * Time: 22:50
 */

namespace Steroid\Config;

abstract class Parser
{
    public static function parse($conf)
    {
        $chdir = dirname($conf);

        $file = self::includeConf($conf, $chdir);
        $definitions = [];
        $current_roots = [];
        $config = [];

        $number_of_lines = count($file);
        for ($line_number = 0; $line_number < $number_of_lines; $line_number++) {
            $line = $file[$line_number];

            // Trim white space and comments
            $line = trim($line);
            $line = preg_replace('/\s*#.*/', '', $line);
            if (empty($line)) {
                // Ignore line
                continue;
            }

            // Check for root definitions
            if (preg_match('/^(\[[^]]+\])?\[\]=/', $line)) {
                // Parse definition and add to list of definitions
                self::parsePredefinedRootDefinition($line, $definitions);
            } // Check for root changes during parsing
            elseif (preg_match('/^\[[^]]*\]$/', $line)) {
                // Set roots for parser, lines below are prefixed with current root
                $current_roots = self::setCurrentRootsForParser($line);
            } // Read config key and values
            else {
                $line_number = self::parseConfigKeyValue($file, $line_number, $line, $current_roots, $config);
            }
        }

        if (!empty($config)) {
            $config = self::expandConfig($config, $definitions);
        }

        return $config;
    }

    private static function includeConf($conf, $chdir)
    {
        $config = [];

        if (is_file($conf)) {
            $file = file($conf);
        } else {
            $file = explode("\n", $conf);
        }

        foreach ($file as $idx => $row) {
            $row = trim($row);
            if (preg_match('/^#include\s*(.*)$/', $row, $matches)) {
                if ($chdir) {
                    $file = $chdir . '/' . $matches[1];
                } else {
                    $file = $matches[1];
                }

                self::merge($config, self::includeConf($file, $chdir));
            } else {
                $row = preg_replace('/\s*#.*/', '', $row);
                if (empty($row)) {
                    continue;
                }
                $config[] = $row;
            }
        }

        return $config;
    }

    private static function getPredefinedRootDefinition($keys, $definitions)
    {
        $node = $definitions;
        foreach ($keys as $key) {
            if (!isset($node[$key])) {
                if (isset($node['@'])) {
                    $key = '@';
                } else {
                    return false;
                }
            }
            $node = &$node[$key];
        }

        if (!is_array($node)) {
            return false;
        }

        /* Only return values, $v that is array, is another definition */
        $arr = [];
        foreach ($node as $k => $v) {
            if (is_array($v)) {
                break;
            }
            $arr[] = $v;
        }

        return $arr;
    }

    private static function expandConfig($config, $definitions, $trail = [])
    {
        if (is_array($config)) {
            $star = false;
            $def = false;
            if (isset($config['@'])) {
                $star = $config['@'];
                $arr = $trail;
                $arr[] = '@';
                $def = self::getPredefinedRootDefinition($arr, $definitions);
            }

            if ($def) {
                // Expand
                foreach ($def as $d) {
                    $arr = $trail;
                    $arr[] = $d;
                    if (isset($config[$d])) {
                        $original_config = $config[$d];
                        $config[$d] = self::expandConfig($star, $definitions, $arr);

                        self::merge(
                            $config[$d],
                            self::expandConfig($original_config, $definitions, $arr)
                        );
                    } else {
                        $config[$d] = self::expandConfig($star, $definitions, $arr);
                    }
                }
            } else {
                foreach ($config as $idx => $node) {
                    if (!is_array($node)) {
                        // Node reached isn't an array, don't recurse into it!
                        if ($star) {
                            self::exception("Expand error: idx:" . $idx . " node:" . $node);
                        }
                        continue;
                    }

                    $arr = $trail;
                    $arr[] = $idx;

                    if ($star) {
                        $config[$idx] = self::expandConfig($star, $definitions, $arr);
                        self::merge($config[$idx], self::expandConfig($node, $definitions, $arr));
                    } else {
                        $config[$idx] = self::expandConfig($node, $definitions, $arr);
                    }
                }
            }

            // Remove expand key after expand is complete!
            unset($config['@']);
        }

        return $config;
    }

    private static function merge(array &$dst_config, array $config)
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

    private static function filter($value, $filter)
    {
        if (method_exists(__CLASS__, $filter)) {
            return self::$filter($value);
        } else {
            if (function_exists($filter) && is_callable($filter)) {
                return call_user_func($filter, $value);
            }
        }

        return null;
    }

    private static function exception($str)
    {
        throw new \Exception(__NAMESPACE__ . "Exception: {$str}");
    }

    private static function parsePredefinedRootDefinition($line, &$definitions)
    {
        list ($keys, $values) = explode('=', $line);

        $parent_keys = trim(substr($keys, 0, -2), '[]');
        $values = explode(',', $values);

        $node =& $definitions;
        if (strlen($parent_keys)) {
            $parent_keys = explode('.', $parent_keys);
            foreach ($parent_keys as $key) {
                if (!isset($node[$key])) {
                    $node[$key] = [];
                }
                $node = &$node[$key];
            }
        }

        $node['@'] = $values;
    }

    private static function setCurrentRootsForParser($line)
    {
        $root_str = trim($line, '[]');
        if (empty($root_str)) {
            // Reset root
            return [];
        }

        // Set current roots
        $roots = explode(',', $root_str);
        foreach ($roots as $idx => $root) {
            $roots[$idx] = explode('.', $root);
        }
        return $roots;
    }

    private static function parseConfigKeyValue($file, $line_number, $line, $current_roots, &$config)
    {
        list ($keys, $value) = explode('=', $line, 2);

        // Extract the value
        list($line_number, $value) = self::parseConfigValue($file, $line_number, $value);

        // If no roots specified, then use an empty root
        if (empty($current_roots)) {
            $current_roots[] = [];
        }

        $keys = explode('.', $keys);

        // For every root, and sub keys set the value
        foreach ($current_roots as $root) {
            // Point to root of config
            $node = &$config;

            // If under current root, move pointer to leaf
            foreach ($root as $key) {
                if (!isset($node[$key])) {
                    $node[$key] = [];
                }
                $node = &$node[$key];
            }

            // Move node to leaf
            foreach ($keys as $key) {
                if (!isset($node[$key])) {
                    $node[$key] = [];
                }
                $node = &$node[$key];
            }

            // Set the value to the node
            if ($node) {
                self::exception("Double set:" . $line);
            }

            if (strcmp($value, (int)$value) == 0) {
                $node = (int)$value;
            } else {
                $node = $value;
            }

            unset($node);
        }

        return $line_number;
    }

    private static function parseConfigValue($file, $line_number, $value)
    {
        // Check for value blocks - Multiline blocks
        if (preg_match('/^\[((?P<filter>[a-zA-Z0-9_]+)\]\[)?$/', $value, $matches)) {
            $filter = null;
            if (isset($matches['filter'])) {
                $filter = $matches['filter'];
            }

            $value_str = '';
            while (true) {
                $line_number++;
                $multiline = $file[$line_number];

                if (trim($multiline) === ']') {
                    break;
                }

                if (strlen($value_str)) {
                    $value_str .= "\n";
                }

                // Fix escaped ]
                if (substr($multiline, 0, 2) == '\]') {
                    $multiline = substr($multiline, 1);
                }

                $value_str .= $multiline;
            }
            $value = trim($value_str);

            if ($filter) {
                $value = self::filter($value, $filter);
                return array($line_number, $value);
            }
            return array($line_number, $value);
        } elseif ($value === '\[') {
            // Value is only an escaped block, remove slashes
            $value = substr($value, 1);
            return array($line_number, $value);
        }

        return array($line_number, $value);
    }
}
