<?php

declare(strict_types=1);

namespace SoureCode\Component\Traceable\Tests;

use PHPUnit\Framework\TestCase;
use SoureCode\Component\Traceable\TraceContext;
use SoureCode\Component\Traceable\TraceContextFactory;
use SoureCode\Component\Traceable\TraceContextInterface;
use Symfony\Component\Uid\Ulid;

final class TraceContextTest extends TestCase
{
    public function testContextExposesItsUlid(): void
    {
        $ulid = new Ulid();
        $context = new TraceContext($ulid);

        self::assertSame($ulid, $context->getId());
    }

    public function testContextImplementsInterface(): void
    {
        self::assertInstanceOf(TraceContextInterface::class, new TraceContext(new Ulid()));
    }

    public function testFactoryGeneratesUlidWhenNoneProvided(): void
    {
        $context = (new TraceContextFactory())->create();

        self::assertInstanceOf(Ulid::class, $context->getId());
    }

    public function testFactoryUsesProvidedUlid(): void
    {
        $ulid = new Ulid();
        $context = (new TraceContextFactory())->create($ulid);

        self::assertSame($ulid, $context->getId());
    }

    public function testFactoryReturnsFreshInstancesPerCall(): void
    {
        $factory = new TraceContextFactory();

        self::assertNotSame($factory->create(), $factory->create());
    }
}
