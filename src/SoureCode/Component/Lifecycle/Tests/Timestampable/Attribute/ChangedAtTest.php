<?php

declare(strict_types=1);

namespace SoureCode\Component\Lifecycle\Tests\Timestampable\Attribute;

use PHPUnit\Framework\TestCase;
use SoureCode\Component\Lifecycle\Attribute\ChangedAt;

final class ChangedAtTest extends TestCase
{
    public function testNormalizesSingleFieldToList(): void
    {
        $attribute = new ChangedAt(field: 'status');

        self::assertSame(['status'], $attribute->fields);
        self::assertNull($attribute->value);
    }

    public function testKeepsArrayOfFields(): void
    {
        $attribute = new ChangedAt(field: ['a', 'b']);

        self::assertSame(['a', 'b'], $attribute->fields);
    }

    public function testThrowsOnEmptyFieldList(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new ChangedAt(field: []);
    }

    public function testThrowsWhenValueMatcherCombinedWithMultipleFields(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new ChangedAt(field: ['a', 'b'], matchValue: true, value: 'x');
    }

    public function testNullValueWithMatchFlagIsValid(): void
    {
        $attribute = new ChangedAt(field: 'parent', matchValue: true, value: null);

        self::assertTrue($attribute->matchValue);
        self::assertNull($attribute->value);
    }
}
