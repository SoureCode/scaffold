<?php

declare(strict_types=1);

namespace SoureCode\Bundle\FeatureFlagsBundle\Command;

use SoureCode\Component\FeatureFlags\Manager\FeatureFlagsManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'features:list', description: 'List every known feature flag and its current state.')]
final class FeatureFlagsListCommand extends Command
{
    public function __construct(
        private readonly FeatureFlagsManagerInterface $manager,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $rows = [];

        foreach ($this->manager->all() as $flag) {
            $rows[] = [$flag->getName(), $flag->isEnabled() ? 'enabled' : 'disabled'];
        }

        if ($rows === []) {
            $io->warning('No feature flags registered.');

            return self::SUCCESS;
        }

        $io->table(['Name', 'State'], $rows);

        return self::SUCCESS;
    }
}
