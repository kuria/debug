<?php declare(strict_types=1);

namespace Kuria\Debug;

/**
 * Exception utilities
 */
abstract class Exception
{
    /**
     * Get name of the given exception
     */
    static function getName(\Throwable $exception): string
    {
        $name = null;

        if ($exception instanceof \ErrorException) {
            $name = Error::getName($exception->getSeverity());
        }

        if ($name === null) {
            $name = get_class($exception);
        }

        if (($code = $exception->getCode()) !== 0) {
            $name .= " ({$code})";
        }

        return $name;
    }

    /**
     * List exceptions starting from the given exception
     *
     * @return \Throwable[]
     */
    static function getChain(\Throwable $node): array
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
    static function joinChains(\Throwable ...$nodes): ?\Throwable
    {
        $lastNodeIndex = count($nodes) - 1;

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

        return $nodes[$lastNodeIndex] ?? null;
    }

    /**
     * Get textual information about an exception
     */
    static function render(\Throwable $exception, bool $renderTrace = true, bool $renderPrevious = false): string
    {
        $exceptions = $renderPrevious ? static::getChain($exception) : [$exception];
        $totalExceptions = count($exceptions);
        $lastException = $totalExceptions - 1;

        $output = '';
        for ($i = 0; $i < $totalExceptions; ++$i) {
            if ($i > 0 && $renderTrace) {
                $output .= "\n";
            }

            $output .= ($renderPrevious ? '[' . ($i + 1) . "/{$totalExceptions}] " : '')
                . static::getName($exceptions[$i])
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
}
