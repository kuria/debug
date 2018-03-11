<?php declare(strict_types=1);

namespace Kuria\Debug;

/**
 * Output utilities
 */
abstract class Output
{
    /**
     * Attempt to clean output buffers
     *
     * Some built-in or non-removable buffers cannot be cleaned.
     */
    static function cleanBuffers(?int $targetLevel = null, bool $catchExceptions = false): bool
    {
        if ($targetLevel === null) {
            $targetLevel = self::determineMinimalBufferLevel();
        }

        if (($bufferLevel = ob_get_level()) > $targetLevel) {
            do {
                $lastBufferLevel = $bufferLevel;

                try {
                    ob_end_clean();
                } catch (\Throwable $e) {
                    if (!$catchExceptions) {
                        throw $e;
                    }
                }

                $bufferLevel = ob_get_level();
            } while ($bufferLevel > $targetLevel && $bufferLevel < $lastBufferLevel);
        }

        return $bufferLevel <= $targetLevel;
    }

    /**
     * Attempt to capture output buffers
     *
     * Some built-in or non-removable buffers cannot be captured.
     */
    static function captureBuffers(?int $targetLevel = null, bool $catchExceptions = false): string
    {
        if ($targetLevel === null) {
            $targetLevel = self::determineMinimalBufferLevel();
        }

        $buffer = '';

        if (($bufferLevel = ob_get_level()) > $targetLevel) {
            do {
                $lastBufferLevel = $bufferLevel;

                try {
                    $buffer = ob_get_clean() . $buffer;
                } catch (\Throwable $e) {
                    if (!$catchExceptions) {
                        throw $e;
                    }
                }

                $bufferLevel = ob_get_level();
            } while ($bufferLevel > $targetLevel && $bufferLevel < $lastBufferLevel);
        }

        return $buffer;
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

    private static function determineMinimalBufferLevel(): int
    {
        if (!empty(ini_get('output_buffer')) || !empty(ini_get('output_handler'))) {
            return 1;
        } else {
            return 0;
        }
    }
}
