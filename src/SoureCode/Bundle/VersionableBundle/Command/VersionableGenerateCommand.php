<?php

declare(strict_types=1);

namespace SoureCode\Bundle\VersionableBundle\Command;

use Doctrine\ORM\EntityManagerInterface;
use SoureCode\Component\Versionable\Internal\History\EntityProxyFactory;
use SoureCode\Component\Versionable\Internal\History\HistoryClassFactory;
use SoureCode\Component\Versionable\Internal\History\PhpStormMetaWriter;
use SoureCode\Component\Versionable\Metadata\VersionableMetadataFactory;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Eagerly generate every versioned entity's `*History` DTO and entity-proxy
 * class. Equivalent to what {@see \SoureCode\Bundle\VersionableBundle\CacheWarmer\VersionableCacheWarmer}
 * does during `cache:warmup`, exposed as a separate command for CI deploys,
 * debugging, and `--clear` regeneration during development.
 */
#[AsCommand(
    name: 'versionable:generate',
    description: 'Pre-generate the *History DTOs and *VersionableProxy classes for every #[Versioned] entity.',
)]
final class VersionableGenerateCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly VersionableMetadataFactory $metadataFactory,
        private readonly HistoryClassFactory $historyClassFactory,
        private readonly EntityProxyFactory $entityProxyFactory,
        private readonly PhpStormMetaWriter $metaWriter,
        private readonly string $cacheDir,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption(
            'clear',
            null,
            InputOption::VALUE_NONE,
            'Delete every existing generated file before regenerating (forces a clean rebuild).',
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        if ($input->getOption('clear')) {
            $this->clearCacheDir($io);
        }

        $generatedHistories = 0;
        $generatedProxies = 0;

        foreach ($this->entityManager->getMetadataFactory()->getAllMetadata() as $classMetadata) {
            $className = $classMetadata->getName();

            if (!$this->metadataFactory->isVersionable($className)) {
                continue;
            }

            $this->historyClassFactory->ensureGenerated($className);
            ++$generatedHistories;

            if ($this->entityProxyFactory->ensureGenerated($classMetadata) !== null) {
                ++$generatedProxies;
            }
        }

        $this->metaWriter->write($this->cacheDir);

        $io->success(\sprintf(
            'Generated %d *History class(es) and %d *VersionableProxy class(es) in %s.',
            $generatedHistories,
            $generatedProxies,
            $this->cacheDir,
        ));

        return Command::SUCCESS;
    }

    private function clearCacheDir(SymfonyStyle $io): void
    {
        if (!is_dir($this->cacheDir)) {
            return;
        }

        $removed = 0;

        foreach (glob($this->cacheDir . \DIRECTORY_SEPARATOR . '*') ?: [] as $file) {
            if (!is_file($file)) {
                continue;
            }

            $basename = basename($file);

            if (
                !str_ends_with($basename, 'History.php')
                && !str_ends_with($basename, 'VersionableProxy.php')
                && $basename !== PhpStormMetaWriter::FILE_NAME
            ) {
                continue;
            }

            if (@unlink($file)) {
                ++$removed;
            }
        }

        $io->writeln(\sprintf('<comment>--clear: removed %d generated file(s) from %s.</comment>', $removed, $this->cacheDir));
    }
}
