<?php declare(strict_types=1);

namespace Kuria\Debug;

use PHPUnit\Framework\TestCase;

class DumperTest extends TestCase
{
    function testShouldDumpBasicValues()
    {
        $assertions = [
            ['foo bar', '"foo bar"'],
            [123, '123'],
            [-123, '-123'],
            [1.53, '1.530000'],
            [-1.53, '-1.530000'],
            [INF, 'INF'],
            [-INF, '-INF'],
            [NAN, 'NaN'],
            [true, 'true'],
            [false, 'false'],
            [STDIN, 'resource\(stream#\d+\)', true],
            [null, 'NULL'],
            [[1, 2, 3], 'array[3]'],
            [new \stdClass(), 'object(stdClass)'],
            [new class {}, 'object\(<anonymous@.*DumperTest\.php.*>\)', true],
        ];

        $this->assertDumpResults($assertions, 1);
    }

    function testShouldDumpString()
    {
        $assertions = [
            ['12345678910', '"1234567891"...'],
            ['123456789žč', '"123456789ž"...'],
            ["\000", '"\000"'],
            ["\001", '"\001"'],
            ["\002", '"\002"'],
            ["\003", '"\003"'],
            ["\004", '"\004"'],
            ["\005", '"\005"'],
            ["\006", '"\006"'],
            ["\007", '"\a"'],
            ["\010", '"\b"'],
            ["\011", '"\t"'],
            ["\012", '"\n"'],
            ["\013", '"\v"'],
            ["\014", '"\f"'],
            ["\015", '"\r"'],
            ["\016", '"\016"'],
            ["\017", '"\017"'],
            ["\020", '"\020"'],
            ["\021", '"\021"'],
            ["\022", '"\022"'],
            ["\023", '"\023"'],
            ["\024", '"\024"'],
            ["\025", '"\025"'],
            ["\026", '"\026"'],
            ["\027", '"\027"'],
            ["\030", '"\030"'],
            ["\031", '"\031"'],
            ["\032", '"\032"'],
            ["\033", '"\033"'],
            ["\034", '"\034"'],
            ["\035", '"\035"'],
            ["\036", '"\036"'],
            ["\037", '"\037"'],
        ];

        $this->assertDumpResults($assertions, 1, 10);
    }

    /**
     * @dataProvider provideStringsToDumpAsHex
     * @param string $string
     * @param int    $width
     * @param string  $expectedOutput
     */
    function testShouldDumpStringAsHex($string, $width, $expectedOutput)
    {
        $this->assertSame($expectedOutput, Dumper::dumpStringAsHex($string, $width));
    }

    /**
     * @return array[]
     */
    function provideStringsToDumpAsHex()
    {
        return [
            [
                "Lorem ipsum dolor sit amet\nThis is a null byte: \x00",
                16,
                <<<EXPECTED
     0 : 4c 6f 72 65 6d 20 69 70 73 75 6d 20 64 6f 6c 6f [Lorem ipsum dolo]
    10 : 72 20 73 69 74 20 61 6d 65 74 0a 54 68 69 73 20 [r sit amet.This ]
    20 : 69 73 20 61 20 6e 75 6c 6c 20 62 79 74 65 3a 20 [is a null byte: ]
    30 : 00                                              [.]
EXPECTED
                ,
            ],
            [
                "Foo\r\nBar",
                5,
                <<<EXPECTED
     0 : 46 6f 6f 0d 0a [Foo..]
     5 : 42 61 72       [Bar]
EXPECTED
            ,
            ],
        ];
    }

    function testShouldDumpObject()
    {
        $testPropertyObject = new TestPropertiesA();
        $testPropertyObject->dynamic = 'hello';

        $testKeyEscapesObject = new \stdClass();
        $testKeyEscapesObject->{"key-escapes-\t\n\v"} = 'a';
        $testKeyEscapesObject->{"key-binary-\000\001\002"} = 'b';

        $assertions = [
            [$testPropertyObject, <<<EXPECTED
object(Kuria\Debug\TestPropertiesA) {
    public static [staticPublic] => "staticPublicA"
    protected static [staticProtected] => "staticProtectedA"
    private static [staticPrivate] => "staticPrivateA"
    public [public] => "publicA"
    protected [protected] => "protectedA"
    private [private] => "privateA"
    private [privateNonShadowed] => "privateNonShadowedA"
    public [dynamic] => "hello"
}
EXPECTED
                ,
            ],
            [new \DateTime('2015-01-01 00:00 UTC'), 'object(DateTime) "Thu, 01 Jan 2015 00:00:00 +0000"'],
            [new TestToString(), 'object(Kuria\Debug\TestToString) "foo bar"'],
            [$testKeyEscapesObject, <<<'EXPECTED'
object(stdClass) {
    public [key-escapes-\t\n\v] => "a"
    public [key-binary-\000\001\002] => "b"
}
EXPECTED
                ,
            ],
            [new TestDebugInfo(), <<<EXPECTED
object(Kuria\Debug\TestDebugInfo) {
    [foo] => "bar"
}
EXPECTED
                ,
            ],
        ];

        $this->assertDumpResults($assertions);
    }

    function testShouldDumpDeepObject()
    {
        $testObject = new \stdClass();

        $testObject->nestedObject = new \stdClass();

        $testObject->nestedObject->foo = [1, 2, 3];
        $testObject->nestedObject->bar = new TestPropertiesA();
        $testObject->nestedObject->baz = new TestToString();

        $expected = <<<EXPECTED
object(stdClass) {
    public [nestedObject] => object(stdClass) {
        public [foo] => array[3]
        public [bar] => object(Kuria\Debug\TestPropertiesA)
        public [baz] => object(Kuria\Debug\TestToString) "foo bar"
    }
}
EXPECTED;

        $this->assertSame($expected, Dumper::dump($testObject, 3));
    }

    function testShouldDumpArray()
    {
        $testArray = [
            "hello" => 'world',
            "key_escapes_\000\011\012\013\014\015" => 'a',
            "key_binary_\000\001\002" => 'b',
            'nested_array' => [
                123,
                [1, 2, 3],
            ],
        ];

        $expected = <<<'EXPECTED'
array[4] {
    [hello] => "world"
    [key_escapes_\000\t\n\v\f\r] => "a"
    [key_binary_\000\001\002] => "b"
    [nested_array] => array[2] {
        [0] => 123
        [1] => array[3]
    }
}
EXPECTED;

        $this->assertSame($expected, Dumper::dump($testArray, 3));
    }

    /**
     * @dataProvider provideObjectProperties
     * @param object $object
     * @param bool   $includeStatic
     * @param array  $expectedProperties
     */
    function testShouldGetObjectProperties($object, $includeStatic, array $expectedProperties)
    {
        $propertyReflections = Dumper::getObjectProperties($object, $includeStatic);

        $this->assertSame(array_keys($expectedProperties), array_keys($propertyReflections));

        foreach ($propertyReflections as $propertyReflection) {
            $this->assertInstanceOf('ReflectionProperty', $propertyReflection);
            $propertyReflection->setAccessible(true);
            $this->assertSame($expectedProperties[$propertyReflection->getName()], $propertyReflection->getValue($object));
        }
    }

    /**
     * @return array[]
     */
    function provideObjectProperties()
    {
        $a = new TestPropertiesA();
        $a->dynamic = 'dynamicA';

        $b = new TestPropertiesB();
        $b->dynamic = 'dynamicB';

        return [
            // object, includeStatic, expectedProperties
            [
                $a,
                true,
                [
                    'staticPublic' => 'staticPublicA',
                    'staticProtected' => 'staticProtectedA',
                    'staticPrivate' => 'staticPrivateA',
                    'public' => 'publicA',
                    'protected' => 'protectedA',
                    'private' => 'privateA',
                    'privateNonShadowed' => 'privateNonShadowedA',
                    'dynamic' => 'dynamicA',
                ],
            ],
            [
                $a,
                false,
                [
                    'public' => 'publicA',
                    'protected' => 'protectedA',
                    'private' => 'privateA',
                    'privateNonShadowed' => 'privateNonShadowedA',
                    'dynamic' => 'dynamicA',
                ],
            ],
            [
                $b,
                true,
                [
                    'staticPublic' => 'staticPublicB',
                    'staticProtected' => 'staticProtectedB',
                    'staticPrivate' => 'staticPrivateB',
                    'public' => 'publicB',
                    'protected' => 'protectedB',
                    'private' => 'privateB',
                    'dynamic' => 'dynamicB',
                    'privateNonShadowed' => 'privateNonShadowedA',
                ],
            ],
            [
                $b,
                false,
                [
                    'public' => 'publicB',
                    'protected' => 'protectedB',
                    'private' => 'privateB',
                    'dynamic' => 'dynamicB',
                    'privateNonShadowed' => 'privateNonShadowedA',
                ],
            ],
        ];
    }

    /**
     * @param array[] $assertions array of arrays: value, expected output, [is_regex?]
     */
    private function assertDumpResults(array $assertions, $maxLevel = 2, $maxStringLen = 64)
    {
        foreach ($assertions as $assertion) {
            list($value, $expected, $isRegex) = $assertion + [2 => null];

            $result = Dumper::dump($value, $maxLevel, $maxStringLen);

            if ($isRegex) {
                $this->assertRegExp('~^' . $expected . '$~', $result);
            } else {
                $this->assertSame($expected, $result);
            }
        }
    }
}

/**
 * @internal
 * @property mixed $dynamic
 */
class TestPropertiesA
{
    static $staticPublic = 'staticPublicA';
    protected static $staticProtected = 'staticProtectedA';
    private static $staticPrivate = 'staticPrivateA';
    public $public = 'publicA';
    protected $protected = 'protectedA';
    private $private = 'privateA';
    private $privateNonShadowed = 'privateNonShadowedA';
}

/**
 * @internal
 * @property mixed $dynamic
 */
class TestPropertiesB extends TestPropertiesA
{
    static $staticPublic = 'staticPublicB';
    protected static $staticProtected = 'staticProtectedB';
    private static $staticPrivate = 'staticPrivateB';
    public $public = 'publicB';
    protected $protected = 'protectedB';
    private $private = 'privateB';
}

/**
 * @internal
 */
class TestToString
{
    function __toString()
    {
        return 'foo bar';
    }
}

/**
 * @internal
 */
class TestDebugInfo
{
    public $someprop = 'somevalue';

    function __debugInfo()
    {
        return [
            'foo' => 'bar',
        ];
    }
}
