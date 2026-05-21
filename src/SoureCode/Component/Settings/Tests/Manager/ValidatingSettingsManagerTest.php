<?php

declare(strict_types=1);

namespace SoureCode\Component\Settings\Tests\Manager;

use PHPUnit\Framework\TestCase;
use SoureCode\Component\Settings\Manager\InMemorySettingsManager;
use SoureCode\Component\Settings\Manager\ValidatingSettingsManager;
use SoureCode\Component\Settings\Validation\ArraySettingsSchema;

final class ValidatingSettingsManagerTest extends TestCase
{
    public function testSetAcceptsValueMatchingSchema(): void
    {
        $manager = new ValidatingSettingsManager(
            new InMemorySettingsManager(),
            new ArraySettingsSchema(['site.title' => ['type' => 'string']]),
        );

        $manager->set('site.title', 'Hello');
        self::assertSame('Hello', $manager->get('site.title'));
    }

    public function testSetRejectsMismatchedType(): void
    {
        $manager = new ValidatingSettingsManager(
            new InMemorySettingsManager(),
            new ArraySettingsSchema(['site.title' => ['type' => 'string']]),
        );

        $this->expectException(\InvalidArgumentException::class);
        $manager->set('site.title', 42);
    }

    public function testUnknownKeysPassThroughBecauseSchemaIsOpenWorld(): void
    {
        $manager = new ValidatingSettingsManager(
            new InMemorySettingsManager(),
            new ArraySettingsSchema(['site.title' => ['type' => 'string']]),
        );

        $manager->set('site.untracked', 42);
        self::assertSame(42, $manager->get('site.untracked'));
    }
}
