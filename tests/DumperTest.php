<?php

namespace Kuria\Debug;

class DumperTest extends \PHPUnit_Framework_TestCase
{
    public function testDumpBasic()
    {
        $assertions = array(
            array('foo bar', '"foo bar"'),
            array(123, '123'),
            array(-123, '-123'),
            array(1.53, '1.530000'),
            array(-1.53, '-1.530000'),
            array(true, 'true'),
            array(false, 'false'),
            array(STDIN, 'resource\(stream#\d+\)', true),
            array(null, 'NULL'),
            array(array(1, 2, 3), 'array[3]'),
            array(new \stdClass(), 'object(stdClass)'),
        );

        $this->assertDumpResults($assertions, 1);
    }

    public function testDumpString()
    {
        $assertions = array(
            array('12345678910', '"1234567891"...'),
            array('123456789žč', '"123456789ž"...'),
            array("\000", '"\000"'),
            array("\001", '"\001"'),
            array("\002", '"\002"'),
            array("\003", '"\003"'),
            array("\004", '"\004"'),
            array("\005", '"\005"'),
            array("\006", '"\006"'),
            array("\007", '"\a"'),
            array("\010", '"\b"'),
            array("\011", '"\t"'),
            array("\012", '"\n"'),
            array("\013", '"\v"'),
            array("\014", '"\f"'),
            array("\015", '"\r"'),
            array("\016", '"\016"'),
            array("\017", '"\017"'),
            array("\020", '"\020"'),
            array("\021", '"\021"'),
            array("\022", '"\022"'),
            array("\023", '"\023"'),
            array("\024", '"\024"'),
            array("\025", '"\025"'),
            array("\026", '"\026"'),
            array("\027", '"\027"'),
            array("\030", '"\030"'),
            array("\031", '"\031"'),
            array("\032", '"\032"'),
            array("\033", '"\033"'),
            array("\034", '"\034"'),
            array("\035", '"\035"'),
            array("\036", '"\036"'),
            array("\037", '"\037"'),
        );

        $this->assertDumpResults($assertions, 1, 10);
    }

    /**
     * @dataProvider provideStringsToDumpAsHex
     * @param string $string
     * @param int    $width
     * @param string  $expectedOutput
     */
    public function testDumpStringAsHex($string, $width, $expectedOutput)
    {
        $this->assertSame($expectedOutput, Dumper::dumpStringAsHex($string, $width));
    }

    /**
     * @return array[]
     */
    public function provideStringsToDumpAsHex()
    {
        return array(
            array(
                "Lorem ipsum dolor sit amet\nThis is a null byte: \x00",
                16,
                <<<EXPECTED
     0 : 4c 6f 72 65 6d 20 69 70 73 75 6d 20 64 6f 6c 6f [Lorem ipsum dolo]
    10 : 72 20 73 69 74 20 61 6d 65 74 0a 54 68 69 73 20 [r sit amet.This ]
    20 : 69 73 20 61 20 6e 75 6c 6c 20 62 79 74 65 3a 20 [is a null byte: ]
    30 : 00                                              [.]
EXPECTED
                ,
            ),
            array(
                "Foo\r\nBar",
                5,
                <<<EXPECTED
     0 : 46 6f 6f 0d 0a [Foo..]
     5 : 42 61 72       [Bar]
EXPECTED
            ,
            ),
        );
    }
    
    public function testDumpObject()
    {
        $testPropertyObject = new TestPropertiesA();
        $testPropertyObject->dynamic = 'hello';

        // NULLs in object property names are not tested here
        // beacause they are not supported before PHP 7
        $testKeyEscapesObject = new \stdClass();
        $testKeyEscapesObject->{"key-escapes-\t\n\v"} = 'a';
        $testKeyEscapesObject->{"key-binary-\001\002"} = 'b';

        $assertions = array(
            array($testPropertyObject, <<<EXPECTED
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
            ),
            array(new \DateTime('2015-01-01 00:00 UTC'), 'object(DateTime) "Thu, 01 Jan 2015 00:00:00 +0000"'),
            array(new TestToString(), 'object(Kuria\Debug\TestToString) "foo bar"'),
            array($testKeyEscapesObject, <<<'EXPECTED'
object(stdClass) {
    public [key-escapes-\t\n\v] => "a"
    public [key-binary-\001\002] => "b"
}
EXPECTED
            ),
            array(new TestDebugInfo(), <<<EXPECTED
object(Kuria\Debug\TestDebugInfo) {
    [foo] => "bar"
}
EXPECTED
            ),
        );

        $this->assertDumpResults($assertions);
    }

    public function testDumpDeepObject()
    {
        $testObject = new \stdClass();

        $testObject->nestedObject = new \stdClass();

        $testObject->nestedObject->foo = array(1, 2, 3);
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

    public function testDumpArray()
    {
        $testArray = array(
            "hello" => 'world',
            "key_escapes_\000\011\012\013\014\015" => 'a',
            "key_binary_\000\001\002" => 'b',
            'nested_array' => array(
                123,
                array(1, 2, 3),
            ),
        );

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
    public function testGetObjectProperties($object, $includeStatic, array $expectedProperties)
    {
        $this->assertSame($expectedProperties, Dumper::getObjectProperties($object, $includeStatic));
    }

    /**
     * @dataProvider provideObjectProperties
     * @param object $object
     * @param bool   $includeStatic
     * @param array  $expectedProperties
     */
    public function testGetObjectPropertiesWithReflection($object, $includeStatic, array $expectedProperties)
    {
        $propertyReflections = Dumper::getObjectProperties($object, $includeStatic, true);

        $this->assertSame(array_keys($expectedProperties), array_keys($propertyReflections));

        foreach ($propertyReflections as $propertyReflection) {
            $this->assertInstanceOf('ReflectionProperty', $propertyReflection);
            $this->assertSame($expectedProperties[$propertyReflection->getName()], $propertyReflection->getValue($object));
        }
    }

    /**
     * @return array[]
     */
    public function provideObjectProperties()
    {
        $a = new TestPropertiesA();
        $a->dynamic = 'dynamicA';

        $b = new TestPropertiesB();
        $b->dynamic = 'dynamicB';

        return array(
            // $object, $includeStatic, $expectedProperties
            array(
                $a,
                true,
                array(
                    'staticPublic' => 'staticPublicA',
                    'staticProtected' => 'staticProtectedA',
                    'staticPrivate' => 'staticPrivateA',
                    'public' => 'publicA',
                    'protected' => 'protectedA',
                    'private' => 'privateA',
                    'privateNonShadowed' => 'privateNonShadowedA',
                    'dynamic' => 'dynamicA',
                ),
            ),
            array(
                $a,
                false,
                array(
                    'public' => 'publicA',
                    'protected' => 'protectedA',
                    'private' => 'privateA',
                    'privateNonShadowed' => 'privateNonShadowedA',
                    'dynamic' => 'dynamicA',
                ),
            ),
            array(
                $b,
                true,
                array(
                    'staticPublic' => 'staticPublicB',
                    'staticProtected' => 'staticProtectedB',
                    'staticPrivate' => 'staticPrivateB',
                    'public' => 'publicB',
                    'protected' => 'protectedB',
                    'private' => 'privateB',
                    'dynamic' => 'dynamicB',
                    'privateNonShadowed' => 'privateNonShadowedA',
                ),
            ),
            array(
                $b,
                false,
                array(
                    'public' => 'publicB',
                    'protected' => 'protectedB',
                    'private' => 'privateB',
                    'dynamic' => 'dynamicB',
                    'privateNonShadowed' => 'privateNonShadowedA',
                ),
            ),
        );
    }

    /**
     * @param array[] $assertions array of arrays: value, expected output, [is_regex?]
     */
    private function assertDumpResults(array $assertions, $maxLevel = 2, $maxStringLen = 64)
    {
        foreach ($assertions as $assertion) {
            list($value, $expected, $isRegex) = $assertion + array(2 => null);

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
 */
class TestPropertiesA
{
    public static $staticPublic = 'staticPublicA';
    protected static $staticProtected = 'staticProtectedA';
    private static $staticPrivate = 'staticPrivateA';
    public $public = 'publicA';
    protected $protected = 'protectedA';
    private $private = 'privateA';
    private $privateNonShadowed = 'privateNonShadowedA';
}

/**
 * @internal
 */
class TestPropertiesB extends TestPropertiesA
{
    public static $staticPublic = 'staticPublicB';
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
    public function __toString()
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

    public function __debugInfo()
    {
        return array(
            'foo' => 'bar',
        );
    }
}
