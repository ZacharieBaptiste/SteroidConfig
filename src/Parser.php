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
        $definitions = array();
        $pre_roots = array();

        for ($r = 0; $r < count($file); $r++) {
            $row = $file[$r];
            // Trim white space and comments
            $row = trim($row);
            $row = preg_replace('/\s*#.*/', '', $row);
            if (empty($row)) {
                continue;
            }

            // Check for definitions
            if (preg_match('/^([^\.]*\.)*\[\]=/', $row)) {
                list ($keys, $values) = explode('=', $row);
                $keys = substr($keys, 0, -2) . '@';
                $keys = explode('.', $keys);
                $values = explode(',', $values);
                $node = &$definitions;
                foreach ($keys as $key) {
                    if (!isset($node[$key])) {
                        $node[$key] = array();
                    }
                    $node = &$node[$key];
                }
                $node = $values;
                unset($node);
            } // Set current root
            elseif (preg_match('/^\[[^]]*\]$/', $row)) {
                $pre_root_str = trim($row, '[]');
                if (!empty($pre_root_str)) {
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
                if (preg_match('/^\[((?P<filter>[a-z0-9_]+)\|)?$/', $value, $matches)) {
                    $filter = null;
                    if (isset($matches['filter'])) {
                        if (is_callable("ConfigParser::{$matches['filter']}")) {
                            $filter = $matches['filter'];
                        }
                    }

                    $value_str = '';
                    while (true) {
                        $r++;
                        $rv = $file[$r];
                        if (trim($rv) == ']') {
                            break;
                        }
                        if (strlen($value_str)) {
                            $value_str .= "\n";
                        }
                        $value_str .= $rv;
                    }
                    $value = trim($value_str);

                    if ($filter) {
                        $value = Parser::$filter($value);
                    }
                }

                if (!empty($pre_roots)) {
                    foreach ($pre_roots as $pr) {
                        // Point to root of config
                        $node = &$config;

                        // If under current root, move pointer to leaf
                        foreach ($pr as $key) {
                            if (!isset($node[$key])) {
                                $node[$key] = array();
                            }
                            $node = &$node[$key];
                        }

                        // Move node to leaf
                        foreach ($keys as $key) {
                            if (!isset($node[$key])) {
                                $node[$key] = array();
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
                            $node[$key] = array();
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
            return array();
        }
        $config = self::expandConfig($config, $definitions);
        return $config;
    }

    private static function includeConf($conf, $chdir)
    {
        $config = array();

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

                Config::merge($config, self::includeConf($file, $chdir));
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
        $arr = array();
        foreach ($node as $k => $v) {
            if (is_array($v)) {
                break;
            }
            $arr[] = $v;
        }

        return $arr;
    }

    private static function expandConfig($config, $definitions, $trail = array())
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

                        Config::merge(
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
                        Config::merge($config[$idx], self::expandConfig($node, $definitions, $arr));
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

    private static function exception($str)
    {
        throw new \Exception(__NAMESPACE__ . "Exception: {$str}");
    }
}
