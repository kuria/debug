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
        $type = gettype($value);

        switch ($type) {
            case 'array':
                return self::dumpArray($value, $maxLevel, $maxStringLen, $encoding, $currentLevel);

            case 'object':
                return self::dumpObject($value, $maxLevel, $maxStringLen, $encoding, $currentLevel);

            case 'string':
                return static::dumpString($value, $maxStringLen, $encoding, ['"', '"'], '...');

            case 'integer':
                return (string) $value;

            case 'double':
                return -INF === $value ? '-INF' : sprintf('%F', $value);

            case 'boolean':
                return $value ? 'true' : 'false';

            case 'resource':
                return 'resource(' . get_resource_type($value) . '#' . ((int) $value) . ')';

            default:
                return $type;
        }
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
                        && !key_exists($name = $property->getName(), $properties)
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

    private static function dumpArray(array $array, int $maxLevel, ?int $maxStringLen, ?string $encoding, int $currentLevel): string
    {
        $output = 'array[' . count($array) . ']';

        if ($currentLevel >= $maxLevel || empty($array)) {
            // short
            return $output;
        }

        // full
        $output .=" {\n";
        $indent = str_repeat('    ', $currentLevel);

        foreach ($array as $key => $property) {
            $output .= $indent;
            $output .= (is_string($key) ? static::dumpString($key, $maxStringLen, $encoding, ['[', ']'], '...') : "[{$key}]");
            $output .= ' => ';
            $output .= static::dump($property, $maxLevel, $maxStringLen, $encoding, $currentLevel + 1);
            $output .= "\n";
        }

        if ($currentLevel > 1) {
            $output .= str_repeat('    ', $currentLevel - 1);
        }

        $output .= '}';

        return $output;
    }

    private static function dumpObject($value, int $maxLevel, ?int $maxStringLen, ?string $encoding, int $currentLevel): string
    {
        $output = 'object(';
        $className = get_class($value);

        if (($nullBytePos = strpos($className, "\0")) !== false) {
            $output .= '<anonymous@' . substr($className, $nullBytePos + 1) . '>';
        } else {
            $output .= $className;
        }

        $output .= ')';

        do {
            // date time
            if ($value instanceof \DateTimeInterface) {
                $output .= " \"{$value->format(static::DATE_TIME_FORMAT)}\"";
                break;
            }

            // full dump
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
                    $indent = str_repeat('    ', $currentLevel);
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
                    } else {
                        foreach ($properties as $propertyName => $propertyValue) {
                            $output .= $indent
                                . (is_string($propertyName)
                                    ? static::dumpString($propertyName, $maxStringLen, $encoding, ['[', ']'], '...')
                                    : "[{$propertyName}]"
                                )
                                . ' => ';
                            $output .= static::dump($propertyValue, $maxLevel, $maxStringLen, $encoding, $currentLevel + 1);
                            $output .= "\n";
                        }
                    }

                    if ($currentLevel > 1) {
                        $output .= str_repeat('    ', $currentLevel - 1);
                    }

                    $output .= '}';
                    break;
                }
            }

            // short dump (or no properties) - use __toString() if available
            if (method_exists($value, '__toString')) {
                $output .= ' ' . static::dumpString((string) $value, $maxStringLen, $encoding, ['"', '"'], '...');
                break;
            }
        } while (false);

        return $output;
    }
}
