<?php

declare(strict_types=1);

namespace SoureCode\Component\Versionable\Tests\Fixtures;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'versionable_account_settings')]
class AccountSettings
{
    #[ORM\Id]
    #[ORM\Column(type: Types::INTEGER)]
    #[ORM\GeneratedValue]
    private int $id;

    #[ORM\OneToOne(inversedBy: 'settings', targetEntity: Account::class)]
    #[ORM\JoinColumn(nullable: false)]
    private Account $account;

    public function __construct(Account $account)
    {
        $this->account = $account;
        $account->setSettings($this);
    }

    public function getId(): int
    {
        return $this->id;
    }
}
