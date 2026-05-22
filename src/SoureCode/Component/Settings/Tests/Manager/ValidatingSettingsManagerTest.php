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

    public function testHasDelegatesToInnerManager(): void
    {
        $inner = new InMemorySettingsManager();
        $inner->set('site.title', 'Hello');

        $manager = new ValidatingSettingsManager(
            $inner,
            new ArraySettingsSchema(['site.title' => ['type' => 'string']]),
        );

        self::assertTrue($manager->has('site.title'));
        self::assertFalse($manager->has('missing.key'));
    }

    public function testRemoveDelegatesToInnerManagerWithoutSchemaCheck(): void
    {
        $inner = new InMemorySettingsManager();
        $inner->set('site.title', 'Hello');
        $inner->set('untracked', 42);

        $manager = new ValidatingSettingsManager(
            $inner,
            new ArraySettingsSchema(['site.title' => ['type' => 'string']]),
        );

        $manager->remove('site.title');
        $manager->remove('untracked');

        self::assertFalse($inner->has('site.title'));
        self::assertFalse($inner->has('untracked'));
    }

    public function testAllReturnsCollectionFromInnerManager(): void
    {
        $inner = new InMemorySettingsManager();
        $inner->set('a', 1);
        $inner->set('b', 'two');

        $manager = new ValidatingSettingsManager(
            $inner,
            new ArraySettingsSchema([]),
        );

        $all = $manager->all();

        self::assertSame($inner->all(), $all);
        self::assertCount(2, $all);
    }
}
