<?php

declare(strict_types=1);

namespace Tourze\DeepSeekApiBundle\Tests\Repository;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Tourze\DeepSeekApiBundle\Entity\DeepSeekApiKey;
use Tourze\DeepSeekApiBundle\Entity\DeepSeekBalance;
use Tourze\DeepSeekApiBundle\Entity\DeepSeekBalanceHistory;
use Tourze\DeepSeekApiBundle\Repository\DeepSeekBalanceHistoryRepository;
use Tourze\PHPUnitSymfonyKernelTest\AbstractRepositoryTestCase;

/**
 * @internal
 */
#[CoversClass(DeepSeekBalanceHistoryRepository::class)]
#[RunTestsInSeparateProcesses]
class DeepSeekBalanceHistoryRepositoryTest extends AbstractRepositoryTestCase
{
    private DeepSeekBalanceHistoryRepository $repository;

    private DeepSeekApiKey $apiKey;

    protected function onSetUp(): void
    {
        $this->repository = self::getService(DeepSeekBalanceHistoryRepository::class);

        // 创建测试用的 API Key
        $this->apiKey = new DeepSeekApiKey();
        $this->apiKey->setName('Test API Key');
        $this->apiKey->setApiKey('sk-test-123456789');

        $entityManager = static::getEntityManager();
        $entityManager->persist($this->apiKey);
        $entityManager->flush();
    }

    protected function createNewEntity(): DeepSeekBalanceHistory
    {
        return $this->createBalanceHistory('USD', '100.0000');
    }

    protected function getRepository(): DeepSeekBalanceHistoryRepository
    {
        return $this->repository;
    }

    public function testSaveAndFind(): void
    {
        $history = $this->createBalanceHistory('CNY', '100.0000');

        $this->repository->save($history, true);

        $found = $this->repository->find($history->getId());
        $this->assertInstanceOf(DeepSeekBalanceHistory::class, $found);
        $this->assertEquals('CNY', $found->getCurrency());
        $this->assertEquals('100.0000', $found->getTotalBalance());
    }

    public function testRecordBalanceChange(): void
    {
        $balance = $this->createBalance('CNY', '100.0000');

        $history = $this->repository->recordBalanceChange($balance);

        $this->assertInstanceOf(DeepSeekBalanceHistory::class, $history);
        $this->assertEquals('100.0000', $history->getTotalBalance());
        $this->assertEquals('CNY', $history->getCurrency());
        $this->assertSame($this->apiKey, $history->getApiKey());
        $this->assertEquals('api_sync', $history->getDataSource());
    }

    public function testRecordBalanceChangeWithPrevious(): void
    {
        // 先记录一个历史
        $firstBalance = $this->createBalance('CNY', '100.0000');
        $firstHistory = $this->repository->recordBalanceChange($firstBalance);

        // 再记录一个历史，应该能计算变化
        $secondBalance = $this->createBalance('CNY', '90.0000');
        $secondHistory = $this->repository->recordBalanceChange($secondBalance);

        $this->assertEquals('-10.0000', $secondHistory->getBalanceChange());
        $this->assertEquals('decrease', $secondHistory->getChangeType());
    }

    public function testFindLatestByApiKeyAndCurrency(): void
    {
        $history1 = $this->createBalanceHistory('CNY', '100.0000', new \DateTimeImmutable('-2 hours'));
        $history2 = $this->createBalanceHistory('CNY', '90.0000', new \DateTimeImmutable('-1 hour'));
        $history3 = $this->createBalanceHistory('USD', '20.0000');

        $this->repository->save($history1, false);
        $this->repository->save($history2, false);
        $this->repository->save($history3, true);

        $latest = $this->repository->findLatestByApiKeyAndCurrency($this->apiKey, 'CNY');
        $this->assertInstanceOf(DeepSeekBalanceHistory::class, $latest);
        $this->assertEquals('90.0000', $latest->getTotalBalance());

        $latestUSD = $this->repository->findLatestByApiKeyAndCurrency($this->apiKey, 'USD');
        $this->assertNotNull($latestUSD);
        $this->assertEquals('20.0000', $latestUSD->getTotalBalance());

        $notFound = $this->repository->findLatestByApiKeyAndCurrency($this->apiKey, 'EUR');
        $this->assertNull($notFound);
    }

    public function testRecordBalanceChanges(): void
    {
        // 清理之前测试产生的数据
        $entityManager = static::getEntityManager();
        $entityManager->createQuery('DELETE FROM Tourze\DeepSeekApiBundle\Entity\DeepSeekBalanceHistory')->execute();
        $entityManager->flush();

        $balance1 = $this->createBalance('CNY', '100.0000');
        $balance2 = $this->createBalance('USD', '20.0000');

        $histories = $this->repository->recordBalanceChanges([$balance1, $balance2]);

        $this->assertCount(2, $histories);
        $this->assertInstanceOf(DeepSeekBalanceHistory::class, $histories[0]);
        $this->assertInstanceOf(DeepSeekBalanceHistory::class, $histories[1]);

        // 验证都被持久化了
        $allHistories = $this->repository->findAll();
        $this->assertCount(2, $allHistories);
    }

    public function testFindByApiKey(): void
    {
        // 创建另一个API Key
        $otherApiKey = new DeepSeekApiKey();
        $otherApiKey->setName('Other API Key');
        $otherApiKey->setApiKey('sk-other-123456789');

        $entityManager = static::getEntityManager();
        $entityManager->persist($otherApiKey);
        $entityManager->flush();

        $history1 = $this->createBalanceHistory('CNY', '100.0000');
        $history2 = $this->createBalanceHistory('USD', '20.0000');

        $otherHistory = new DeepSeekBalanceHistory();
        $otherHistory->setApiKey($otherApiKey);
        $otherHistory->setCurrency('CNY');
        $otherHistory->setTotalBalance('50.0000');

        $this->repository->save($history1, false);
        $this->repository->save($history2, false);
        $this->repository->save($otherHistory, true);

        $histories = $this->repository->findByApiKey($this->apiKey, 10);
        $this->assertCount(2, $histories);

        $otherHistories = $this->repository->findByApiKey($otherApiKey, 10);
        $this->assertCount(1, $otherHistories);
        $this->assertEquals('50.0000', $otherHistories[0]->getTotalBalance());
    }

    public function testFindByDateRange(): void
    {
        // 清理之前测试产生的数据
        $entityManager = static::getEntityManager();
        $entityManager->createQuery('DELETE FROM Tourze\DeepSeekApiBundle\Entity\DeepSeekBalanceHistory')->execute();
        $entityManager->flush();

        $startDate = new \DateTimeImmutable('-2 days');
        $endDate = new \DateTimeImmutable();

        $oldHistory = $this->createBalanceHistory('CNY', '100.0000', new \DateTimeImmutable('-3 days'));
        $recentHistory = $this->createBalanceHistory('CNY', '90.0000', new \DateTimeImmutable('-1 day'));

        $this->repository->save($oldHistory, false);
        $this->repository->save($recentHistory, true);

        $histories = $this->repository->findByDateRange($startDate, $endDate);
        $this->assertCount(1, $histories);
        $this->assertEquals('90.0000', $histories[0]->getTotalBalance());

        $historiesWithApiKey = $this->repository->findByDateRange(
            $startDate,
            $endDate,
            $this->apiKey,
            'CNY'
        );
        $this->assertCount(1, $historiesWithApiKey);
    }

    public function testGetBalanceTrend(): void
    {
        $startDate = new \DateTimeImmutable('-3 days');
        $endDate = new \DateTimeImmutable();

        // 创建一些历史记录
        $history1 = $this->createBalanceHistory('CNY', '100.0000', new \DateTimeImmutable('-2 days'));
        $history2 = $this->createBalanceHistory('CNY', '90.0000', new \DateTimeImmutable('-1 day'));
        $history3 = $this->createBalanceHistory('CNY', '80.0000', new \DateTimeImmutable());

        $this->repository->save($history1, false);
        $this->repository->save($history2, false);
        $this->repository->save($history3, true);

        $trend = $this->repository->getBalanceTrend($this->apiKey, 'CNY', $startDate, $endDate, 'daily');

        $this->assertIsArray($trend);
        $this->assertNotEmpty($trend);

        $firstPeriod = reset($trend);
        $this->assertArrayHasKey('date', $firstPeriod);
        $this->assertArrayHasKey('records', $firstPeriod);
        $this->assertArrayHasKey('start_balance', $firstPeriod);
        $this->assertArrayHasKey('end_balance', $firstPeriod);
        $this->assertArrayHasKey('change', $firstPeriod);
    }

    public function testGetConsumptionStatistics(): void
    {
        $startDate = new \DateTimeImmutable('-1 day');
        $endDate = new \DateTimeImmutable();

        // 创建消费历史
        $history1 = $this->createBalanceHistory('CNY', '100.0000');
        $history1->setBalanceChange('-10.0000');
        $history1->setChangeType('decrease');

        $history2 = $this->createBalanceHistory('CNY', '110.0000');
        $history2->setBalanceChange('20.0000');
        $history2->setChangeType('increase');

        $this->repository->save($history1, false);
        $this->repository->save($history2, true);

        $stats = $this->repository->getConsumptionStatistics($this->apiKey, $startDate, $endDate);

        $this->assertArrayHasKey('CNY', $stats);
        $cnyStats = $stats['CNY'];
        $this->assertIsArray($cnyStats);

        $this->assertEquals('CNY', $cnyStats['currency']);
        $this->assertEquals(10.0, $cnyStats['total_consumed']);
        $this->assertEquals(20.0, $cnyStats['total_topped_up']);
        $this->assertEquals(2, $cnyStats['record_count']);
        $this->assertArrayHasKey('decreases', $cnyStats);
        $this->assertArrayHasKey('increases', $cnyStats);
    }

    public function testCleanOldRecords(): void
    {
        // 清理之前测试产生的数据
        $entityManager = static::getEntityManager();
        $entityManager->createQuery('DELETE FROM Tourze\DeepSeekApiBundle\Entity\DeepSeekBalanceHistory')->execute();
        $entityManager->flush();

        $oldHistory = $this->createBalanceHistory('CNY', '100.0000', new \DateTimeImmutable('-91 days'));
        $recentHistory = $this->createBalanceHistory('CNY', '90.0000');

        $this->repository->save($oldHistory, false);
        $this->repository->save($recentHistory, true);

        $deletedCount = $this->repository->cleanOldRecords(90);
        $this->assertEquals(1, $deletedCount);

        $remainingHistories = $this->repository->findAll();
        $this->assertCount(1, $remainingHistories);
        $this->assertEquals('90.0000', $remainingHistories[0]->getTotalBalance());
    }

    public function testGetBalanceAlerts(): void
    {
        // 创建低余额历史
        $lowBalanceHistory = $this->createBalanceHistory('CNY', '3.0000');
        $normalBalanceHistory = $this->createBalanceHistory('USD', '20.0000');

        $this->repository->save($lowBalanceHistory, false);
        $this->repository->save($normalBalanceHistory, true);

        $alerts = $this->repository->getBalanceAlerts(10.0);

        $this->assertCount(1, $alerts);
        $alert = $alerts[0];

        $this->assertEquals('Test API Key', $alert['api_key']);
        $this->assertEquals('CNY', $alert['currency']);
        $this->assertEquals(3.0, $alert['current_balance']);
        $this->assertEquals(10.0, $alert['threshold']);
        $this->assertEquals('critical', $alert['alert_level']); // 3 < 10 * 0.5
    }

    public function testFindLatestForAllKeys(): void
    {
        // 清理之前测试产生的数据
        $entityManager = static::getEntityManager();
        $entityManager->createQuery('DELETE FROM Tourze\DeepSeekApiBundle\Entity\DeepSeekBalanceHistory')->execute();
        $entityManager->flush();

        // 创建另一个API Key
        $otherApiKey = new DeepSeekApiKey();
        $otherApiKey->setName('Other API Key');
        $otherApiKey->setApiKey('sk-other-123456789');

        $entityManager->persist($otherApiKey);
        $entityManager->flush();

        // 为第一个key创建历史
        $history1 = $this->createBalanceHistory('CNY', '100.0000', new \DateTimeImmutable('-2 hours'));
        $history2 = $this->createBalanceHistory('CNY', '90.0000', new \DateTimeImmutable('-1 hour'));

        // 为第二个key创建历史
        $otherHistory = new DeepSeekBalanceHistory();
        $otherHistory->setApiKey($otherApiKey);
        $otherHistory->setCurrency('CNY');
        $otherHistory->setTotalBalance('50.0000');

        $this->repository->save($history1, false);
        $this->repository->save($history2, false);
        $this->repository->save($otherHistory, true);

        $latest = $this->repository->findLatestForAllKeys();
        $this->assertCount(2, $latest); // 每个key-currency组合的最新记录

        $balances = array_map(fn ($h) => $h->getTotalBalanceAsFloat(), $latest);
        $this->assertContains(90.0, $balances);
        $this->assertContains(50.0, $balances);
    }

    public function testRemove(): void
    {
        $history = $this->createBalanceHistory('CNY', '100.0000');
        $this->repository->save($history, true);

        $historyId = $history->getId();
        $this->assertNotNull($historyId);

        $this->repository->remove($history, true);

        $found = $this->repository->find($historyId);
        $this->assertNull($found);
    }

    public function testBalanceHistoryCalculateChange(): void
    {
        $previousHistory = $this->createBalanceHistory('CNY', '100.0000');
        $this->repository->save($previousHistory, true);

        $currentHistory = $this->createBalanceHistory('CNY', '90.0000');
        $currentHistory->calculateChange($previousHistory);

        $this->assertEquals('-10.0000', $currentHistory->getBalanceChange());
        $this->assertEquals('decrease', $currentHistory->getChangeType());

        // 测试增加的情况
        $increaseHistory = $this->createBalanceHistory('CNY', '110.0000');
        $increaseHistory->calculateChange($previousHistory);

        $this->assertEquals('10.0000', $increaseHistory->getBalanceChange());
        $this->assertEquals('increase', $increaseHistory->getChangeType());

        // 测试无变化
        $noChangeHistory = $this->createBalanceHistory('CNY', '100.0000');
        $noChangeHistory->calculateChange($previousHistory);

        $this->assertEquals('0.0000', $noChangeHistory->getBalanceChange());
        $this->assertEquals('no_change', $noChangeHistory->getChangeType());
    }

    private function createBalance(string $currency, string $totalBalance): DeepSeekBalance
    {
        $balance = new DeepSeekBalance();
        $balance->setApiKey($this->apiKey);
        $balance->setCurrency($currency);
        $balance->setTotalBalance($totalBalance);
        $balance->setGrantedBalance('50.0000');
        $balance->setToppedUpBalance('50.0000');

        return $balance;
    }

    private function createBalanceHistory(
        string $currency,
        string $totalBalance,
        ?\DateTimeImmutable $recordedAt = null,
    ): DeepSeekBalanceHistory {
        $history = new DeepSeekBalanceHistory();
        $history->setApiKey($this->apiKey);
        $history->setCurrency($currency);
        $history->setTotalBalance($totalBalance);
        $history->setGrantedBalance('50.0000');
        $history->setToppedUpBalance('50.0000');

        if (null !== $recordedAt) {
            $history->setRecordTime($recordedAt);
        }

        return $history;
    }

    protected function onTearDown(): void
    {
        // 清理测试数据
        $entityManager = static::getEntityManager();
        $entityManager->clear();
    }
}
