<?php declare(strict_types=1);

namespace Kuria\Debug;

use PHPUnit\Framework\TestCase;

class ExceptionTest extends TestCase
{
    function testShouldGetChain()
    {
        $c = new \Exception('C');
        $b = new \Exception('B', 0, $c);
        $a = new \Exception('A', 0, $b);

        $this->assertSame([$a, $b, $c], Exception::getChain($a));
        $this->assertSame([$b, $c], Exception::getChain($b));
        $this->assertSame([$c], Exception::getChain($c));
    }

    function testShouldJoinChains()
    {
        $c = new \Exception('C');
        $b = new \Exception('B', 0, $c);
        $a = new \Exception('A', 0, $b);

        $z = new \Exception('Z');
        $y = new \Exception('Y', 0, $z);
        $x = new \Exception('X', 0, $y);

        $result = Exception::joinChains($a, $x);

        $this->assertSame($x, $result);
        $this->assertSame($y, $x->getPrevious());
        $this->assertSame($z, $y->getPrevious());
        $this->assertSame($a, $z->getPrevious());
        $this->assertSame($b, $a->getPrevious());
        $this->assertSame($c, $b->getPrevious());
        $this->assertNull($c->getPrevious());
    }

    function testShouldJoinChainsWithoutPrevious()
    {
        $a = new \Exception();
        $b = new \Exception();

        $result = Exception::joinChains($a, $b);

        $this->assertSame($b, $result);
        $this->assertSame($a, $b->getPrevious());
    }

    function testShouldJoinChainsWithDifferentExceptionHierarchies()
    {
        $c = new \Exception('C');
        $b = new \Exception('B', 0, $c);
        $a = new \Exception('A', 0, $b);

        $z = new \Error('Z');
        $y = new \Error('Y', 0, $z);
        $x = new \Error('X', 0, $y);

        $result = Exception::joinChains($a, $x);

        $this->assertSame($x, $result);
        $this->assertSame($y, $x->getPrevious());
        $this->assertSame($z, $y->getPrevious());
        $this->assertSame($a, $z->getPrevious());
        $this->assertSame($b, $a->getPrevious());
        $this->assertSame($c, $b->getPrevious());
        $this->assertNull($c->getPrevious());
    }

    function testShouldRender()
    {
        $testException = new \Exception(
            'Test exception',
            0,
            new \Exception('Test previous exception')
        );

        // default (trace = on, previous = off)
        $output = Exception::render($testException);

        $this->assertContains('Test exception', $output);
        $this->assertNotContains('Test previous exception', $output);
        $this->assertContains('{main}', $output);
        $this->assertSame($output, trim($output));

        // trace = on, previous = on
        $output = Exception::render($testException, true, true);
        $this->assertContains('Test exception', $output);
        $this->assertContains('Test previous exception', $output);
        $this->assertContains('{main}', $output);
        $this->assertSame($output, trim($output));

        // trace = off, previous = on
        $output = Exception::render($testException, false, true);
        $this->assertContains('Test exception', $output);
        $this->assertContains('Test previous exception', $output);
        $this->assertNotContains('{main}', $output);
        $this->assertSame($output, trim($output));

        // trace = off, previous = off
        $output = Exception::render($testException, false, false);
        $this->assertContains('Test exception', $output);
        $this->assertNotContains('Test previous exception', $output);
        $this->assertNotContains('{main}', $output);
        $this->assertSame($output, trim($output));
    }

    function testShouldGetName()
    {
        $this->assertSame('Exception', Exception::getName(new \Exception('Test exception')));
        $this->assertSame('Exception (123)', Exception::getName(new \Exception('Test exception', 123)));
        $this->assertSame('E_ERROR', Exception::getName(new \ErrorException('Test error', 0, E_ERROR)));
        $this->assertSame('E_ERROR (456)', Exception::getName(new \ErrorException('Test error', 456, E_ERROR)));
        $this->assertSame('ErrorException', Exception::getName(new \ErrorException('Test error', 0, 123456789)));
        $this->assertSame('ErrorException (789)', Exception::getName(new \ErrorException('Test error', 789, 123456789)));
    }
}
