<?php

declare(strict_types=1);

namespace SoureCode\Component\Traceable;

final class TraceContextHolder
{
    private ?TraceContextInterface $current = null;

    public function setCurrent(?TraceContextInterface $context): void
    {
        $this->current = $context;
    }

    public function getCurrent(): ?TraceContextInterface
    {
        return $this->current;
    }
}
