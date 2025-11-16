<?php

declare(strict_types=1);

namespace Tourze\DeepSeekApiBundle\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Tourze\DeepSeekApiBundle\Service\DeepSeekService;

#[AsCommand(
    name: 'deepseek:models:list',
    description: 'List available DeepSeek models',
)]
class ListModelsCommand extends Command
{
    public function __construct(
        private readonly DeepSeekService $deepSeekService,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('api-key', InputArgument::OPTIONAL, 'Specific API key to use')
            ->addOption('all-keys', null, InputOption::VALUE_NONE, 'List models for all configured API keys')
            ->setHelp('This command lists all available DeepSeek models for the configured API keys')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $apiKeyRaw = $input->getArgument('api-key');
        $allKeysRaw = $input->getOption('all-keys');

        // Type safety for Console Input parameters
        assert(is_string($apiKeyRaw) || is_null($apiKeyRaw), 'api-key argument must be string or null');
        assert(is_bool($allKeysRaw) || is_null($allKeysRaw), 'all-keys option must be bool or null');

        $apiKey = $apiKeyRaw;
        $allKeys = (bool) $allKeysRaw;

        try {
            if ($allKeys) {
                return $this->listModelsForAllKeys($io);
            }

            return $this->listModelsForSingleKey($io, $apiKey);
        } catch (\Exception $e) {
            $io->error(sprintf('Failed to list models: %s', $e->getMessage()));

            return Command::FAILURE;
        }
    }

    private function listModelsForAllKeys(SymfonyStyle $io): int
    {
        $io->title('Listing models for all API keys');
        $modelsByKey = $this->deepSeekService->listModelsForAllKeys();

        foreach ($modelsByKey as $keySuffix => $models) {
            $this->displayModelsForKey($io, $keySuffix, $models);
        }

        return Command::SUCCESS;
    }

    private function listModelsForSingleKey(SymfonyStyle $io, ?string $apiKey): int
    {
        $io->title('DeepSeek Available Models');
        $models = $this->deepSeekService->listModels($apiKey);

        if ([] === $models) {
            $io->warning('No models available');

            return Command::SUCCESS;
        }

        $this->displayModelsTable($io, $models);
        $io->success(sprintf('Found %d models', count($models)));

        return Command::SUCCESS;
    }

    /**
     * @param mixed[] $models
     */
    private function displayModelsForKey(SymfonyStyle $io, string $keySuffix, array $models): void
    {
        $io->section(sprintf('API Key ending with: %s', $keySuffix));

        if ([] === $models) {
            $io->warning('No models available or API key invalid');

            return;
        }

        $this->displayModelsTable($io, $models);
    }

    /**
     * @param mixed[] $models
     */
    private function displayModelsTable(SymfonyStyle $io, array $models): void
    {
        $rows = [];
        foreach ($models as $model) {
            // Type safety for model objects
            if (!is_object($model) || !method_exists($model, 'getId') || !method_exists($model, 'getObject') || !method_exists($model, 'getOwnedBy')) {
                continue; // Skip invalid model objects
            }

            $rows[] = [
                $model->getId(),
                $model->getObject(),
                $model->getOwnedBy(),
            ];
        }

        $io->table(['Model ID', 'Type', 'Owned By'], $rows);
    }
}
