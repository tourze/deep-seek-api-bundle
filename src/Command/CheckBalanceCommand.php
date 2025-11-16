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
use Tourze\DeepSeekApiBundle\DTO\BalanceInfo;
use Tourze\DeepSeekApiBundle\Service\DeepSeekService;

#[AsCommand(
    name: 'deepseek:balance:check',
    description: 'Check DeepSeek API account balance',
)]
class CheckBalanceCommand extends Command
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
            ->addOption('all-keys', null, InputOption::VALUE_NONE, 'Check balance for all configured API keys')
            ->addOption('total', null, InputOption::VALUE_NONE, 'Show total balance across all keys')
            ->setHelp('This command checks the account balance for DeepSeek API keys')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $apiKeyArg = $input->getArgument('api-key');
        $apiKey = is_string($apiKeyArg) ? $apiKeyArg : null;
        $allKeys = (bool) $input->getOption('all-keys');
        $showTotal = (bool) $input->getOption('total');

        try {
            if ($showTotal) {
                $io->title('Total Balance Across All API Keys');
                $totals = $this->deepSeekService->getTotalBalance();

                if ([] === $totals) {
                    $io->warning('No balance information available');

                    return Command::SUCCESS;
                }

                $rows = [];
                foreach ($totals as $currency => $amount) {
                    assert(is_string($currency), 'Currency should be string');
                    assert(is_float($amount), 'Amount should be float');
                    $rows[] = [$currency, number_format($amount, 2)];
                }

                $io->table(['Currency', 'Total Balance'], $rows);
            } elseif ($allKeys) {
                $io->title('Balance for all API keys');
                $balancesByKey = $this->deepSeekService->getBalanceForAllKeys();

                foreach ($balancesByKey as $keySuffix => $balance) {
                    assert(is_string($keySuffix), 'Key suffix should be string');
                    $io->section(sprintf('API Key ending with: %s', $keySuffix));
                    $this->displayBalance($io, $balance);
                }
            } else {
                $io->title('DeepSeek API Balance');
                $balance = $this->deepSeekService->getBalance($apiKey);
                $this->displayBalance($io, $balance);
            }

            return Command::SUCCESS;
        } catch (\Exception $e) {
            $io->error(sprintf('Failed to check balance: %s', $e->getMessage()));

            return Command::FAILURE;
        }
    }

    private function displayBalance(SymfonyStyle $io, BalanceInfo $balance): void
    {
        if (!$balance->isAvailable()) {
            $io->warning('Balance not available');

            return;
        }

        $rows = [];
        foreach ($balance->getBalanceInfos() as $currencyBalance) {
            $rows[] = [
                $currencyBalance->getCurrency(),
                $currencyBalance->getTotalBalance(),
                $currencyBalance->getGrantedBalance(),
                $currencyBalance->getToppedUpBalance(),
            ];
        }

        if ([] === $rows) {
            $io->info('No balance information');

            return;
        }

        $io->table(
            ['Currency', 'Total Balance', 'Granted Balance', 'Topped Up Balance'],
            $rows
        );

        if ($balance->hasPositiveBalance()) {
            $io->success('Account has positive balance');
        } else {
            $io->warning('Account has zero balance');
        }
    }
}
