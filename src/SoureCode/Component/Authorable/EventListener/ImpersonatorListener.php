<?php

declare(strict_types=1);

namespace SoureCode\Component\Authorable\EventListener;

use Doctrine\ORM\Event\PrePersistEventArgs;
use SoureCode\Component\Authorable\Author\ImpersonatorProviderInterface;
use SoureCode\Component\Authorable\Metadata\AuthorableMetadataFactory;

final class ImpersonatorListener
{
    public function __construct(
        private readonly ImpersonatorProviderInterface $impersonatorProvider,
        private readonly AuthorableMetadataFactory $metadataFactory,
    ) {
    }

    public function prePersist(PrePersistEventArgs $args): void
    {
        $impersonator = $this->impersonatorProvider->getImpersonator();

        if ($impersonator === null) {
            return;
        }

        $entity = $args->getObject();
        $metadata = $this->metadataFactory->getMetadataFor($entity::class);

        foreach ($metadata->getImpersonatedBindings() as $binding) {
            $property = $binding->getProperty();

            if ($property->getValue($entity) === null) {
                $property->setValue($entity, $impersonator);
            }
        }
    }
}
