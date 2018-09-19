<?php declare(strict_types=1);

namespace Kuria\Debug;

use Kuria\DevMeta\Test;

class OutputTest extends Test
{
    function testShouldCleanBuffers()
    {
        $initialBufferLevel = ob_get_level();

        $this->prepareTestOutputBuffers();

        $this->assertTrue(Output::cleanBuffers($initialBufferLevel));
        $this->assertSame($initialBufferLevel, ob_get_level());
    }

    function testShouldCaptureBuffers()
    {
        $initialBufferLevel = ob_get_level();

        $this->prepareTestOutputBuffers();

        $this->assertSame('abcd', Output::captureBuffers($initialBufferLevel));
        $this->assertSame($initialBufferLevel, ob_get_level());
    }

    function testShouldCleanBuffersWithCaughtException()
    {
        $initialBufferLevel = ob_get_level();

        $this->prepareExceptionThrowingOutputBuffers();

        $this->assertTrue(Output::cleanBuffers($initialBufferLevel, true));
        $this->assertSame($initialBufferLevel, ob_get_level());
    }

    function testShouldCaptureBuffersWithCaughtException()
    {
        $initialBufferLevel = ob_get_level();

        $this->prepareExceptionThrowingOutputBuffers();

        $this->assertSame('ac', Output::captureBuffers($initialBufferLevel, true)); // b gets discarded
        $this->assertSame($initialBufferLevel, ob_get_level());
    }

    function testShouldCleanBuffersWithRethrownException()
    {
        $initialBufferLevel = ob_get_level();
        $bufferException = new \Exception();

        $this->prepareExceptionThrowingOutputBuffers($bufferException);

        $e = null;

        try {
            Output::cleanBuffers($initialBufferLevel);
        } catch (\Throwable $e) {
            $this->assertSame($bufferException, $e);
        }

        $this->assertTrue(Output::cleanBuffers($initialBufferLevel, true));

        if ($e === null) {
            $this->fail('The buffer exception was not rethrown');
        }

        $this->assertSame($initialBufferLevel, ob_get_level());
    }

    function testShouldCaptureBuffersWithRethrownException()
    {
        $initialBufferLevel = ob_get_level();
        $bufferException = new \Exception();

        $this->prepareExceptionThrowingOutputBuffers($bufferException);

        $e = null;

        try {
            Output::cleanBuffers($initialBufferLevel);
        } catch (\Throwable $e) {
            $this->assertSame($bufferException, $e);
        }

        $this->assertSame('a', Output::captureBuffers($initialBufferLevel));

        if ($e === null) {
            $this->fail('The buffer exception was not rethrown');
        }

        $this->assertSame($initialBufferLevel, ob_get_level());
    }

    function testShouldCleanBuffersAboveCurrentLevel()
    {
        $this->assertTrue(Output::cleanBuffers(ob_get_level() + 1));
        $this->assertSame('', Output::captureBuffers(ob_get_level() + 1));
    }

    private function prepareTestOutputBuffers()
    {
        for ($l = 'a'; $l <= 'd'; ++$l) {
            ob_start();
            echo $l;
        }
    }

    private function prepareExceptionThrowingOutputBuffers(?\Throwable $exception = null): void
    {
        ob_start();
        echo 'a';

        ob_start(function ($buffer, $phase) use ($exception) {
            if ((PHP_OUTPUT_HANDLER_END & $phase) !== (0)) {
                throw $exception ?: new \Exception();
            }
        });

        echo 'b';

        ob_start();
        echo 'c';
    }
}
