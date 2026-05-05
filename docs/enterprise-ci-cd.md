# 企业级 CI/CD 设计建议

本文档说明企业开发中 CI/CD 应该如何设计，并和当前 Laravel Demo 的简单部署方式做区分。

## 1. CI/CD 的职责

CI 和 CD 应该分工清楚。

CI 负责判断代码能不能合并：

- 代码格式是否正确
- 静态分析是否通过
- 自动化测试是否通过
- 前端构建是否通过
- 依赖是否安全
- 产物是否可以构建

CD 负责判断代码能不能发布：

- 部署到哪个环境
- 谁可以批准发布
- 发布哪个版本
- 如何健康检查
- 如何回滚
- 如何记录发布历史

不要把 CI 和 CD 混成一个没有边界的脚本。

## 2. 推荐整体流程

企业项目一般不建议直接 push 到主分支后部署生产。

推荐流程：

```text
feature/*
  -> Pull Request
  -> CI 检查
  -> Code Review
  -> merge 到 develop / master / main
  -> 自动部署测试环境
  -> 部署预发环境
  -> 人工审批
  -> 部署生产环境
```

更完整的流程：

```text
开发分支
  -> Pull Request
  -> 代码规范检查
  -> 静态分析
  -> 单元测试
  -> Feature Test
  -> 前端构建
  -> 安全扫描
  -> Code Review
  -> 合并主干
  -> 构建不可变制品
  -> 部署测试环境
  -> 部署预发环境
  -> 人工审批
  -> 部署生产环境
  -> 健康检查
  -> 监控告警
```

## 3. 分支策略

常见分支：

```text
feature/*     功能开发分支
bugfix/*      问题修复分支
develop       集成分支，可选
master/main   主干分支
release/*     发布分支，可选
hotfix/*      线上紧急修复分支
```

简单团队可以使用 GitHub Flow：

```text
feature/* -> Pull Request -> main/master
```

复杂团队可以使用：

```text
feature/* -> develop -> release/* -> master/main
```

建议：

- 所有业务代码必须通过 Pull Request
- 主分支禁止直接 push
- 主分支开启 branch protection
- CI 必须通过才能合并
- 至少一名 reviewer approve 后才能合并

## 4. CI 应该检查什么

Laravel 企业项目建议至少包含：

```bash
composer validate --strict
composer install --no-interaction --prefer-dist --no-progress
./vendor/bin/pint --test
./vendor/bin/phpstan analyse
php artisan test
npm ci
npm run build
composer audit
npm audit
```

对应检查项：

| 检查项 | 工具示例 | 目的 |
| --- | --- | --- |
| Composer 配置校验 | `composer validate --strict` | 检查 composer.json 是否规范 |
| PHP 代码格式 | Laravel Pint | 统一代码风格 |
| 静态分析 | PHPStan / Larastan | 提前发现类型和逻辑问题 |
| 自动化测试 | PHPUnit / Pest | 保证业务行为正确 |
| 前端构建 | Vite | 确认前端资源可构建 |
| PHP 依赖安全 | `composer audit` | 检查已知漏洞 |
| npm 依赖安全 | `npm audit` | 检查前端依赖漏洞 |
| Docker 构建 | Docker build | 确认部署产物可生成 |

## 5. Laravel 项目推荐工具

建议工具组合：

```text
Pint              PHP 代码格式
PHPStan/Larastan  静态分析
PHPUnit/Pest      自动化测试
Composer audit    PHP 依赖安全检查
npm audit         前端依赖安全检查
Rector            可选，自动重构和升级
Sentry            可选，线上错误追踪
```

可以按项目阶段逐步引入：

1. 先接 Pint、测试、前端构建
2. 再接 PHPStan/Larastan
3. 再接依赖安全扫描
4. 再接 Docker 镜像构建和制品管理

## 6. CD 应该分环境

企业项目至少建议三个环境：

```text
testing / dev      测试环境
staging / preprod  预发环境
production         生产环境
```

推荐部署策略：

| 环境 | 触发方式 | 审批 | 说明 |
| --- | --- | --- | --- |
| testing | 合并主干后自动部署 | 不需要 | 给测试人员验证 |
| staging | 手动触发或打 tag | 可选 | 尽量和生产一致 |
| production | 手动触发 | 必须 | 需要审批和回滚方案 |

## 7. 不建议生产服务器直接构建

Demo 项目可以这样部署：

```text
GitHub Actions -> SSH -> 服务器 git pull/reset -> composer install -> npm build
```

但企业生产环境不建议在服务器上临时构建。

不推荐：

```bash
git reset --hard origin/master
composer install
npm ci
npm run build
php artisan migrate
```

原因：

- 每台服务器构建结果可能不一致
- 部署速度慢
- 回滚困难
- 服务器需要安装太多构建工具
- 构建失败会直接影响部署过程

企业更推荐：

```text
CI 构建不可变制品
-> 上传制品仓库
-> CD 拉取固定版本
-> 部署
```

不可变制品可以是：

```text
Docker image
tar.gz release package
Composer artifact
前端静态资源包
```

## 8. 推荐企业部署方式

更稳妥的流程：

```text
GitHub Actions
  -> 运行 CI
  -> 构建 Docker image
  -> 推送到 Container Registry
  -> 部署到 Kubernetes / ECS / VM
  -> 健康检查
  -> 切换流量
  -> 失败回滚
```

发布版本应该是固定的，例如：

```text
registry.example.com/app:git-sha
registry.example.com/app:v1.2.3
```

不要用不稳定标签直接部署生产：

```text
latest
```

生产部署应该知道“这次到底部署了哪个 commit 或哪个 tag”。

## 9. GitHub Actions 推荐拆分

企业项目可以拆成多个 workflow：

```text
.github/workflows/ci.yml
.github/workflows/deploy-testing.yml
.github/workflows/deploy-staging.yml
.github/workflows/deploy-production.yml
```

示例职责：

| Workflow | 触发 | 职责 |
| --- | --- | --- |
| `ci.yml` | PR / push | 代码规范、静态分析、测试、构建 |
| `deploy-testing.yml` | push 到主干 | 自动部署测试环境 |
| `deploy-staging.yml` | 手动触发 / tag | 部署预发环境 |
| `deploy-production.yml` | 手动触发 | 审批后部署生产 |

## 10. GitHub Environments

GitHub Environments 适合做环境隔离。

建议创建：

```text
testing
staging
production
```

每个 environment 可以配置：

- environment secrets
- required reviewers
- deployment branches
- protection rules

生产环境建议开启：

```text
Required reviewers
Deployment branch restrictions
Environment secrets
```

这样可以做到：

- 生产密钥不暴露给测试部署
- 生产发布需要人工批准
- 只有指定分支或 tag 可以部署生产

## 11. Secrets 管理

企业项目不要把敏感配置写进代码仓库。

敏感信息包括：

```text
数据库密码
API Key
SSH 私钥
云厂商 AK/SK
第三方服务 Token
加密密钥
```

推荐存放位置：

```text
GitHub Environment Secrets
云厂商 Secret Manager
Kubernetes Secrets
Vault
```

建议：

- 不同环境使用不同 secrets
- 生产 secrets 只给 production environment
- 定期轮换密钥
- 不在 workflow 日志中打印 secrets
- 不把 `.env` 提交到 Git

## 12. 数据库迁移策略

数据库迁移是生产部署中风险较高的一步。

建议：

- 迁移必须向后兼容
- 避免一次性大表锁表操作
- 删除字段分两次发布
- 复杂迁移先在 staging 演练
- 生产迁移需要备份和回滚预案

推荐模式：

```text
先加字段 -> 发布代码兼容新旧字段 -> 回填数据 -> 切换代码 -> 删除旧字段
```

不要轻易在生产部署中直接做高风险迁移。

## 13. 发布审批

生产部署建议必须审批。

审批人可以是：

```text
Tech Lead
项目负责人
值班工程师
发布负责人
```

审批前需要确认：

- CI 全部通过
- staging 已验证
- 影响范围明确
- 数据库迁移风险已评估
- 回滚方案明确
- 发布窗口合适

## 14. 健康检查

部署后必须确认服务可用。

常见健康检查：

```text
HTTP /up
HTTP /health
数据库连接
队列连接
缓存连接
关键接口 smoke test
```

Laravel 默认有：

```text
/up
```

CD 可以在部署后请求：

```bash
curl -f https://example.com/up
```

如果健康检查失败：

- 标记部署失败
- 阻止继续切流量
- 触发回滚或通知人工处理

## 15. 回滚策略

企业发布必须有回滚方案。

常见回滚方式：

```text
回滚到上一个 Docker image
回滚到上一个 release 目录
回滚 Kubernetes Deployment revision
重新部署上一个 Git tag
```

注意：

- 代码回滚容易
- 数据库回滚困难
- 所以数据库迁移必须谨慎

推荐保留：

```text
最近 N 个 release
最近 N 个镜像 tag
部署记录
迁移记录
```

## 16. 可观测性

企业项目发布后要能快速知道有没有问题。

建议接入：

```text
应用日志
错误追踪
性能监控
请求成功率
响应时间
队列积压
数据库指标
告警通知
```

常见工具：

```text
Sentry
Prometheus
Grafana
ELK / OpenSearch
Datadog
New Relic
CloudWatch
```

## 17. 企业级 GitHub Actions 示例结构

CI 示例：

```yaml
name: CI

on:
  pull_request:
  push:
    branches: [master]

jobs:
  checks:
    runs-on: ubuntu-latest

    steps:
      - uses: actions/checkout@v4

      - uses: shivammathur/setup-php@v2
        with:
          php-version: '8.2'
          coverage: none

      - uses: actions/setup-node@v4
        with:
          node-version: '22'
          cache: npm

      - run: composer validate --strict
      - run: composer install --no-interaction --prefer-dist --no-progress
      - run: npm ci
      - run: ./vendor/bin/pint --test
      - run: ./vendor/bin/phpstan analyse
      - run: php artisan test
      - run: npm run build
      - run: composer audit
      - run: npm audit --audit-level=high
```

生产部署示例：

```yaml
name: Deploy Production

on:
  workflow_dispatch:
    inputs:
      image_tag:
        description: Image tag to deploy
        required: true

jobs:
  deploy:
    runs-on: ubuntu-latest
    environment: production

    steps:
      - name: Deploy image
        run: |
          echo "Deploy ${{ inputs.image_tag }} to production"
```

实际项目中，部署步骤会替换成 Kubernetes、云厂商 CLI、Argo CD、Terraform 或内部发布平台。

## 18. Demo 和企业版对比

| 维度 | Demo | 企业项目 |
| --- | --- | --- |
| 触发方式 | push master | PR + 主干 + 手动发布 |
| 测试 | Feature Test | 单元、Feature、集成、端到端 |
| 代码规范 | Pint | Pint + 静态分析 |
| 构建 | npm build | 前后端制品构建 |
| 部署 | SSH 到服务器执行命令 | 不可变制品部署 |
| secrets | GitHub secrets | 分环境 secrets / Secret Manager |
| 生产审批 | 无 | 必须 |
| 回滚 | 手动处理 | 明确回滚机制 |
| 监控 | 无 | 日志、指标、告警 |

## 19. 推荐落地顺序

不要一次性把所有企业实践都加上。

推荐顺序：

1. PR 必须跑 CI
2. 主分支保护
3. Pint、测试、前端构建
4. PHPStan/Larastan
5. 自动部署测试环境
6. staging 环境
7. production environment 审批
8. 制品化部署
9. 健康检查和回滚
10. 监控和告警

## 20. 总结

企业 CI/CD 的重点不是“能自动执行脚本”，而是：

- 代码进入主干前要被充分检查
- 发布过程要可控
- 每次发布要知道版本
- 环境和 secrets 要隔离
- 生产发布要审批
- 出问题要能快速回滚
- 发布后要能监控

Demo 可以使用：

```text
GitHub Actions -> SSH -> 测试服务器
```

企业项目更推荐：

```text
PR 检查
-> 自动测试
-> 构建不可变制品
-> 分环境部署
-> 生产审批
-> 健康检查
-> 可观测和回滚
```
