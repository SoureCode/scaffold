<?php

declare(strict_types=1);

namespace SoureCode\Component\Lifecycle\Metadata;

use SoureCode\Component\Lifecycle\Attribute\ChangedBy;
use SoureCode\Component\Lifecycle\Attribute\CreatedBy;
use SoureCode\Component\Lifecycle\Attribute\DeletedBy;
use SoureCode\Component\Lifecycle\Attribute\ImpersonatedBy;
use SoureCode\Component\Lifecycle\Attribute\UpdatedBy;
use SoureCode\Component\DoctrineExtensions\Metadata\AbstractBehaviorMetadataFactory;
use SoureCode\Component\DoctrineExtensions\Metadata\BehaviorMetadataFactoryInterface;

final class AuthorableMetadataFactory extends AbstractBehaviorMetadataFactory implements BehaviorMetadataFactoryInterface
{

    /**
     * @param class-string $class
     */
    public function getMetadataFor(string $class): AuthorableMetadata
    {
        if (isset($this->cache[$class])) {
            /** @var AuthorableMetadata */
            return $this->cache[$class];
        }

        $created = [];
        $updated = [];
        $changed = [];
        $deleted = [];
        $impersonated = [];

        $this->walkHierarchy(
            $class,
            static function (\ReflectionProperty $property) use (&$created, &$updated, &$changed, &$deleted, &$impersonated): void {
                if ($property->getAttributes(CreatedBy::class) !== []) {
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

                if ($property->getAttributes(DeletedBy::class) !== []) {
                    $deleted[] = new DeletedByBinding($property);
                }

                if ($property->getAttributes(ImpersonatedBy::class) !== []) {
                    $impersonated[] = new ImpersonatedByBinding($property);
                }
            },
        );

        $metadata = new AuthorableMetadata($created, $updated, $changed, $deleted, $impersonated);
        $this->cache[$class] = $metadata;

        return $metadata;
    }
}
