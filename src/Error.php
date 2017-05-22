<?php


namespace Kuria\Debug;

/**
 * PHP error & exception utilities
 *
 * @author ShiraNai7 <shira.cz>
 */
class Error
{
    /** @var string[] */
    private static $errorCodes = array(
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
    );

    /**
     * This is a static class
     */
    private function __construct()
    {
    }

    /**
     * Get PHP error name by its code
     *
     * @param int $code PHP error code
     * @return string|null
     */
    public static function getErrorNameByCode($code)
    {
        if (isset(static::$errorCodes[$code])) {
            return static::$errorCodes[$code];
        }
    }

    /**
     * List exceptions starting from the given exception
     *
     * @param \Throwable|\Exception $node the current exception instance
     * @return \Exception[]|\Throwable[]
     */
    public static function getExceptionChain($node)
    {
        $chain = array();
        $hashMap = array();

        while (null !== $node && !isset($hashMap[$hash = spl_object_hash($node)])) {
            $chain[] = $node;
            $hashMap[$hash] = true;
            $node = $node->getPrevious();
        }

        return $chain;
    }

    /**
     * Join exception chains together
     *
     * @param \Throwable|\Exception $exception1,...
     * @return \Throwable|\Exception|null the last exception passed
     */
    public static function joinExceptionChains()
    {
        /** @var \Exception[] $nodes */
        $nodes = func_get_args();

        $lastNodeIndex = sizeof($nodes) - 1;

        if ($lastNodeIndex > 0) {
            // iterate over all but the last node
            for ($i = 0; $i < $lastNodeIndex; ++$i) {
                // find initial node of the next chain
                $initialNode = $nodes[$i + 1];
                $hashMap = array();
                while (($previousNode = $initialNode->getPrevious()) && !isset($hashMap[$hash = spl_object_hash($previousNode)])) {
                    $initialNode = $previousNode;
                }

                // connect end of the current chain (= current node)
                // to the initial node of the next chain
                $previousProperty = new \ReflectionProperty(
                    ($parents = class_parents($initialNode)) ? end($parents) : $initialNode,
                    'previous'
                );

                $previousProperty->setAccessible(true);
                $previousProperty->setValue($initialNode, $nodes[$i]);
            }
        }

        return $lastNodeIndex >= 0
            ? $nodes[$lastNodeIndex]
            : null;
    }

    /**
     * Get textual information about an exception
     *
     * @param \Throwable|\Exception $exception      the exception instance
     * @param bool                  $renderTrace    render exception traces 1/0
     * @param bool                  $renderPrevious render previous exceptions 1/0
     * @return string
     */
    public static function renderException($exception, $renderTrace = true, $renderPrevious = false)
    {
        $exceptions = $renderPrevious
            ? static::getExceptionChain($exception)
            : array($exception);
        $totalExceptions = sizeof($exceptions);
        $lastException = $totalExceptions - 1;

        $output = '';
        for ($i = 0; $i < $totalExceptions; ++$i) {
            if ($i > 0 && $renderTrace) {
                $output .= "\n";
            }

            $output .= ($renderPrevious ? '[' . ($i + 1) . "/{$totalExceptions}] " : '')
                . static::getExceptionName($exceptions[$i])
                . ': ' . ($exceptions[$i]->getMessage() ?: '<no message>')
                . " in {$exceptions[$i]->getFile()} on line {$exceptions[$i]->getLine()}";

            if ($renderTrace || $i < $lastException) {
                $output .= "\n";
            }

            if ($renderTrace) {
                $output .= $exceptions[$i]->getTraceAsString();

                if ($i < $lastException) {
                    $output .= "\n";
                }
            }
        }

        return $output;
    }

    /**
     * Get name of the given exception
     *
     * @param \Throwable|\Exception $exception
     * @return string
     */
    public static function getExceptionName($exception)
    {
        $name = null;

        if ($exception instanceof \ErrorException) {
            $name = static::getErrorNameByCode($exception->getSeverity());
        }

        if (null === $name) {
            $name = get_class($exception);
        }

        if (0 !== ($code = $exception->getCode())) {
            $name .= " ({$code})";
        }

        return $name;
    }
}
