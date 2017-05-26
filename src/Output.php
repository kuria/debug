<?php

namespace Kuria\Debug;

/**
 * Output utilities
 *
 * @author ShiraNai7 <shira.cz>
 */
class Output
{
    /**
     * This is a static class
     */
    private function __construct()
    {
    }

    /**
     * Attempt to clean output buffers
     *
     * Some built-in or non-removable buffers cannot be cleaned.
     *
     * @param int|null $targetLevel     target buffer level or null (= all buffers)
     * @param bool     $capture         capture and return buffer contents 1/0
     * @param bool     $catchExceptions catch buffer exceptions 1/0 (false = rethrow)
     * @return string|bool captured buffer if $capture = true, boolean status otherwise
     */
    public static function cleanBuffers($targetLevel = null, $capture = false, $catchExceptions = false)
    {
        if (null === $targetLevel) {
            $targetLevel = 0;

            if ('' != ini_get('output_buffer') || '' != ini_get('output_handler')) {
                ++$targetLevel;
            }
        }

        if ($capture) {
            $buffer = '';
        }

        if (($bufferLevel = ob_get_level()) > $targetLevel) {
            $cleanFunction = $capture ? 'ob_get_clean' : 'ob_end_clean';

            do {
                $lastBufferLevel = $bufferLevel;

                $e = null;
                try {
                    $result = $cleanFunction();
                } catch (\Exception $e) {
                } catch (\Throwable $e) {
                }

                $bufferLevel = ob_get_level();

                if (null === $e) {
                    if ($capture) {
                        $buffer = $result . $buffer;
                    }
                } elseif (!$catchExceptions) {
                    throw $e;
                }
            } while ($bufferLevel > $targetLevel && $bufferLevel < $lastBufferLevel);
        }

        return $capture ? $buffer : $bufferLevel <= $targetLevel;
    }

    /**
     * Attempt to replace headers
     *
     * @param string[] $newHeaders list of new headers to set
     * @return bool
     */
    public static function replaceHeaders(array $newHeaders)
    {
        if (!headers_sent()) {
            header_remove();

            for ($i = 0; isset($newHeaders[$i]); ++$i) {
                header($newHeaders[$i]);
            }

            return true;
        }

        return false;
    }
}
