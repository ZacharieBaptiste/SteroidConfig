<?php
/**
 * Created by PhpStorm.
 * User: Zacharie
 * Date: 22/05/15
 * Time: 00:30
 */

namespace Steroid\Config;

abstract class Output
{
    public static function write($config, $cache_file, $file_path, $filemtime)
    {
        $output = "<?php\n\n";
        $output .= "// " . __NAMESPACE__ . " cache <{$file_path}>\n\n";

        if (!empty($config)) {
            $output .= "return [{$filemtime}, ";
            $output .= self::varExport($config);
            $output .= "];\n";
        }

        file_put_contents($cache_file, $output);
    }

    // Copied from drupal
    private static function varExport($var, $prefix = '')
    {
        if (is_array($var)) {
            if (empty($var)) {
                $output = '[]';
            } else {
                $output = "[\n";
                // Don't export keys if the array is non associative.
                $export_keys = array_values($var) != $var;
                foreach ($var as $key => $value) {
                    $output .= '  '
                        . ($export_keys ? self::varExport($key) . ' => ' : '')
                        . self::varExport($value, '  ', false) . ",\n";
                }
                $output .= ']';
            }
        } elseif (is_bool($var)) {
            $output = $var ? 'true' : 'false';
        } elseif (is_string($var)) {
            if (strpos($var, "\n") !== false || strpos($var, "'") !== false) {
                // If the string contains a line break or a single quote, use the
                // double quote export mode. Encode backslash and double quotes and
                // transform some common control characters.
                $var = str_replace(['\\', '"', "\n", "\r", "\t"], ['\\\\', '\"', '\n', '\r', '\t'], $var);
                $output = '"' . $var . '"';
            } else {
                $output = "'" . $var . "'";
            }
        } elseif (is_object($var) && get_class($var) === 'stdClass') {
            // var_export() will export stdClass objects using an undefined
            // magic method __set_state() leaving the export broken. This
            // workaround avoids this by casting the object as an array for
            // export and casting it back to an object when evaluated.
            $output = '(object) ' . self::varExport((array)$var, $prefix);
        } else {
            $output = var_export($var, true);
        }

        if ($prefix) {
            $output = str_replace("\n", "\n$prefix", $output);
        }

        return $output;
    }
}
