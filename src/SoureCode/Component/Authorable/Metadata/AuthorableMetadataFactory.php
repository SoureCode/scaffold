<?php

declare(strict_types=1);

namespace SoureCode\Component\Authorable\Metadata;

use SoureCode\Component\Authorable\Attribute\ChangedBy;
use SoureCode\Component\Authorable\Attribute\CreatedBy;
use SoureCode\Component\Authorable\Attribute\UpdatedBy;
use SoureCode\Component\DoctrineExtensions\Metadata\BehaviorMetadataFactoryInterface;

final class AuthorableMetadataFactory implements BehaviorMetadataFactoryInterface
{
    /**
     * @var array<class-string, AuthorableMetadata>
     */
    private array $cache = [];

    /**
     * @param class-string $class
     */
    public function getMetadataFor(string $class): AuthorableMetadata
    {
        if (isset($this->cache[$class])) {
            return $this->cache[$class];
        }

        $created = [];
        $updated = [];
        $changed = [];
        $reflection = new \ReflectionClass($class);

        do {
            foreach ($reflection->getProperties() as $property) {
                foreach ($property->getAttributes(CreatedBy::class) as $attribute) {
                    $attribute->newInstance();
                    $created[] = new CreatedByBinding($property);
                }

                foreach ($property->getAttributes(UpdatedBy::class) as $attribute) {
                    $instance = $attribute->newInstance();
                    $updated[] = new UpdatedByBinding($property, $instance->nullable);
                }

                foreach ($property->getAttributes(ChangedBy::class) as $attribute) {
                    $instance = $attribute->newInstance();
                    $changed[] = new ChangedByBinding($property, $instance->fields, $instance->matchValue, $instance->value);
                }
            }

            $reflection = $reflection->getParentClass();
        } while ($reflection !== false);

        return $this->cache[$class] = new AuthorableMetadata($created, $updated, $changed);
    }
}
