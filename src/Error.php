<?php declare(strict_types=1);

namespace Kuria\Debug;

/**
 * PHP error & exception utilities
 */
abstract class Error
{
    /** @var string[] */
    private const CODES = [
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

    /**
     * Get PHP error name by its code
     */
    static function getErrorNameByCode(int $code): ?string
    {
        return self::CODES[$code] ?? null;
    }

    /**
     * List exceptions starting from the given exception
     *
     * @return \Throwable[]
     */
    static function getExceptionChain(\Throwable $node): array
    {
        $chain = [];
        $hashMap = [];

        while ($node !== null && !isset($hashMap[$hash = spl_object_hash($node)])) {
            $chain[] = $node;
            $hashMap[$hash] = true;
            $node = $node->getPrevious();
        }

        return $chain;
    }

    /**
     * Join exception chains together
     *
     * Returns the last exception or NULL if no exceptions were given.
     */
    static function joinExceptionChains(\Throwable ...$nodes): ?\Throwable
    {
        $lastNodeIndex = sizeof($nodes) - 1;

        if ($lastNodeIndex > 0) {
            // iterate over all but the last node
            for ($i = 0; $i < $lastNodeIndex; ++$i) {
                // find initial node of the next chain
                $initialNode = $nodes[$i + 1];
                $hashMap = [];
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
     */
    static function renderException(\Throwable $exception, bool $renderTrace = true, bool $renderPrevious = false): string
    {
        $exceptions = $renderPrevious ? static::getExceptionChain($exception) : [$exception];
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
     */
    static function getExceptionName(\Throwable $exception): string
    {
        $name = null;

        if ($exception instanceof \ErrorException) {
            $name = static::getErrorNameByCode($exception->getSeverity());
        }

        if ($name === null) {
            $name = get_class($exception);
        }

        if (($code = $exception->getCode()) !== 0) {
            $name .= " ({$code})";
        }

        return $name;
    }
}
