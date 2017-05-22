<?php

namespace Kuria\Debug;

class OutputTest extends \PHPUnit_Framework_TestCase
{
    public function testCleanBuffers()
    {
        $bufferLevel = ob_get_level();
        
        for ($i = 0; $i < 5; ++$i) {
            ob_start();
            echo $i;
        }
        
        $buffer = Output::cleanBuffers($bufferLevel, true);

        $this->assertSame('01234', $buffer);
        $this->assertSame($bufferLevel, ob_get_level());
    }

    public function testCleanBuffersWithCaughtException()
    {
        $initialBufferLevel = ob_get_level();

        ob_start();
        echo 'a';

        ob_start(function ($buffer, $phase) {
            if (0 !== ($phase & PHP_OUTPUT_HANDLER_CLEAN)) {
                throw new \Exception('Test buffer exception');
            }
        });
        echo 'b';

        ob_start();
        echo 'c';

        $buffer = Output::cleanBuffers($initialBufferLevel, true, true);

        $this->assertSame('ac', $buffer); // b gets discarded
        $this->assertSame($initialBufferLevel, ob_get_level());
    }

    public function testCleanBuffersWithRethrownException()
    {
        $bufferLevel = ob_get_level();
        $testBufferException = new \Exception('Test buffer exception');

        ob_start();
        echo 'lorem';

        ob_start(function ($buffer, $phase) use ($testBufferException) {
            if (0 !== (PHP_OUTPUT_HANDLER_END & $phase)) {
                throw $testBufferException;
            }
        });
        
        echo 'ipsum';

        ob_start();
        echo 'dolor';
        
        $e = null;

        try {
            Output::cleanBuffers($bufferLevel);
        } catch (\Exception $e) {
            $this->assertSame($testBufferException, $e);
        }

        Output::cleanBuffers($bufferLevel, false, true);

        if (null === $e) {
            $this->fail('The buffer exception was not rethrown');
        }

        $this->assertSame($bufferLevel, ob_get_level());
    }

    public function testCleanBuffersAboveCurrentLevel()
    {
        $this->assertTrue(Output::cleanBuffers(ob_get_level() + 1));
        $this->assertSame('', Output::cleanBuffers(ob_get_level() + 1, true));
    }
}
