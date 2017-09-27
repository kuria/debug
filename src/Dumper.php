<?php declare(strict_types=1);

namespace Kuria\Debug;

/**
 * Value dumper
 *
 * Dumps arbitrary PHP values as strings.
 */
abstract class Dumper
{
    const DEFAULT_MAX_LEVEL = 2;
    const DEFAULT_MAX_STRING_LENGTH = 64;
    const DEFAULT_HEX_WIDTH = 16;
    const DATE_TIME_FORMAT = DATE_RFC1123;

    /**
     * Dump a value
     */
    static function dump(
        $value,
        int $maxLevel = self::DEFAULT_MAX_LEVEL,
        ?int $maxStringLen = self::DEFAULT_MAX_STRING_LENGTH,
        ?string $encoding = null,
        int $currentLevel = 1
    ): string {
        $output = '';
        $type = gettype($value);
        $indent = str_repeat('    ', $currentLevel);

        switch ($type) {
            case 'array':
                if ($currentLevel < $maxLevel && $value) {
                    // full
                    $output .= 'array[' . sizeof($value) . "] {\n";
                    foreach ($value as $key => $property) {
                        $output .= $indent . (is_string($key) ? static::dumpString($key, $maxStringLen, $encoding, ['[', ']'], '...') : "[{$key}]") . ' => ';
                        $output .= static::dump($property, $maxLevel, $maxStringLen, $encoding, $currentLevel + 1);
                        $output .= "\n";
                    }
                    if ($currentLevel > 1) {
                        $output .= str_repeat('    ', $currentLevel - 1);
                    }
                    $key = $property = null;
                    $output .= "}";
                } else {
                    // short
                    $output .= 'array[' . sizeof($value) . "]";
                }
                break;

            case 'object':
                $output .= 'object(';
                $className = get_class($value);

                if (strpos($className, "\0") !== false) {
                    $output .= '<anonymous>';
                } else {
                    $output .= $className;
                }

                $output .= ')';

                // output formatted date-time value?
                if ($value instanceof \DateTimeInterface) {
                    $output .= " \"{$value->format(static::DATE_TIME_FORMAT)}\"";
                    break;
                }

                // dump properties?
                if ($currentLevel < $maxLevel) {
                    if (method_exists($value, '__debugInfo')) {
                        // use __debugInfo
                        $properties = $value->__debugInfo();
                        $isReflectionProperties = false;
                    } else {
                        // use actual properties
                        $properties = static::getObjectProperties($value, true);
                        $isReflectionProperties = true;
                    }

                    if ($properties) {
                        // full
                        $output .= " {\n";
                        if ($isReflectionProperties) {
                            foreach ($properties as $propertyName => $reflectionProperty) {
                                $reflectionProperty->setAccessible(true);

                                $output .=
                                    $indent
                                    . implode(' ', \Reflection::getModifierNames($reflectionProperty->getModifiers())) . ' '
                                    . static::dumpString($propertyName, $maxStringLen, $encoding, ['[', ']'], '...')
                                    . ' => ';
                                $output .= static::dump($reflectionProperty->getValue($value), $maxLevel, $maxStringLen, $encoding, $currentLevel + 1);
                                $output .= "\n";
                            }
                            $propertyName = $reflectionProperty = null;
                        } else {
                            foreach ($properties as $propertyName => $propertyValue) {
                                $output .= $indent . (is_string($propertyName) ? static::dumpString($propertyName, $maxStringLen, $encoding, ['[', ']'], '...') : "[{$propertyName}]") . ' => ';
                                $output .= static::dump($propertyValue, $maxLevel, $maxStringLen, $encoding, $currentLevel + 1);
                                $output .= "\n";
                            }
                            $propertyName = $propertyValue = null;
                        }

                        $properties = null;

                        if ($currentLevel > 1) {
                            $output .= str_repeat('    ', $currentLevel - 1);
                        }

                        $output .= '}';
                        break;
                    }
                }

                // could not dump properties - use __toString() if available
                if (method_exists($value, '__toString')) {
                    $output .= ' ' . static::dumpString((string) $value, $maxStringLen, $encoding, ['"', '"'], '...');
                }
                break;

            case 'string':
                $output .= static::dumpString($value, $maxStringLen, $encoding, ['"', '"'], '...');
                break;

            case 'integer':
                $output .= $value;
                break;

            case 'double':
                if (-INF === $value) {
                    $output .= '-INF';
                } else {
                    $output .= sprintf('%F', $value);
                }
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
     * Dump a string
     *
     * All ASCII < 32 will be escaped in C style.
     *
     * - if $quotes is specified, it should be an array with 2 elements
     * - if $ellipsis is specified, it will be appended at the end if the string had to be shortened
     */
    static function dumpString(
        string $string,
        ?int $maxLength = null,
        ?string $encoding = null,
        ?array $quotes = null,
        ?string $ellipsis = null
    ): string {
        $stringLength = $encoding === null
            ? mb_strlen($string)
            : mb_strlen($string, $encoding);

        $tooLong = $maxLength !== null && $stringLength > $maxLength;

        return
            $quotes[0]
            . addcslashes(
                $tooLong
                    ? ($encoding === null
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
     */
    static function dumpStringAsHex(string $string, int $width = self::DEFAULT_HEX_WIDTH): string
    {
        $string = (string) $string;

        static $from = '';
        static $to = '';

        if ($from === '') {
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
     * @return \ReflectionProperty[]
     */
    static function getObjectProperties($object, bool $includeStatic = true): array
    {
        $properties = [];

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

                $properties[$property->getName()] = $property;
            }

            foreach (class_parents($object) as $parentClass) {
                $reflection = new \ReflectionClass($parentClass);

                foreach ($reflection->getProperties($parentFilter) as $property) {
                    if (
                        ($includeStatic || !$property->isStatic())
                        && !array_key_exists($name = $property->getName(), $properties)
                    ) {
                        $properties[$name] = $property;
                    }
                }
            }
        } catch (\ReflectionException $e) {
            // some objects may not be fully accessible (e.g. instances of internal classes)
        }

        return $properties;
    }
}
