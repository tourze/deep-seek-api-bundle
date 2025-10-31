<?php

declare(strict_types=1);

namespace Tourze\DeepSeekApiBundle\Service;

use Psr\Log\LoggerInterface;
use Tourze\DeepSeekApiBundle\DTO\BalanceInfo;
use Tourze\DeepSeekApiBundle\DTO\ModelInfo;
use Tourze\DeepSeekApiBundle\Entity\DeepSeekApiKey;
use Tourze\DeepSeekApiBundle\Entity\DeepSeekBalance;
use Tourze\DeepSeekApiBundle\Exception\InvalidApiKeyException;
use Tourze\DeepSeekApiBundle\Repository\DeepSeekApiKeyRepository;
use Tourze\DeepSeekApiBundle\Repository\DeepSeekBalanceHistoryRepository;
use Tourze\DeepSeekApiBundle\Repository\DeepSeekBalanceRepository;
use Tourze\DeepSeekApiBundle\Repository\DeepSeekModelRepository;
use Tourze\DeepSeekApiBundle\Request\GetBalanceRequest;
use Tourze\DeepSeekApiBundle\Request\ListModelsRequest;

class DeepSeekService
{
    public function __construct(
        private readonly DeepSeekApiClient $apiClient,
        private readonly ApiKeyManager $apiKeyManager,
        private readonly LoggerInterface $logger,
        private readonly DeepSeekApiKeyRepository $apiKeyRepository,
        private readonly DeepSeekModelRepository $modelRepository,
        private readonly DeepSeekBalanceRepository $balanceRepository,
        private readonly DeepSeekBalanceHistoryRepository $balanceHistoryRepository,
        private readonly LockManager $lockManager,
    ) {
    }

    /**
     * @return array<string, ModelInfo[]>
     */
    public function listModelsForAllKeys(): array
    {
        $apiKeys = $this->apiKeyRepository->findActiveKeys();

        if ([] === $apiKeys) {
            throw new InvalidApiKeyException('No API keys configured');
        }

        $result = [];
        foreach ($apiKeys as $apiKey) {
            $result[$apiKey->getName()] = $this->listModelsForSingleKey($apiKey);
        }

        return $result;
    }

    /**
     * @return ModelInfo[]
     */
    private function listModelsForSingleKey(DeepSeekApiKey $apiKey): array
    {
        try {
            $models = $this->listModels($apiKey->getApiKey());
            $this->syncModelsToDatabase($apiKey, $models);
            $this->updateApiKeyModelsSync($apiKey);
            $this->apiKeyManager->markKeyAsValid($apiKey->getApiKey());

            return $models;
        } catch (\Exception $e) {
            $this->handleModelsListingError($apiKey, $e);

            return [];
        }
    }

    /**
     * @param ModelInfo[] $models
     */
    private function syncModelsToDatabase(DeepSeekApiKey $apiKey, array $models): void
    {
        $modelsData = array_map(fn (ModelInfo $model) => [
            'id' => $model->getId(),
            'object' => $model->getObject(),
            'owned_by' => $model->getOwnedBy(),
        ], $models);

        $this->modelRepository->syncModelsForApiKey($apiKey, $modelsData);
    }

    private function updateApiKeyModelsSync(DeepSeekApiKey $apiKey): void
    {
        $apiKey->setLastModelsSyncTime(new \DateTimeImmutable());
        $this->apiKeyRepository->save($apiKey, true);
    }

    private function handleModelsListingError(DeepSeekApiKey $apiKey, \Exception $e): void
    {
        $this->logger->warning('Failed to list models for key', [
            'key_name' => $apiKey->getName(),
            'error' => $e->getMessage(),
        ]);

        $this->markInvalidIfUnauthorized($apiKey->getApiKey(), $e);
    }

    /**
     * @return ModelInfo[]
     */
    public function listModels(?string $apiKey = null): array
    {
        $this->setApiKeyIfProvided($apiKey);

        try {
            $response = $this->fetchModelsResponse();
            $models = $this->parseModelsFromResponse($response);

            $this->logModelsRetrievalSuccess($models, $apiKey);

            return $models;
        } catch (\Exception $e) {
            $this->logModelsRetrievalError($e, $apiKey);
            throw $e;
        }
    }

    private function setApiKeyIfProvided(?string $apiKey): void
    {
        if (null !== $apiKey) {
            $this->apiClient->setApiKey($apiKey);
        }
    }

    /**
     * @return mixed
     */
    private function fetchModelsResponse()
    {
        $request = new ListModelsRequest();

        return $this->apiClient->request($request);
    }

    /**
     * @param mixed $response
     * @return ModelInfo[]
     */
    private function parseModelsFromResponse($response): array
    {
        if (!$this->isValidModelsResponse($response)) {
            return [];
        }

        /** @var array{data: mixed} $response */
        return $this->extractModelsFromData($response['data']);
    }

    /**
     * 从数据中提取模型
     * @param mixed $data
     * @return ModelInfo[]
     */
    private function extractModelsFromData($data): array
    {
        if (!is_array($data)) {
            return [];
        }

        $models = [];
        foreach ($data as $modelData) {
            if ($this->isValidModelData($modelData)) {
                /** @var array{id: string, object: string, owned_by: string} $modelData */
                $models[] = ModelInfo::fromArray($modelData);
            }
        }

        return $models;
    }

    /**
     * @param mixed $response
     */
    private function isValidModelsResponse($response): bool
    {
        return is_array($response)
            && isset($response['data'])
            && is_array($response['data']);
    }

    /**
     * @param mixed $modelData
     */
    private function isValidModelData($modelData): bool
    {
        return is_array($modelData)
            && isset($modelData['id'], $modelData['object'], $modelData['owned_by'])
            && is_string($modelData['id'])
            && is_string($modelData['object'])
            && is_string($modelData['owned_by']);
    }

    /**
     * @param ModelInfo[] $models
     */
    private function logModelsRetrievalSuccess(array $models, ?string $apiKey): void
    {
        $this->logger->info('Models retrieved successfully', [
            'count' => count($models),
            'api_key_suffix' => $this->getApiKeySuffix($apiKey),
        ]);
    }

    private function logModelsRetrievalError(\Exception $e, ?string $apiKey): void
    {
        $this->logger->error('Failed to list models', [
            'error' => $e->getMessage(),
            'api_key_suffix' => $this->getApiKeySuffix($apiKey),
        ]);
    }

    /**
     * @return array<string, bool>
     */
    public function validateAllApiKeys(): array
    {
        $result = [];
        /** @var DeepSeekApiKey[] $apiKeys */
        $apiKeys = $this->apiKeyRepository->findAll();

        foreach ($apiKeys as $apiKey) {
            $result[$apiKey->getName()] = $this->validateApiKey($apiKey->getApiKey());
        }

        return $result;
    }

    public function validateApiKey(string $apiKey): bool
    {
        try {
            $balance = $this->getBalance($apiKey);
            $this->apiKeyManager->markKeyAsValid($apiKey);

            return $balance->isAvailable();
        } catch (\Exception $e) {
            $this->handleApiKeyValidationError($apiKey, $e);

            return false;
        }
    }

    /**
     * 处理API密钥验证错误
     */
    private function handleApiKeyValidationError(string $apiKey, \Exception $e): void
    {
        $this->logger->warning('API key validation failed', [
            'key_suffix' => substr($apiKey, -4),
            'error' => $e->getMessage(),
        ]);

        $this->markInvalidIfUnauthorized($apiKey, $e);
    }

    /**
     * 检查是否为未授权错误
     */
    private function isUnauthorizedError(\Exception $e): bool
    {
        return str_contains($e->getMessage(), '401');
    }

    /**
     * 如果是未授权错误则标记密钥为无效
     */
    private function markInvalidIfUnauthorized(string $apiKey, \Exception $e): void
    {
        if ($this->isUnauthorizedError($e)) {
            $this->apiKeyManager->markKeyAsInvalid($apiKey);
        }
    }

    public function getBalance(?string $apiKey = null): BalanceInfo
    {
        return $this->executeWithBalanceLock($apiKey, function () use ($apiKey): BalanceInfo {
            return $this->fetchAndProcessBalance($apiKey);
        });
    }

    /**
     * @return mixed
     */
    private function fetchBalanceResponse()
    {
        $request = new GetBalanceRequest();

        return $this->apiClient->request($request);
    }

    /**
     * @param mixed $response
     * @return array{is_available: bool, balance_infos?: array<array{currency: string, total_balance: string, granted_balance: string, topped_up_balance: string}>}
     */
    private function validateAndParseBalanceResponse($response): array
    {
        $this->validateResponseFormat($response);

        if (!is_array($response)) {
            throw new InvalidApiKeyException('Invalid response format from API');
        }

        /** @var array<string, mixed> $response */
        $this->validateIsAvailableField($response);

        return $this->buildValidatedResponse($response);
    }

    /**
     * 构建验证后的响应数据
     *
     * @param array<string, mixed> $response
     * @return array{is_available: bool, balance_infos?: array<array{currency: string, total_balance: string, granted_balance: string, topped_up_balance: string}>}
     */
    private function buildValidatedResponse(array $response): array
    {
        $validatedResponse = [
            'is_available' => (bool) $response['is_available'],
        ];

        if ($this->hasBalanceInfos($response)) {
            /** @var array<mixed> $balanceInfosData */
            $balanceInfosData = $response['balance_infos'];
            $validatedResponse['balance_infos'] = $this->parseBalanceInfos($balanceInfosData);
        }

        return $validatedResponse;
    }

    /**
     * @param mixed $response
     */
    private function validateResponseFormat($response): void
    {
        if (!is_array($response)) {
            throw new InvalidApiKeyException('Invalid response format from API');
        }
    }

    /**
     * @param array<string, mixed> $response
     */
    private function validateIsAvailableField(array $response): void
    {
        if (!isset($response['is_available']) || !is_bool($response['is_available'])) {
            throw new InvalidApiKeyException('Invalid balance response: missing or invalid is_available field');
        }
    }

    /**
     * @param array<string, mixed> $response
     */
    private function hasBalanceInfos(array $response): bool
    {
        return isset($response['balance_infos']) && is_array($response['balance_infos']);
    }

    /**
     * @param array<mixed> $balanceInfosData
     * @return array<array{currency: string, total_balance: string, granted_balance: string, topped_up_balance: string}>
     */
    private function parseBalanceInfos(array $balanceInfosData): array
    {
        $balanceInfos = [];
        foreach ($balanceInfosData as $balanceData) {
            if ($this->isValidBalanceData($balanceData)) {
                /** @var array{currency: string, total_balance: string, granted_balance: string, topped_up_balance: string} $balanceData */
                $balanceInfos[] = $balanceData;
            }
        }

        return $balanceInfos;
    }

    /**
     * @param mixed $balanceData
     */
    private function isValidBalanceData($balanceData): bool
    {
        if (!is_array($balanceData)) {
            return false;
        }

        return $this->hasRequiredBalanceFields($balanceData);
    }

    /**
     * 检查余额数据是否包含所有必需字段
     *
     * @param array<mixed> $balanceData
     */
    private function hasRequiredBalanceFields(array $balanceData): bool
    {
        $requiredFields = ['currency', 'total_balance', 'granted_balance', 'topped_up_balance'];
        foreach ($requiredFields as $field) {
            if (!isset($balanceData[$field]) || !is_string($balanceData[$field])) {
                return false;
            }
        }

        return true;
    }

    private function logBalanceRetrievalSuccess(BalanceInfo $balanceInfo, ?string $apiKey): void
    {
        $this->logger->info('Balance retrieved successfully', [
            'is_available' => $balanceInfo->isAvailable(),
            'has_positive_balance' => $balanceInfo->hasPositiveBalance(),
            'api_key_suffix' => $this->getApiKeySuffix($apiKey),
        ]);
    }

    private function logBalanceRetrievalError(\Exception $e, ?string $apiKey): void
    {
        $this->logger->error('Failed to get balance', [
            'error' => $e->getMessage(),
            'api_key_suffix' => $this->getApiKeySuffix($apiKey),
        ]);
    }

    /**
     * 执行带锁的余额操作
     *
     * @template T
     * @param callable(): T $callback
     * @return T
     */
    private function executeWithBalanceLock(?string $apiKey, callable $callback)
    {
        $lockKey = $this->lockManager->generateBalanceLockKey($apiKey);

        if (!$this->lockManager->acquireLock($lockKey)) {
            $this->logger->warning('Balance request already in progress for API key, skipping concurrent call', [
                'api_key_suffix' => $this->getApiKeySuffix($apiKey),
            ]);
            throw new InvalidApiKeyException('Balance request already in progress');
        }

        try {
            return $callback();
        } finally {
            $this->lockManager->releaseLock($lockKey);
        }
    }

    /**
     * 获取并处理余额信息
     */
    private function fetchAndProcessBalance(?string $apiKey): BalanceInfo
    {
        try {
            $this->setApiKeyIfProvided($apiKey);
            $response = $this->fetchBalanceResponse();
            $validatedResponse = $this->validateAndParseBalanceResponse($response);
            $balanceInfo = BalanceInfo::fromArray($validatedResponse);

            $this->logBalanceRetrievalSuccess($balanceInfo, $apiKey);

            return $balanceInfo;
        } catch (\Exception $e) {
            $this->logBalanceRetrievalError($e, $apiKey);
            throw $e;
        }
    }

    /**
     * 获取API密钥后缀用于日志
     */
    private function getApiKeySuffix(?string $apiKey): string
    {
        return null !== $apiKey ? substr($apiKey, -4) : 'default';
    }

    /**
     * @return array<string, float>
     */
    public function getTotalBalance(): array
    {
        // 防止并发查询总余额
        $lockKey = 'deepseek_total_balance_lock';
        if (!$this->lockManager->acquireLock($lockKey)) {
            $this->logger->warning('Total balance calculation already in progress, skipping concurrent call');

            return [];
        }

        try {
            return $this->balanceRepository->getTotalBalanceByCurrency();
        } finally {
            $this->lockManager->releaseLock($lockKey);
        }
    }

    /**
     * 同步所有需要同步的模型和余额数据
     *
     * @return array{models: array<string, array{id: string, object: string, owned_by: string}[]>, balances: array<string, DeepSeekBalance[]>}
     */
    public function syncAllData(): array
    {
        $result = [
            'models' => [],
            'balances' => [],
        ];

        $result['models'] = $this->syncModelsForKeysNeedingSync();
        $result['balances'] = $this->syncBalancesForKeysNeedingSync();

        return $result;
    }

    /**
     * @return array<string, array{id: string, object: string, owned_by: string}[]>
     */
    private function syncModelsForKeysNeedingSync(): array
    {
        $result = [];
        $keysNeedingModelsSync = $this->apiKeyRepository->findKeysNeedingModelsSync();

        foreach ($keysNeedingModelsSync as $apiKey) {
            $modelsData = $this->syncModelsForApiKey($apiKey);
            if (null !== $modelsData) {
                $result[$apiKey->getName()] = $modelsData;
            }
        }

        return $result;
    }

    /**
     * @return array{id: string, object: string, owned_by: string}[]|null
     */
    private function syncModelsForApiKey(DeepSeekApiKey $apiKey): ?array
    {
        try {
            $models = $this->listModels($apiKey->getApiKey());
            $this->syncModelsToDatabase($apiKey, $models);
            $this->updateApiKeyModelsSync($apiKey);

            $this->logModelsSyncSuccess($apiKey, $models);

            return $this->transformModelsToArray($models);
        } catch (\Exception $e) {
            $this->logModelsSyncError($apiKey, $e);

            return null;
        }
    }

    /**
     * 将模型对象转换为数组格式
     *
     * @param ModelInfo[] $models
     * @return array{id: string, object: string, owned_by: string}[]
     */
    private function transformModelsToArray(array $models): array
    {
        return array_map(fn (ModelInfo $model) => [
            'id' => $model->getId(),
            'object' => $model->getObject(),
            'owned_by' => $model->getOwnedBy(),
        ], $models);
    }

    /**
     * 记录模型同步成功
     *
     * @param ModelInfo[] $models
     */
    private function logModelsSyncSuccess(DeepSeekApiKey $apiKey, array $models): void
    {
        $this->logger->info('Models synced for API key', [
            'key_name' => $apiKey->getName(),
            'models_count' => count($models),
        ]);
    }

    /**
     * 记录模型同步错误
     */
    private function logModelsSyncError(DeepSeekApiKey $apiKey, \Exception $e): void
    {
        $this->logger->error('Failed to sync models', [
            'key_name' => $apiKey->getName(),
            'error' => $e->getMessage(),
        ]);
    }

    /**
     * @return array<string, DeepSeekBalance[]>
     */
    private function syncBalancesForKeysNeedingSync(): array
    {
        $result = [];
        $keysNeedingBalanceSync = $this->apiKeyRepository->findKeysNeedingBalanceSync();

        foreach ($keysNeedingBalanceSync as $apiKey) {
            $balances = $this->syncBalanceForApiKey($apiKey);
            if (null !== $balances) {
                $result[$apiKey->getName()] = $balances;
            }
        }

        return $result;
    }

    /**
     * @return DeepSeekBalance[]|null
     */
    private function syncBalanceForApiKey(DeepSeekApiKey $apiKey): ?array
    {
        try {
            $balance = $this->getBalance($apiKey->getApiKey());
            $balances = $this->createBalanceEntities($apiKey, $balance);
            $this->saveBalances($apiKey, $balances);
            $this->updateApiKeyBalanceSync($apiKey);

            $this->logBalanceSyncSuccess($apiKey);

            return $balances;
        } catch (\Exception $e) {
            $this->logBalanceSyncError($apiKey, $e);

            return null;
        }
    }

    /**
     * 记录余额同步成功
     */
    private function logBalanceSyncSuccess(DeepSeekApiKey $apiKey): void
    {
        $this->logger->info('Balance synced for API key', [
            'key_name' => $apiKey->getName(),
        ]);
    }

    /**
     * 记录余额同步错误
     */
    private function logBalanceSyncError(DeepSeekApiKey $apiKey, \Exception $e): void
    {
        $this->logger->error('Failed to sync balance', [
            'key_name' => $apiKey->getName(),
            'error' => $e->getMessage(),
        ]);
    }

    /**
     * @return DeepSeekBalance[]
     */
    private function createBalanceEntities(DeepSeekApiKey $apiKey, BalanceInfo $balance): array
    {
        $balances = [];
        $balanceInfos = $balance->getBalanceInfos();

        foreach ($balanceInfos as $currencyBalance) {
            $balanceEntity = new DeepSeekBalance();
            $balanceEntity->setApiKey($apiKey);
            $balanceEntity->setCurrency($currencyBalance->getCurrency());
            $balanceEntity->setTotalBalance($currencyBalance->getTotalBalance());
            $balanceEntity->setGrantedBalance($currencyBalance->getGrantedBalance());
            $balanceEntity->setToppedUpBalance($currencyBalance->getToppedUpBalance());
            $balances[] = $balanceEntity;
        }

        return $balances;
    }

    /**
     * @param DeepSeekBalance[] $balances
     */
    private function saveBalances(DeepSeekApiKey $apiKey, array $balances): void
    {
        $this->balanceRepository->saveBalances($apiKey, $balances);
        $this->balanceHistoryRepository->recordBalanceChanges($balances);
    }

    private function updateApiKeyBalanceSync(DeepSeekApiKey $apiKey): void
    {
        $apiKey->setLastBalanceSyncTime(new \DateTimeImmutable());
        $this->apiKeyRepository->save($apiKey, true);
    }

    /**
     * @return array<string, BalanceInfo>
     */
    public function getBalanceForAllKeys(): array
    {
        $lockKey = 'deepseek_balance_sync_lock';

        if (!$this->lockManager->acquireLock($lockKey)) {
            $this->logger->warning('Balance sync already in progress, skipping concurrent call');

            return [];
        }

        try {
            $apiKeys = $this->getActiveApiKeysOrThrow();

            return $this->fetchBalanceForAllApiKeys($apiKeys);
        } finally {
            $this->lockManager->releaseLock($lockKey);
        }
    }

    /**
     * @return DeepSeekApiKey[]
     */
    private function getActiveApiKeysOrThrow(): array
    {
        $apiKeys = $this->apiKeyRepository->findActiveKeys();

        if ([] === $apiKeys) {
            throw new InvalidApiKeyException('No API keys configured');
        }

        return $apiKeys;
    }

    /**
     * @param DeepSeekApiKey[] $apiKeys
     * @return array<string, BalanceInfo>
     */
    private function fetchBalanceForAllApiKeys(array $apiKeys): array
    {
        $result = [];

        foreach ($apiKeys as $apiKey) {
            $balance = $this->fetchBalanceForSingleApiKey($apiKey);
            if (null !== $balance) {
                $result[$apiKey->getName()] = $balance;
            }
        }

        return $result;
    }

    private function fetchBalanceForSingleApiKey(DeepSeekApiKey $apiKey): ?BalanceInfo
    {
        try {
            $balance = $this->getBalance($apiKey->getApiKey());
            $this->saveApiKeyBalance($apiKey, $balance);
            $this->apiKeyManager->markKeyAsValid($apiKey->getApiKey());

            return $balance;
        } catch (\Exception $e) {
            $this->handleBalanceFetchError($apiKey, $e);

            return null;
        }
    }

    private function saveApiKeyBalance(DeepSeekApiKey $apiKey, BalanceInfo $balance): void
    {
        $balances = $this->createBalanceEntities($apiKey, $balance);
        $this->saveBalances($apiKey, $balances);
        $this->updateApiKeyBalanceSync($apiKey);
    }

    private function handleBalanceFetchError(DeepSeekApiKey $apiKey, \Exception $e): void
    {
        $this->logger->warning('Failed to get balance for key', [
            'key_name' => $apiKey->getName(),
            'error' => $e->getMessage(),
        ]);

        $this->markInvalidIfUnauthorized($apiKey->getApiKey(), $e);
    }
}
