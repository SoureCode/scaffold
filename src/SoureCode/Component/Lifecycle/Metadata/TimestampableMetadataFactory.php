<?php

declare(strict_types=1);

namespace SoureCode\Component\Lifecycle\Metadata;

use SoureCode\Component\DoctrineExtensions\Metadata\AbstractBehaviorMetadataFactory;
use SoureCode\Component\DoctrineExtensions\Metadata\BehaviorMetadataFactoryInterface;
use SoureCode\Component\Lifecycle\Attribute\ChangedAt;
use SoureCode\Component\Lifecycle\Attribute\CreatedAt;
use SoureCode\Component\Lifecycle\Attribute\DeletedAt;
use SoureCode\Component\Lifecycle\Attribute\UpdatedAt;

final class TimestampableMetadataFactory extends AbstractBehaviorMetadataFactory implements BehaviorMetadataFactoryInterface
{

    /**
     * @param class-string $class
     */
    public function getMetadataFor(string $class): TimestampableMetadata
    {
        if (isset($this->cache[$class])) {
            /** @var TimestampableMetadata */
            return $this->cache[$class];
        }

        $created = [];
        $updated = [];
        $changed = [];
        $deleted = [];

        $this->walkHierarchy(
            $class,
            static function (\ReflectionProperty $property) use (&$created, &$updated, &$changed, &$deleted): void {
                foreach ($property->getAttributes(CreatedAt::class) as $attribute) {
                    $instance = $attribute->newInstance();
                    $created[] = new CreatedAtBinding($property, $instance->type);
                }

                foreach ($property->getAttributes(UpdatedAt::class) as $attribute) {
                    $instance = $attribute->newInstance();
                    $updated[] = new UpdatedAtBinding($property, $instance->type, $instance->nullable);
                }

                foreach ($property->getAttributes(ChangedAt::class) as $attribute) {
                    $instance = $attribute->newInstance();
                    $changed[] = new ChangedAtBinding($property, $instance->fields, $instance->matchValue, $instance->value, $instance->type);
                }

                foreach ($property->getAttributes(DeletedAt::class) as $attribute) {
                    $instance = $attribute->newInstance();
                    $deleted[] = new DeletedAtBinding($property, $instance->type);
                }
            },
        );

        $metadata = new TimestampableMetadata($created, $updated, $changed, $deleted);
        $this->cache[$class] = $metadata;

        return $metadata;
    }
}
