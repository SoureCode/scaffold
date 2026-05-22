<?php

declare(strict_types=1);

namespace SoureCode\Component\Lifecycle;

use SoureCode\Component\Lifecycle\Author\AuthorProviderInterface;
use SoureCode\Component\Lifecycle\Metadata\AuthorableMetadataFactory;
use SoureCode\Component\Lifecycle\DeletionMarkerProviderInterface;

final class AuthorableDeletionMarkerProvider implements DeletionMarkerProviderInterface
{
    public function __construct(
        private readonly AuthorableMetadataFactory $metadataFactory,
        private readonly ?AuthorProviderInterface $authorProvider = null,
    ) {
    }

    public function fillDeletionMarkers(object $entity): void
    {
        $author = $this->authorProvider?->getCurrentAuthor();

        if ($author === null) {
            return;
        }

        foreach ($this->metadataFactory->getMetadataFor($entity::class)->getDeletedBindings() as $binding) {
            $binding->getProperty()->setValue($entity, $author);
        }
    }

    public function clearDeletionMarkers(object $entity): void
    {
        foreach ($this->metadataFactory->getMetadataFor($entity::class)->getDeletedBindings() as $binding) {
            $binding->getProperty()->setValue($entity, null);
        }
    }
}
