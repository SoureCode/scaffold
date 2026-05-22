<?php

declare(strict_types=1);

namespace SoureCode\Bundle\SettingsBundle\Tests\Fixtures;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use SoureCode\Bundle\LifecycleBundle\Doctrine\CreatedByTrait;
use SoureCode\Bundle\LifecycleBundle\Doctrine\UpdatedByTrait;
use SoureCode\Bundle\LifecycleBundle\Doctrine\CreatedAtTrait;
use SoureCode\Bundle\LifecycleBundle\Doctrine\UpdatedAtTrait;
use SoureCode\Component\Settings\Model\SettingInterface;

#[ORM\Entity]
#[ORM\Table(name: 'stamped_settings')]
class StampedSetting implements SettingInterface
{
    use CreatedAtTrait;
    use UpdatedAtTrait;
    use CreatedByTrait;
    use UpdatedByTrait;

    #[ORM\Id]
    #[ORM\Column(type: Types::STRING, length: 191)]
    private string $key;

    #[ORM\Column(type: Types::JSON, nullable: true)]
    private mixed $value = null;

    public function getKey(): string
    {
        return $this->key;
    }

    public function setKey(string $key): void
    {
        $this->key = $key;
    }

    public function getValue(): mixed
    {
        return $this->value;
    }

    public function setValue(mixed $value): void
    {
        $this->value = $value;
    }
}
