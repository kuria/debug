<?php declare(strict_types=1);

namespace Kuria\Debug;

/**
 * Output utilities
 */
abstract class Output
{
    /**
     * Attempt to clean output buffers
     */
    static function cleanBuffers(?int $targetLevel = null, bool $catchExceptions = false): bool
    {
        $level = 0;
        $stopLevel = $targetLevel ?? 0;

        foreach (self::iterateBufferLevels(PHP_OUTPUT_HANDLER_CLEANABLE) as $level) {
            if ($level <= $stopLevel) {
                break;
            }

            try {
                ob_end_clean();
            } catch (\Throwable $e) {
                if (!$catchExceptions) {
                    throw $e;
                }
            }
        }

        return $targetLevel === null || $level <= $targetLevel;
    }

    /**
     * Attempt to capture output buffers
     */
    static function captureBuffers(?int $targetLevel = null, bool $catchExceptions = false): string
    {
        $output = '';
        $stopLevel = $targetLevel ?? 0;

        foreach (self::iterateBufferLevels(PHP_OUTPUT_HANDLER_CLEANABLE) as $level) {
            if ($level <= $stopLevel) {
                break;
            }

            try {
                $output = ob_get_clean() . $output;
            } catch (\Throwable $e) {
                if (!$catchExceptions) {
                    throw $e;
                }
            }
        }

        return $output;
    }

    /**
     * Attempt to replace headers
     */
    static function replaceHeaders(array $newHeaders): bool
    {
        if (!headers_sent()) {
            header_remove();

            foreach ($newHeaders as $header) {
                header($header);
            }

            return true;
        }

        return false;
    }

    /**
     * @return iterable<int>
     */
    private static function iterateBufferLevels(int $requiredFlags): iterable
    {
        $buffers = ob_get_status(true);

        for ($i = count($buffers) - 1; $i >= 0 && ($buffers[$i]['flags'] & $requiredFlags) === $requiredFlags; --$i) {
            yield $i + 1;
        }
    }
}
