# DeepSeekApiBundle

[English](README.md) | [中文](README.zh-CN.md)

一个用于集成 DeepSeek API 的 Symfony 包，提供 API 密钥管理、模型列表和余额检查功能。

## 功能特性

- 多个 API 密钥管理和自动轮换
- 列出每个 API 密钥的可用模型
- 检查多种货币的账户余额
- 验证 API 密钥
- 用于 API 交互的命令行工具

## 安装

```bash
composer require tourze/deep-seek-api-bundle
```

## 配置

在 Symfony 配置中配置您的 API 密钥：

```yaml
# config/packages/deep_seek_api.yaml
deep_seek_api:
    api_keys:
        - 'your-api-key-1'
        - 'your-api-key-2'
```

## 命令行工具

### 分析余额趋势

分析余额变化和使用模式。

```bash
# 分析最近 7 天的余额
bin/console deep-seek:analyze-balance

# 分析指定天数的余额
bin/console deep-seek:analyze-balance --days=30

# 使用特定时间间隔（每小时、每天、每周）
bin/console deep-seek:analyze-balance --interval=daily

# 显示特定货币
bin/console deep-seek:analyze-balance --currency=CNY

# 显示重大变化警告
bin/console deep-seek:analyze-balance --show-alerts
```

### 管理 API 密钥

管理 DeepSeek API 密钥，包括列表、添加和停用。

```bash
# 列出所有 API 密钥
bin/console deep-seek:api-key list

# 添加新的 API 密钥
bin/console deep-seek:api-key add sk-your-api-key --name="生产密钥"

# 停用 API 密钥
bin/console deep-seek:api-key deactivate sk-your-api-key

# 激活 API 密钥
bin/console deep-seek:api-key activate sk-your-api-key

# 显示 API 密钥详情
bin/console deep-seek:api-key show sk-your-api-key
```

### 检查余额

检查所有或特定 API 密钥的当前余额。

```bash
# 检查所有密钥的余额
bin/console deepseek:balance:check

# 显示详细输出
bin/console deepseek:balance:check --verbose
```

### 列出模型

列出 DeepSeek API 的所有可用模型。

```bash
# 列出所有密钥的模型
bin/console deepseek:models:list

# 以 JSON 格式列出模型
bin/console deepseek:models:list --json
```

### 验证 API 密钥

验证 API 密钥是否正常工作。

```bash
# 验证所有 API 密钥
bin/console deepseek:api-key:validate --all-keys

# 验证特定 API 密钥
bin/console deepseek:api-key:validate --key=sk-your-api-key
```

### 同步数据

从 DeepSeek API 同步模型和余额数据。

```bash
# 同步所有数据（模型和余额）
bin/console deep-seek:sync-data

# 仅同步模型
bin/console deep-seek:sync-data --models-only

# 仅同步余额
bin/console deep-seek:sync-data --balances-only

# 强制同步（即使最近已同步）
bin/console deep-seek:sync-data --force
```

## 代码使用

### 使用 DeepSeekService

```php
use Tourze\DeepSeekApiBundle\Service\DeepSeekService;

class MyService
{
    public function __construct(
        private DeepSeekService $deepSeekService
    ) {}

    public function example(): void
    {
        // 列出模型
        $models = $this->deepSeekService->listModels();
        
        // 检查余额
        $balance = $this->deepSeekService->getBalance();
        
        // 验证 API 密钥
        $isValid = $this->deepSeekService->validateApiKey('your-api-key');
    }
}
```

### 管理 API 密钥

```php
use Tourze\DeepSeekApiBundle\Service\ApiKeyManager;

class MyKeyManager
{
    public function __construct(
        private ApiKeyManager $apiKeyManager
    ) {}

    public function example(): void
    {
        // 添加新的 API 密钥
        $this->apiKeyManager->addApiKey('new-api-key');
        
        // 轮换到下一个密钥
        $this->apiKeyManager->rotateToNextKey();
        
        // 获取当前密钥
        $currentKey = $this->apiKeyManager->getCurrentKey();
        
        // 将密钥标记为无效
        $this->apiKeyManager->markKeyAsInvalid('bad-key');
    }
}
```