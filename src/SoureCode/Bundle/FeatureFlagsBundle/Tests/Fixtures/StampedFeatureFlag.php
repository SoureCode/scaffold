<?php

declare(strict_types=1);

namespace SoureCode\Bundle\FeatureFlagsBundle\Tests\Fixtures;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use SoureCode\Bundle\AuthorableBundle\Doctrine\CreatedByTrait;
use SoureCode\Bundle\AuthorableBundle\Doctrine\UpdatedByTrait;
use SoureCode\Bundle\TimestampableBundle\Doctrine\CreatedAtTrait;
use SoureCode\Bundle\TimestampableBundle\Doctrine\UpdatedAtTrait;
use SoureCode\Component\FeatureFlags\Model\FeatureFlagInterface;

#[ORM\Entity]
#[ORM\Table(name: 'stamped_feature_flags')]
class StampedFeatureFlag implements FeatureFlagInterface
{
    use CreatedAtTrait;
    use UpdatedAtTrait;
    use CreatedByTrait;
    use UpdatedByTrait;

    #[ORM\Id]
    #[ORM\Column(type: Types::STRING, length: 191)]
    private string $name;

    #[ORM\Column(type: Types::BOOLEAN)]
    private bool $enabled = false;

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): void
    {
        $this->name = $name;
    }

    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    public function setEnabled(bool $enabled): void
    {
        $this->enabled = $enabled;
    }
}
