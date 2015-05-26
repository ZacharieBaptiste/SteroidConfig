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
    public static function parse($conf, $chdir = null)
    {
        if ($chdir !== false) {
            if ($chdir === null) {
                $chdir = dirname($conf);
            }
        }

        $file = self::includeConf($conf, $chdir);
        $definitions = [];
        $pre_roots = [];

	$count = count($file);
        for ($r = 0; $r < $count; $r++) {
            $row = $file[$r];
            // Trim white space and comments
            $row = trim($row);
            $row = preg_replace('/\s*#.*/', '', $row);
            if (empty($row)) {
                continue;
            }

            // Check for root definitions
            if (preg_match('/^(\[[^]]+\])?\[\]=/', $row)) {
                list ($keys, $values) = explode('=', $row);

                $parent_keys = trim(substr($keys, 0, -2), '[]');
                $values = explode(',', $values);

                $node = &$definitions;

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

                unset($node);
            } // Set current root
            elseif (preg_match('/^\[[^]]*\]$/', $row)) {
                $pre_root_str = trim($row, '[]');
                if (empty($pre_root_str)) {
                    // Reset pre roots
                    $pre_roots = [];
                } else {
                    // Set pre roots
                    $pre_roots = explode(',', $pre_root_str);
                    foreach ($pre_roots as $idx => $pr) {
                        $pre_roots[$idx] = explode('.', $pr);
                    }
                }
            } // Update config
            else {
                list ($keys, $value) = explode('=', $row, 2);
                $keys = explode('.', $keys);

                // Check for value blocks
                if (preg_match('/^\[((?P<filter>[a-zA-Z0-9_]+)\]\[)?$/', $value, $matches)) {
                    $filter = null;
                    if (isset($matches['filter'])) {
                        $filter = $matches['filter'];
                    }

                    $value_str = '';
                    while (true) {
                        $r++;
                        $rv = $file[$r];
                        if (trim($rv) === ']') {
                            break;
                        }

                        if (strlen($value_str)) {
                            $value_str .= "\n";
                        }

                        // Fix escaped ]
                        if (substr($rv, 0, 2) == '\]') {
                            $rv = substr($rv, 1);
                        }

                        $value_str .= $rv;
                    }
                    $value = trim($value_str);

                    if ($filter) {
                        $value = self::filter($value, $filter);
                    }
                } else {
                    if ($value === '\[') {
                        $value = substr($value, 1);
                    }
                }

                if (!empty($pre_roots)) {
                    foreach ($pre_roots as $pr) {
                        // Point to root of config
                        $node = &$config;

                        // If under current root, move pointer to leaf
                        foreach ($pr as $key) {
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
                            self::exception("Double set:" . $row);
                        }

                        if (strcmp($value, (int)$value) == 0) {
                            $node = (int)$value;
                        } else {
                            $node = $value;
                        }

                        unset($node);
                    }
                } else {
                    // Point to root of config
                    $node = &$config;

                    // Move node to leaf
                    foreach ($keys as $key) {
                        if (!isset($node[$key])) {
                            $node[$key] = [];
                        } elseif (!is_array($node[$key])) {
                            self::exception("Array node error: row:" . $row);
                        }
                        $node = &$node[$key];
                    }

                    // Set the value to the node
                    if ($node) {
                        self::exception("Double set:" . $row);
                    }

                    if (strcmp($value, (int)$value) == 0) {
                        $node = (int)$value;
                    } else {
                        $node = $value;
                    }

                    unset($node);
                }
            }
        }

        if (!isset($config)) {
            return [];
        }
        $config = self::expandConfig($config, $definitions);
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

    private static function getDefinition($keys, $definitions)
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
                $def = self::getDefinition($arr, $definitions);
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
                        // Node reached isn't an array, don't recurse int it!
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
}
