<?php

namespace Kuria\Debug;

/**
 * Value dumper
 *
 * Dumps arbitrary PHP values as strings.
 *
 * @author ShiraNai7 <shira.cz>
 */
class Dumper
{
    const DEFAULT_MAX_LEVEL = 2;
    const DEFAULT_MAX_STRING_LENGTH = 64;
    const DEFAULT_HEX_WIDTH = 16;

    /**
     * This is a static class
     */
    private function __construct()
    {
    }

    /**
     * Dump a value
     *
     * @param mixed       $value        the value to dump
     * @param int         $maxLevel     maximum nesting level
     * @param int|null    $maxStringLen maximum number of characters to dump (null = no limit)
     * @param string|null $encoding     string encoding (null = mb_internal_encoding())
     * @param int         $currentLevel current nesting level
     * @return string
     */
    public static function dump($value, $maxLevel = self::DEFAULT_MAX_LEVEL, $maxStringLen = self::DEFAULT_MAX_STRING_LENGTH, $encoding = null, $currentLevel = 1)
    {
        $output = '';
        $type = gettype($value);
        $indent = str_repeat('    ', $currentLevel);

        switch ($type) {
            case 'array':
                if ($currentLevel < $maxLevel && $value) {
                    // full
                    $output .= 'array[' . sizeof($value) . "] {\n";
                    foreach ($value as $key => $item) {
                        $output .= $indent . (is_string($key) ? static::dumpString($key, $maxStringLen, $encoding, '[]', '...') : "[{$key}]") . ' => ';
                        $output .= static::dump($item, $maxLevel, $maxStringLen, $encoding, $currentLevel + 1);
                        $output .= "\n";
                    }
                    if ($currentLevel > 1) {
                        $output .= str_repeat('    ', $currentLevel - 1);
                    }
                    $key = $item = null;
                    $output .= "}";
                } else {
                    // short
                    $output .= 'array[' . sizeof($value) . "]";
                }
                break;
            case 'object':
                $output .= 'object(';

                if (PHP_MAJOR_VERSION >= 7) {
                    // PHP 7+ (anonymous class names contain a NULL byte)
                    $className = get_class($value);

                    $output .= false !== ($nullBytePos = strpos($className, "\0"))
                        ? substr($className, 0, $nullBytePos)
                        : $className;
                } else {
                    $output .= get_class($value);
                }

                $output .= ')';

                // output formatted date-time value?
                if ($value instanceof \DateTime || $value instanceof \DateTimeInterface) {
                    $output .= " \"{$value->format(DATE_RFC1123)}\"";
                    break;
                }

                // dump properties?
                if ($currentLevel < $maxLevel) {
                    if (method_exists($value, '__debugInfo')) {
                        // use __debugInfo (PHP 5.6 feature)
                        $properties = $value->__debugInfo();
                        $actualProperties = false;
                    } else {
                        // use actual properties
                        $properties = static::getObjectProperties($value, true, true);
                        $actualProperties = true;
                    }

                    if ($properties) {
                        // full
                        $output .= " {\n";
                        if ($actualProperties) {
                            foreach ($properties as $key => $item) {
                                $output .=
                                    $indent
                                    . implode(' ', \Reflection::getModifierNames($item->getModifiers())) . ' '
                                    . static::dumpString($key, $maxStringLen, $encoding, '[]', '...')
                                    . ' => ';
                                $output .= static::dump($item->getValue($value), $maxLevel, $maxStringLen, $encoding, $currentLevel + 1);
                                $output .= "\n";
                            }
                        } else {
                            foreach ($properties as $key => $item) {
                                $output .= $indent . (is_string($key) ? static::dumpString($key, $maxStringLen, $encoding, '[]', '...') : "[{$key}]") . ' => ';
                                $output .= static::dump($item, $maxLevel, $maxStringLen, $encoding, $currentLevel + 1);
                                $output .= "\n";
                            }
                        }
                        $properties = $key = $item = null;
                        if ($currentLevel > 1) {
                            $output .= str_repeat('    ', $currentLevel - 1);
                        }
                        $output .= '}';

                        break;
                    }
                }

                // try to use __toString() if available
                if (method_exists($value, '__toString')) {
                    $output .= ' ' . static::dumpString((string) $value, $maxStringLen, $encoding, '""', '...');
                }
                break;
            case 'string':
                $output .= static::dumpString($value, $maxStringLen, $encoding, '""', '...');
                break;
            case 'integer':
                $output .= $value;
                break;
            case 'double':
                $output .= sprintf('%F', $value);
                break;
            case 'boolean':
                $output .= ($value ? 'true' : 'false');
                break;
            case 'resource':
                $output .= 'resource(' . get_resource_type($value) . '#' . ((int) $value) . ")";
                break;
            default:
                $output .= $type;
                break;
        }

        return $output;
    }

    /**
     * Dump a string and return the result
     *
     * All ASCII < 32 will be escaped in C style.
     *
     * @param string               $string    the string to dump
     * @param int|null             $maxLength maximum number of characters to dump (null = no limit)
     * @param string|null          $encoding  string encoding (null = mb_internal_encoding())
     * @param string|string[]|null $quotes    quote symbols (2 byte string or a 2-element array)
     * @param string|null          $ellipsis  ellipsis string (appended at the end if the string had to be shortened)
     * @return string
     */
    public static function dumpString($string, $maxLength = null, $encoding = null, $quotes = null, $ellipsis = null)
    {
        $stringLength = null === $encoding
            ? mb_strlen($string)
            : mb_strlen($string, $encoding);

        $tooLong = null !== $maxLength && $stringLength > $maxLength;

        return
            $quotes[0]
            . addcslashes(
                $tooLong
                    ? (null === $encoding
                    ? mb_substr($string, 0, $maxLength)
                    : mb_substr($string, 0, $maxLength, $encoding)
                )
                    : $string,
                "\000..\037"
            )
            . $quotes[1]
            . ($tooLong ? $ellipsis : '');
    }

    /**
     * Dump a string in HEX format
     *
     * @param string $string
     * @param int    $width
     * @return string
     */
    public static function dumpStringAsHex($string, $width = self::DEFAULT_HEX_WIDTH)
    {
        $string = (string) $string;

        static $from = '';
        static $to = '';

        if ('' === $from) {
            for ($i = 0; $i <= 0xFF; $i++) {
                $from .= chr($i);
                $to .= ($i >= 0x20 && $i <= 0x7E) ? chr($i) : '.';
            }
        }

        $hex = str_split(bin2hex($string), $width * 2);
        $chars = str_split(strtr($string, $from, $to), $width);

        $hexLineLength = $width * 2 + $width - 1;

        $offset = 0;
        $output = '';
        foreach ($hex as $i => $line) {
            if ($i > 0) {
                $output .= "\n";
            }
            $output .= sprintf('%6X', $offset) . ' : ' . str_pad(implode(' ', str_split($line, 2)), $hexLineLength) . ' [' . $chars[$i] . ']';
            $offset += $width;
        }

        return $output;
    }

    /**
     * Get all properties of the given object
     *
     * @param object $object
     * @param bool   $includeStatic
     * @param bool   $getReflection
     * @return mixed[]|\ReflectionProperty[]
     */
    public static function getObjectProperties($object, $includeStatic = true, $getReflection = false)
    {
        $output = array();

        try {
            $filter =
                \ReflectionProperty::IS_PUBLIC
                | \ReflectionProperty::IS_PROTECTED
                | \ReflectionProperty::IS_PRIVATE;

            $parentFilter = \ReflectionProperty::IS_PRIVATE; // only fetch private parent properties

            $reflection = new \ReflectionObject($object);
            foreach ($reflection->getProperties($filter) as $property) {
                if (!$includeStatic && $property->isStatic()) {
                    continue;
                }

                $property->setAccessible(true);
                $output[$property->getName()] = $getReflection ? $property : $property->getValue($object);
            }

            foreach (class_parents($object) as $parentClass) {
                $reflection = new \ReflectionClass($parentClass);

                foreach ($reflection->getProperties($parentFilter) as $property) {
                    if (
                        ($includeStatic || !$property->isStatic())
                        && !array_key_exists($name = $property->getName(), $output)
                    ) {
                        $property->setAccessible(true);
                        $output[$name] = $getReflection ? $property : $property->getValue($object);
                    }
                }
            }
        } catch (\ReflectionException $e) {
            // some objects may not be fully accessible (e.g. instances of internal classes)
        }

        return $output;
    }
}
