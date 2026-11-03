<?php

declare(strict_types=1);

namespace PortLibs\Pandoc;

use Closure;
use InvalidArgumentException;

/** @internal Adapter while existing reader transforms move into processors. */
final class PdfCallableSemanticRecordProcessor implements PdfSemanticRecordProcessor
{
    /** @var Closure(array):array */
    private readonly Closure $processor;

    public function __construct(private readonly string $processorName, callable $processor)
    {
        if (trim($processorName) === '') {
            throw new InvalidArgumentException('A PDF semantic processor requires a name.');
        }
        $this->processor = Closure::fromCallable($processor);
    }

    public function name(): string
    {
        return $this->processorName;
    }

    public function process(array $records): array
    {
        return ($this->processor)($records);
    }
}
