<?php

declare(strict_types=1);

namespace Tourze\DeepSeekApiBundle\Tests\Repository;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Tourze\DeepSeekApiBundle\Entity\DeepSeekApiKey;
use Tourze\DeepSeekApiBundle\Entity\DeepSeekBalance;
use Tourze\DeepSeekApiBundle\Repository\DeepSeekBalanceRepository;
use Tourze\PHPUnitSymfonyKernelTest\AbstractRepositoryTestCase;

/**
 * @internal
 */
#[CoversClass(DeepSeekBalanceRepository::class)]
#[RunTestsInSeparateProcesses]
class DeepSeekBalanceRepositoryTest extends AbstractRepositoryTestCase
{
    private DeepSeekBalanceRepository $repository;

    private DeepSeekApiKey $apiKey;

    protected function onSetUp(): void
    {
        $this->repository = self::getService(DeepSeekBalanceRepository::class);

        // 创建测试用的 API Key
        $this->apiKey = new DeepSeekApiKey();
        $this->apiKey->setName('Test API Key');
        $this->apiKey->setApiKey('sk-test-123456789');

        $entityManager = static::getEntityManager();
        $entityManager->persist($this->apiKey);
        $entityManager->flush();
    }

    protected function createNewEntity(): DeepSeekBalance
    {
        return $this->createBalance('USD', '100.0000');
    }

    protected function getRepository(): DeepSeekBalanceRepository
    {
        return $this->repository;
    }

    public function testSaveAndFind(): void
    {
        $balance = new DeepSeekBalance();
        $balance->setApiKey($this->apiKey);
        $balance->setCurrency('CNY');
        $balance->setTotalBalance('100.5000');
        $balance->setGrantedBalance('50.0000');
        $balance->setToppedUpBalance('50.5000');

        $this->repository->save($balance, true);

        $found = $this->repository->find($balance->getId());
        $this->assertInstanceOf(DeepSeekBalance::class, $found);
        $this->assertEquals('CNY', $found->getCurrency());
        $this->assertEquals('100.5000', $found->getTotalBalance());
    }

    public function testFindLatestByApiKey(): void
    {
        // 创建多个余额记录
        $balance1 = $this->createBalance('CNY', '100.0000', new \DateTimeImmutable('-2 hours'));
        $balance2 = $this->createBalance('CNY', '90.0000', new \DateTimeImmutable('-1 hour'));
        $balance3 = $this->createBalance('CNY', '80.0000', new \DateTimeImmutable());

        $this->repository->save($balance1, false);
        $this->repository->save($balance2, false);
        $this->repository->save($balance3, true);

        $latest = $this->repository->findLatestByApiKey($this->apiKey);
        $this->assertInstanceOf(DeepSeekBalance::class, $latest);
        $this->assertEquals('80.0000', $latest->getTotalBalance());
    }

    public function testFindByApiKeyAndCurrency(): void
    {
        $balanceCNY = $this->createBalance('CNY', '100.0000');
        $balanceUSD = $this->createBalance('USD', '15.0000');

        $this->repository->save($balanceCNY, false);
        $this->repository->save($balanceUSD, true);

        $cnyBalances = $this->repository->findByApiKeyAndCurrency($this->apiKey, 'CNY');
        $this->assertCount(1, $cnyBalances);
        $this->assertEquals('CNY', $cnyBalances[0]->getCurrency());

        $usdBalances = $this->repository->findByApiKeyAndCurrency($this->apiKey, 'USD');
        $this->assertCount(1, $usdBalances);
        $this->assertEquals('USD', $usdBalances[0]->getCurrency());
    }

    public function testFindLowBalances(): void
    {
        // 清理之前测试产生的数据
        $entityManager = static::getEntityManager();
        $entityManager->createQuery('DELETE FROM Tourze\DeepSeekApiBundle\Entity\DeepSeekBalance')->execute();
        $entityManager->flush();

        $lowBalance = $this->createBalance('CNY', '5.0000');
        $normalBalance = $this->createBalance('USD', '15.0000');

        $this->repository->save($lowBalance, false);
        $this->repository->save($normalBalance, true);

        $lowBalances = $this->repository->findLowBalances(10.0);
        $this->assertCount(1, $lowBalances);
        $this->assertEquals('5.0000', $lowBalances[0]->getTotalBalance());
    }

    public function testFindBalanceHistory(): void
    {
        $startDate = new \DateTimeImmutable('-1 day');
        $endDate = new \DateTimeImmutable('+1 day');

        $balance = $this->createBalance('CNY', '100.0000');
        $this->repository->save($balance, true);

        $history = $this->repository->findBalanceHistory($this->apiKey, $startDate, $endDate);
        $this->assertCount(1, $history);
        $this->assertEquals('100.0000', $history[0]->getTotalBalance());
    }

    public function testSaveBalances(): void
    {
        $balance1 = $this->createBalance('CNY', '100.0000');
        $balance2 = $this->createBalance('USD', '15.0000');

        $this->repository->saveBalances($this->apiKey, [$balance1, $balance2]);

        $allBalances = $this->repository->findBy(['apiKey' => $this->apiKey]);
        $this->assertCount(2, $allBalances);
    }

    public function testSaveBalancesWithPreviousBalance(): void
    {
        // 先保存一个旧余额
        $oldBalance = $this->createBalance('CNY', '100.0000');
        $this->repository->save($oldBalance, true);

        // 再保存新余额
        $newBalance = $this->createBalance('CNY', '90.0000');
        $this->repository->saveBalances($this->apiKey, [$newBalance]);

        $this->assertEquals('100.0000', $newBalance->getPreviousTotalBalance());
        $this->assertEquals('-10.0000', $newBalance->getBalanceChange());
    }

    public function testGetTotalBalanceByCurrency(): void
    {
        // 清理之前测试产生的数据
        $entityManager = static::getEntityManager();
        $entityManager->createQuery('DELETE FROM Tourze\DeepSeekApiBundle\Entity\DeepSeekBalance')->execute();
        $entityManager->flush();

        // 创建多个API Key用于测试
        $activeApiKey = new DeepSeekApiKey();
        $activeApiKey->setName('Active Key');
        $activeApiKey->setApiKey('sk-active-123');
        $activeApiKey->setIsActive(true);
        $activeApiKey->setIsValid(true);

        $inactiveApiKey = new DeepSeekApiKey();
        $inactiveApiKey->setName('Inactive Key');
        $inactiveApiKey->setApiKey('sk-inactive-123');
        $inactiveApiKey->setIsActive(false);
        $inactiveApiKey->setIsValid(true);

        $entityManager->persist($activeApiKey);
        $entityManager->persist($inactiveApiKey);
        $entityManager->flush();

        // 为激活的key创建余额
        $activeBalance = new DeepSeekBalance();
        $activeBalance->setApiKey($activeApiKey);
        $activeBalance->setCurrency('CNY');
        $activeBalance->setTotalBalance('100.0000');

        // 为未激活的key创建余额
        $inactiveBalance = new DeepSeekBalance();
        $inactiveBalance->setApiKey($inactiveApiKey);
        $inactiveBalance->setCurrency('CNY');
        $inactiveBalance->setTotalBalance('50.0000');

        $this->repository->save($activeBalance, false);
        $this->repository->save($inactiveBalance, true);

        $totals = $this->repository->getTotalBalanceByCurrency();
        $this->assertEquals(100.0, $totals['CNY']); // 只统计激活的key
    }

    public function testGetBalanceStatistics(): void
    {
        // 清理之前测试产生的数据
        $entityManager = static::getEntityManager();
        $entityManager->createQuery('DELETE FROM Tourze\DeepSeekApiBundle\Entity\DeepSeekBalance')->execute();
        $entityManager->flush();

        $balanceCNY = $this->createBalance('CNY', '100.0000');
        $balanceUSD = $this->createBalance('USD', '20.0000');
        $lowBalance = $this->createBalance('CNY', '5.0000');

        $this->repository->save($balanceCNY, false);
        $this->repository->save($balanceUSD, false);
        $this->repository->save($lowBalance, true);

        $stats = $this->repository->getBalanceStatistics();
        $this->assertEquals(3, $stats['total_records']);
        $this->assertEquals(52.5, $stats['average_balance_cny']); // (100 + 5) / 2
        $this->assertEquals(20.0, $stats['average_balance_usd']);
        $this->assertEquals(1, $stats['low_balance_count']);
    }

    public function testCleanOldRecords(): void
    {
        // 清理之前测试产生的数据
        $entityManager = static::getEntityManager();
        $entityManager->createQuery('DELETE FROM Tourze\DeepSeekApiBundle\Entity\DeepSeekBalance')->execute();
        $entityManager->flush();

        // 创建旧记录
        $oldBalance = $this->createBalance('CNY', '100.0000', new \DateTimeImmutable('-31 days'));
        $recentBalance = $this->createBalance('CNY', '90.0000');

        $this->repository->save($oldBalance, false);
        $this->repository->save($recentBalance, true);

        $deletedCount = $this->repository->cleanOldRecords(30);
        $this->assertEquals(1, $deletedCount);

        $remainingBalances = $this->repository->findAll();
        $this->assertCount(1, $remainingBalances);
        $this->assertEquals('90.0000', $remainingBalances[0]->getTotalBalance());
    }

    public function testRemove(): void
    {
        $balance = $this->createBalance('CNY', '100.0000');
        $this->repository->save($balance, true);

        $balanceId = $balance->getId();
        $this->assertNotNull($balanceId);

        $this->repository->remove($balance, true);

        $found = $this->repository->find($balanceId);
        $this->assertNull($found);
    }

    public function testFindLatestForAllKeys(): void
    {
        // 清理之前测试产生的数据
        $entityManager = static::getEntityManager();
        $entityManager->createQuery('DELETE FROM Tourze\DeepSeekApiBundle\Entity\DeepSeekBalance')->execute();
        $entityManager->flush();

        // 创建第二个 API Key
        $apiKey2 = new DeepSeekApiKey();
        $apiKey2->setName('Test API Key 2');
        $apiKey2->setApiKey('sk-test-key-2');
        $apiKey2->setIsActive(true);
        $apiKey2->setIsValid(true);

        $entityManager->persist($apiKey2);
        $entityManager->flush();

        // 创建不同时间的余额记录
        $balance1 = $this->createBalance('USD', '100.0000', new \DateTimeImmutable('-1 hour'));
        $balance1->setApiKey($this->apiKey);
        $this->repository->save($balance1, true);

        $balance2 = $this->createBalance('USD', '150.0000'); // 最新记录
        $balance2->setApiKey($this->apiKey);
        $this->repository->save($balance2, true);

        $balance3 = $this->createBalance('USD', '75.0000');
        $balance3->setApiKey($apiKey2);
        $this->repository->save($balance3, true);

        $latestBalances = $this->repository->findLatestForAllKeys();

        $this->assertCount(2, $latestBalances);

        $apiKey1Latest = null;
        $apiKey2Latest = null;
        foreach ($latestBalances as $balance) {
            $balanceApiKey = $balance->getApiKey();
            $this->assertNotNull($balanceApiKey, 'Balance API key should not be null');

            if ($balanceApiKey->getId() === $this->apiKey->getId()) {
                $apiKey1Latest = $balance;
            } elseif ($balanceApiKey->getId() === $apiKey2->getId()) {
                $apiKey2Latest = $balance;
            }
        }

        $this->assertNotNull($apiKey1Latest);
        $this->assertNotNull($apiKey2Latest);
        $this->assertEquals('150.0000', $apiKey1Latest->getTotalBalance()); // 应该是最新的记录
        $this->assertEquals('75.0000', $apiKey2Latest->getTotalBalance());
    }

    private function createBalance(
        string $currency,
        string $totalBalance,
        ?\DateTimeImmutable $recordedAt = null,
    ): DeepSeekBalance {
        $balance = new DeepSeekBalance();
        $balance->setApiKey($this->apiKey);
        $balance->setCurrency($currency);
        $balance->setTotalBalance($totalBalance);
        $balance->setGrantedBalance('50.0000');
        $balance->setToppedUpBalance('50.0000');

        if (null !== $recordedAt) {
            $balance->setRecordTime($recordedAt);
        }

        return $balance;
    }

    protected function onTearDown(): void
    {
        // 清理测试数据
        $entityManager = static::getEntityManager();
        $entityManager->clear();
    }
}
