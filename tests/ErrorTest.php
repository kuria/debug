<?php declare(strict_types=1);

namespace Kuria\Debug;

use PHPUnit\Framework\TestCase;

class ErrorTest extends TestCase
{
    function testGetExceptionChain()
    {
        $c = new \Exception('C');
        $b = new \Exception('B', 0, $c);
        $a = new \Exception('A', 0, $b);

        $this->assertSame([$a, $b, $c], Error::getExceptionChain($a));
        $this->assertSame([$b, $c], Error::getExceptionChain($b));
        $this->assertSame([$c], Error::getExceptionChain($c));
    }

    function testJoinExceptionChains()
    {
        $c = new \Exception('C');
        $b = new \Exception('B', 0, $c);
        $a = new \Exception('A', 0, $b);

        $z = new \Exception('Z');
        $y = new \Exception('Y', 0, $z);
        $x = new \Exception('X', 0, $y);

        $result = Error::joinExceptionChains($a, $x);

        $this->assertSame($x, $result);
        $this->assertSame($y, $x->getPrevious());
        $this->assertSame($z, $y->getPrevious());
        $this->assertSame($a, $z->getPrevious());
        $this->assertSame($b, $a->getPrevious());
        $this->assertSame($c, $b->getPrevious());
        $this->assertNull($c->getPrevious());
    }

    function testJoinExceptionChainsWithoutPrevious()
    {
        $a = new \Exception();
        $b = new \Exception();

        $result = Error::joinExceptionChains($a, $b);

        $this->assertSame($b, $result);
        $this->assertSame($a, $b->getPrevious());
    }

    function testJoinExceptionChainsWithDifferentExceptionHierarchies()
    {
        $c = new \Exception('C');
        $b = new \Exception('B', 0, $c);
        $a = new \Exception('A', 0, $b);

        $z = new \Error('Z');
        $y = new \Error('Y', 0, $z);
        $x = new \Error('X', 0, $y);

        $result = Error::joinExceptionChains($a, $x);

        $this->assertSame($x, $result);
        $this->assertSame($y, $x->getPrevious());
        $this->assertSame($z, $y->getPrevious());
        $this->assertSame($a, $z->getPrevious());
        $this->assertSame($b, $a->getPrevious());
        $this->assertSame($c, $b->getPrevious());
        $this->assertNull($c->getPrevious());
    }

    function testRenderException()
    {
        $testException = new \Exception(
            'Test exception',
            0,
            new \Exception('Test previous exception')
        );

        // default (trace = on, previous = off)
        $output = Error::renderException($testException);

        $this->assertContains('Test exception', $output);
        $this->assertNotContains('Test previous exception', $output);
        $this->assertContains('{main}', $output);
        $this->assertSame($output, trim($output));

        // trace = on, previous = on
        $output = Error::renderException($testException, true, true);
        $this->assertContains('Test exception', $output);
        $this->assertContains('Test previous exception', $output);
        $this->assertContains('{main}', $output);
        $this->assertSame($output, trim($output));

        // trace = off, previous = on
        $output = Error::renderException($testException, false, true);
        $this->assertContains('Test exception', $output);
        $this->assertContains('Test previous exception', $output);
        $this->assertNotContains('{main}', $output);
        $this->assertSame($output, trim($output));

        // trace = off, previous = off
        $output = Error::renderException($testException, false, false);
        $this->assertContains('Test exception', $output);
        $this->assertNotContains('Test previous exception', $output);
        $this->assertNotContains('{main}', $output);
        $this->assertSame($output, trim($output));
    }

    function testGetExceptionName()
    {
        $this->assertSame('Exception', Error::getExceptionName(new \Exception('Test exception')));
        $this->assertSame('Exception (123)', Error::getExceptionName(new \Exception('Test exception', 123)));
        $this->assertSame('Error', Error::getExceptionName(new \ErrorException('Test error', 0, E_ERROR)));
        $this->assertSame('Error (456)', Error::getExceptionName(new \ErrorException('Test error', 456, E_ERROR)));
        $this->assertSame('ErrorException', Error::getExceptionName(new \ErrorException('Test error', 0, 123456789)));
        $this->assertSame('ErrorException (789)', Error::getExceptionName(new \ErrorException('Test error', 789, 123456789)));
    }

    function testGetErrorNameByCode()
    {
        $errorLevels = [
            E_ERROR => 'Error',
            E_WARNING => 'Warning',
            E_PARSE => 'Parse error',
            E_NOTICE => 'Notice',
            E_CORE_ERROR => 'Core error',
            E_CORE_WARNING => 'Core warning',
            E_COMPILE_ERROR => 'Compile error',
            E_COMPILE_WARNING => 'Compile warning',
            E_USER_ERROR => 'User error',
            E_USER_WARNING => 'User warning',
            E_USER_NOTICE => 'User notice',
            E_STRICT => 'Strict notice',
            E_RECOVERABLE_ERROR => 'Recoverable error',
            E_DEPRECATED => 'Deprecated',
            E_USER_DEPRECATED => 'User deprecated',
        ];

        foreach ($errorLevels as $code => $name) {
            $this->assertSame($name, Error::getErrorNameByCode($code));
        }
    }
}
