<?php

declare(strict_types=1);

namespace SoureCode\Component\Timestampable\Metadata;

use SoureCode\Component\DoctrineExtensions\Metadata\BehaviorMetadataFactoryInterface;
use SoureCode\Component\Timestampable\Attribute\ChangedAt;
use SoureCode\Component\Timestampable\Attribute\CreatedAt;
use SoureCode\Component\Timestampable\Attribute\DeletedAt;
use SoureCode\Component\Timestampable\Attribute\UpdatedAt;

final class TimestampableMetadataFactory implements BehaviorMetadataFactoryInterface
{
    /**
     * @var array<class-string, TimestampableMetadata>
     */
    private array $cache = [];

    /**
     * @param class-string $class
     */
    public function getMetadataFor(string $class): TimestampableMetadata
    {
        if (isset($this->cache[$class])) {
            return $this->cache[$class];
        }

        $created = [];
        $updated = [];
        $changed = [];
        $deleted = [];
        $reflection = new \ReflectionClass($class);

        do {
            foreach ($reflection->getProperties() as $property) {
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
            }

            $reflection = $reflection->getParentClass();
        } while ($reflection !== false);

        return $this->cache[$class] = new TimestampableMetadata($created, $updated, $changed, $deleted);
    }
}
