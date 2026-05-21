<?php

declare(strict_types=1);

namespace SoureCode\Bundle\FeatureFlagsBundle\Command;

use SoureCode\Component\FeatureFlags\Manager\FeatureFlagsManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'features:disable', description: 'Disable a feature flag (creates the row if missing).')]
final class FeatureFlagsDisableCommand extends Command
{
    public function __construct(
        private readonly FeatureFlagsManagerInterface $manager,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('name', InputArgument::REQUIRED, 'Flag name');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $name = (string) $input->getArgument('name');
        $this->manager->disable($name);

        (new SymfonyStyle($input, $output))->success(\sprintf('Feature flag "%s" disabled.', $name));

        return self::SUCCESS;
    }
}
