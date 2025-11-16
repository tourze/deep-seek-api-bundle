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
    name: 'deepseek:api-key:validate',
    description: 'Validate DeepSeek API keys',
)]
class ValidateApiKeyCommand extends Command
{
    public function __construct(
        private readonly DeepSeekService $deepSeekService,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('api-key', InputArgument::OPTIONAL, 'Specific API key to validate')
            ->addOption('all-keys', null, InputOption::VALUE_NONE, 'Validate all configured API keys')
            ->setHelp('This command validates DeepSeek API keys by checking if they can access the API')
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

        if ((null === $apiKey || '' === $apiKey) && !$allKeys) {
            $io->error('Please provide an API key or use --all-keys option');

            return Command::FAILURE;
        }

        try {
            if ($allKeys) {
                return $this->validateAllKeys($io);
            }

            return $this->validateSingleKey($io, $apiKey);
        } catch (\Exception $e) {
            $io->error(sprintf('Validation failed: %s', $e->getMessage()));

            return Command::FAILURE;
        }
    }

    private function validateAllKeys(SymfonyStyle $io): int
    {
        $io->title('Validating all configured API keys');
        $results = $this->deepSeekService->validateAllApiKeys();

        if ([] === $results) {
            $io->warning('No API keys configured');

            return Command::SUCCESS;
        }

        [$rows, $validCount] = $this->buildValidationResultsTable($results);
        $io->table(['API Key (last 4 chars)', 'Status'], $rows);

        return $this->displayValidationSummary($io, $validCount, count($results));
    }

    private function validateSingleKey(SymfonyStyle $io, ?string $apiKey): int
    {
        if (null === $apiKey) {
            $io->error('API key cannot be null');

            return Command::FAILURE;
        }

        $io->title('Validating API key');
        $isValid = $this->deepSeekService->validateApiKey($apiKey);

        if ($isValid) {
            $io->success(sprintf('API key ending with %s is valid', substr($apiKey, -4)));

            return Command::SUCCESS;
        }

        $io->error(sprintf('API key ending with %s is invalid', substr($apiKey, -4)));

        return Command::FAILURE;
    }

    /**
     * @param array<string, bool> $results
     * @return array{array<int, array{string, string}>, int}
     */
    private function buildValidationResultsTable(array $results): array
    {
        $rows = [];
        $validCount = 0;

        foreach ($results as $keySuffix => $isValid) {
            $rows[] = [
                $keySuffix,
                $isValid ? '✅ Valid' : '❌ Invalid',
            ];
            if ($isValid) {
                ++$validCount;
            }
        }

        return [$rows, $validCount];
    }

    private function displayValidationSummary(SymfonyStyle $io, int $validCount, int $totalCount): int
    {
        if ($validCount === $totalCount) {
            $io->success(sprintf('All %d API keys are valid', $validCount));

            return Command::SUCCESS;
        }

        if ($validCount > 0) {
            $io->warning(sprintf('%d out of %d API keys are valid', $validCount, $totalCount));

            return Command::SUCCESS;
        }

        $io->error('All API keys are invalid');

        return Command::FAILURE;
    }
}
