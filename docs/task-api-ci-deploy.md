# Laravel Task API + GitHub Actions 流程文档

本文档记录本项目从最小 Task API 到 GitHub Actions 测试、构建、代码规范检查和测试环境部署的完整步骤。

## 1. 项目目标

本项目只做一个最小 Laravel Demo，不做复杂业务。

目标功能：

- `GET /api/tasks`：获取任务列表
- `POST /api/tasks`：创建任务
- Feature Test 覆盖 API 行为
- Vite 前端构建
- GitHub Actions 自动执行代码规范检查、测试、构建
- 推送到 `master` 后部署到测试环境

## 2. 新增 Task 数据表

新增迁移文件：

```text
database/migrations/2026_05_05_000000_create_tasks_table.php
```

表结构：

- `id`
- `title`
- `is_completed`，默认 `false`
- `created_at`
- `updated_at`

本地执行迁移：

```bash
php artisan migrate
```

测试环境和 CI 测试使用 SQLite 内存库，不需要手动创建测试数据库。

## 3. 新增 Task 模型

新增文件：

```text
app/Models/Task.php
```

模型配置：

- 允许批量写入 `title`、`is_completed`
- 默认 `is_completed=false`
- 将 `is_completed` 转成 boolean

这样 `POST /api/tasks` 创建任务时，即使请求里没有传 `is_completed`，响应和数据库里也会保持默认未完成状态。

## 4. 启用 API 路由

Laravel 12 默认骨架里没有自动加载 `routes/api.php`，所以在：

```text
bootstrap/app.php
```

加入 API 路由加载：

```php
api: __DIR__.'/../routes/api.php',
```

这样 `/api/*` 路由才会注册。

可用命令确认：

```bash
php artisan route:list --path=api/tasks
```

期望看到：

```text
GET|HEAD   api/tasks
POST       api/tasks
```

## 5. 新增 Task API

新增文件：

```text
routes/api.php
```

接口：

```text
GET /api/tasks
POST /api/tasks
```

`GET /api/tasks`：

- 按最新创建时间倒序返回任务列表

`POST /api/tasks`：

- 校验 `title` 必填、字符串、最大 255 字符
- 创建任务
- 返回 HTTP `201 Created`

请求示例：

```bash
curl -X POST http://localhost/api/tasks \
  -H "Content-Type: application/json" \
  -d '{"title":"Ship Laravel demo"}'
```

响应示例：

```json
{
  "id": 1,
  "title": "Ship Laravel demo",
  "is_completed": false,
  "created_at": "2026-05-05T00:00:00.000000Z",
  "updated_at": "2026-05-05T00:00:00.000000Z"
}
```

## 6. 新增 Feature Test

测试文件：

```text
tests/Feature/TaskApiTest.php
```

覆盖内容：

1. `test_it_lists_tasks`
   - 创建一条任务
   - 请求 `GET /api/tasks`
   - 断言返回 `200`
   - 断言返回 1 条数据
   - 断言 JSON 字段结构正确

2. `test_it_creates_a_task`
   - 请求 `POST /api/tasks`
   - 断言返回 `201`
   - 断言响应包含任务标题和 `is_completed=false`
   - 断言数据库写入成功

3. `test_it_requires_a_title_when_creating_a_task`
   - 请求 `POST /api/tasks` 但不传 `title`
   - 断言返回 `422`
   - 断言返回 `title` 校验错误
   - 断言数据库没有新增任务

运行测试：

```bash
composer test
```

当前通过结果：

```text
4 passed, 19 assertions
```

## 7. 前端 Vite 构建

项目已有 Vite 配置：

```text
vite.config.js
```

构建脚本在：

```text
package.json
```

脚本：

```json
{
  "scripts": {
    "build": "vite build",
    "dev": "vite"
  }
}
```

本地执行：

```bash
npm run build
```

构建产物会生成到：

```text
public/build
```

## 8. 代码规范检查

项目使用 Laravel Pint 做 PHP 代码规范检查。

本地检查：

```bash
./vendor/bin/pint --test
```

本地自动修复：

```bash
./vendor/bin/pint
```

GitHub Actions 中使用 `--test`，只检查，不自动修改代码。

## 9. GitHub Actions CI

Workflow 文件：

```text
.github/workflows/ci.yml
```

触发条件：

```yaml
on:
  push:
    branches: [master]
  pull_request:
```

也就是说：

- 推送到 `master` 会触发 CI
- 创建或更新 Pull Request 会触发 CI

CI job 名称：

```text
test-and-build
```

执行步骤：

1. Checkout 代码
2. 安装 PHP 8.2
3. 安装 Node.js 22
4. 安装 Composer 依赖
5. 安装 npm 依赖
6. 准备 `.env`
7. 生成 Laravel APP_KEY
8. 执行 Pint 代码规范检查
9. 执行 Laravel 测试
10. 执行 Vite 前端构建

CI 中主要命令：

```bash
composer install --no-interaction --prefer-dist --no-progress
npm ci
cp .env.example .env
php artisan key:generate
./vendor/bin/pint --test
composer test
npm run build
```

## 10. CI 测试环境变量

GitHub Actions 中为测试配置了环境变量：

```yaml
APP_ENV: testing
CACHE_STORE: array
DB_CONNECTION: sqlite
DB_DATABASE: ':memory:'
MAIL_MAILER: array
QUEUE_CONNECTION: sync
SESSION_DRIVER: array
```

作用：

- 使用 SQLite 内存数据库跑测试
- 不依赖真实数据库
- 队列同步执行
- 邮件使用 array driver
- session 使用 array driver

## 11. 部署到测试环境

部署 job 名称：

```text
deploy-testing
```

执行条件：

- `test-and-build` 成功
- 当前事件是 `push`
- 当前分支是 `master`

配置：

```yaml
needs: test-and-build
if: github.event_name == 'push' && github.ref == 'refs/heads/master'
environment: testing
```

也就是说，只有代码规范检查、测试、前端构建都通过后，才会部署测试环境。

## 12. 部署需要的 GitHub Secrets

部署通过 SSH 连接测试服务器，需要在 GitHub 仓库中配置 secrets。

路径：

```text
GitHub Repository -> Settings -> Secrets and variables -> Actions
```

需要配置：

```text
TEST_SERVER_HOST
TEST_SERVER_USER
TEST_SERVER_SSH_KEY
TEST_DEPLOY_PATH
```

含义：

| Secret | 说明 |
| --- | --- |
| `TEST_SERVER_HOST` | 测试服务器 IP 或域名 |
| `TEST_SERVER_USER` | SSH 用户名 |
| `TEST_SERVER_SSH_KEY` | SSH 私钥内容 |
| `TEST_DEPLOY_PATH` | 服务器上的项目目录 |

因为 workflow 使用了：

```yaml
environment: testing
```

所以也可以把 secrets 配在 GitHub 的 `testing` Environment 下面。

## 13. 部署脚本做了什么

部署使用：

```yaml
appleboy/ssh-action@v1.2.0
```

登录服务器后执行：

```bash
cd "$TEST_DEPLOY_PATH"
git fetch --prune
git reset --hard origin/master
composer install --no-interaction --prefer-dist --no-dev --optimize-autoloader
npm ci
npm run build
php artisan migrate --force
php artisan config:cache
php artisan route:cache
```

说明：

- `git fetch --prune`：拉取远程更新并清理无效引用
- `git reset --hard origin/master`：让服务器代码和远程 `master` 完全一致
- `composer install --no-dev`：安装生产依赖
- `npm ci`：按 `package-lock.json` 安装前端依赖
- `npm run build`：构建前端资源
- `php artisan migrate --force`：执行数据库迁移
- `php artisan config:cache`：缓存配置
- `php artisan route:cache`：缓存路由

## 14. 常见错误：missing server host

错误日志：

```text
Error: missing server host
```

原因：

```text
TEST_SERVER_HOST 没有配置，或者 GitHub Actions 没有读取到。
```

解决：

1. 打开 GitHub 仓库
2. 进入 `Settings`
3. 进入 `Secrets and variables`
4. 进入 `Actions`
5. 新增或检查 `TEST_SERVER_HOST`

如果使用 GitHub Environment `testing`，也要确认 secret 是配置在正确的 environment 里。

当前 workflow 已加入 `Validate deployment secrets` 步骤，会提前检查：

```text
TEST_SERVER_HOST
TEST_SERVER_USER
TEST_SERVER_SSH_KEY
TEST_DEPLOY_PATH
```

如果缺少配置，会明确输出缺少哪个 secret。

## 15. 常见错误：Missing secret: TEST_SERVER_HOST

错误日志：

```text
Run test -n "$TEST_SERVER_HOST" || (echo "Missing secret: TEST_SERVER_HOST" && exit 1)
Missing secret: TEST_SERVER_HOST
Error:
Process completed with exit code 1.
```

原因：

```text
GitHub Actions 已经执行到部署前的 secrets 校验步骤，但是没有读取到 TEST_SERVER_HOST。
```

这通常表示：

- `TEST_SERVER_HOST` 没有配置
- secret 名字写错了
- secret 配置到了错误的位置
- workflow 使用了 `environment: testing`，但 secrets 没有配到 `testing` Environment，或者 repository secrets 中也没有

配置 repository secret：

```text
GitHub Repository
-> Settings
-> Secrets and variables
-> Actions
-> New repository secret
```

新增：

```text
Name: TEST_SERVER_HOST
Value: 测试服务器 IP 或域名
```

如果使用 GitHub Environment `testing`，配置路径是：

```text
GitHub Repository
-> Settings
-> Environments
-> testing
-> Environment secrets
-> Add secret
```

新增：

```text
Name: TEST_SERVER_HOST
Value: 测试服务器 IP 或域名
```

同时确认这 4 个 secrets 都存在：

```text
TEST_SERVER_HOST
TEST_SERVER_USER
TEST_SERVER_SSH_KEY
TEST_DEPLOY_PATH
```

说明：

- `TEST_SERVER_HOST` 是服务器 IP 或域名，例如 `192.168.1.10` 或 `test.example.com`
- 不要写成 `http://example.com`
- 不要在名称前后加空格
- secret 名称必须和 workflow 中完全一致
- 如果部署 job 绑定了 `environment: testing`，优先检查 `testing` Environment 下是否配置完整

## 16. 本地提交和推送流程

查看状态：

```bash
git status --short --branch
```

暂存变更：

```bash
git add .
```

提交：

```bash
git commit -m "Add task API and CI workflow"
```

推送到远程 `master`：

```bash
git push origin master
```

后续修改 workflow 的提交示例：

```bash
git add .github/workflows/ci.yml
git commit -m "Validate deployment secrets"
git push origin master
```

## 17. 本地完整验证命令

提交前建议执行：

```bash
./vendor/bin/pint --test
composer test
npm run build
```

全部通过后再提交。

## 18. 当前关键文件清单

```text
app/Models/Task.php
bootstrap/app.php
database/migrations/2026_05_05_000000_create_tasks_table.php
routes/api.php
tests/Feature/TaskApiTest.php
.github/workflows/ci.yml
package-lock.json
```

## 19. 注意事项

- CI 使用 `npm ci`，所以必须提交 `package-lock.json`
- 当前仓库分支是 `master`，workflow 也按 `master` 配置
- 测试环境部署依赖 GitHub secrets，没配置完整时部署 job 会失败
- 服务器上的 `TEST_DEPLOY_PATH` 必须是已经初始化好的 Git 仓库
- 服务器需要能执行 `composer`、`npm`、`php artisan`
