<?php declare(strict_types=1);

namespace Kuria\Debug;

use Kuria\DevMeta\Test;

class ErrorTest extends Test
{
    /**
     * @dataProvider provideErrorConstants
     */
    function testShouldGetName(string $constant)
    {
        $this->assertSame($constant, Error::getName(constant($constant)));
    }

    function provideErrorConstants(): array
    {
        return [
            ['E_ERROR'],
            ['E_WARNING'],
            ['E_PARSE'],
            ['E_NOTICE'],
            ['E_CORE_ERROR'],
            ['E_CORE_WARNING'],
            ['E_COMPILE_ERROR'],
            ['E_COMPILE_WARNING'],
            ['E_USER_ERROR'],
            ['E_USER_WARNING'],
            ['E_USER_NOTICE'],
            ['E_STRICT'],
            ['E_RECOVERABLE_ERROR'],
            ['E_DEPRECATED'],
            ['E_USER_DEPRECATED'],
        ];
    }
}
