# Laravel Task API Demo 完整步骤流程

本文档是一份从零完成 Laravel Task API Demo 的流程说明，覆盖代码实现、测试、前端构建、GitHub Actions、测试环境部署和常见问题处理。

## 1. Demo 目标

实现一个最小 Laravel Demo：

- 提供 Task API
- 编写 Feature Test
- 接入 PHP 代码规范检查
- 接入 Vite 前端构建
- 使用 GitHub Actions 自动跑 CI
- 推送到 `master` 后部署到测试环境

API 范围：

```text
GET  /api/tasks
POST /api/tasks
```

## 2. 环境准备

本地需要：

```text
PHP >= 8.2
Composer
Node.js
npm
SQLite extension
Git
```

安装依赖：

```bash
composer install
npm install
```

准备本地环境文件：

```bash
cp .env.example .env
php artisan key:generate
```

如果本地使用 SQLite，可以创建数据库文件：

```bash
touch database/database.sqlite
```

`.env` 中确认：

```env
DB_CONNECTION=sqlite
```

## 3. 创建 Task 数据表

新增迁移文件：

```text
database/migrations/2026_05_05_000000_create_tasks_table.php
```

迁移内容：

```php
Schema::create('tasks', function (Blueprint $table) {
    $table->id();
    $table->string('title');
    $table->boolean('is_completed')->default(false);
    $table->timestamps();
});
```

执行迁移：

```bash
php artisan migrate
```

## 4. 创建 Task 模型

新增文件：

```text
app/Models/Task.php
```

模型职责：

- 允许写入 `title`
- 允许写入 `is_completed`
- 默认 `is_completed=false`
- 将 `is_completed` 转成 boolean

关键配置：

```php
protected $attributes = [
    'is_completed' => false,
];

protected $fillable = [
    'title',
    'is_completed',
];

protected function casts(): array
{
    return [
        'is_completed' => 'boolean',
    ];
}
```

## 5. 启用 API 路由

Laravel 12 默认项目中需要显式启用 API 路由。

修改文件：

```text
bootstrap/app.php
```

在 `withRouting` 中加入：

```php
api: __DIR__.'/../routes/api.php',
```

完整效果类似：

```php
->withRouting(
    web: __DIR__.'/../routes/web.php',
    api: __DIR__.'/../routes/api.php',
    commands: __DIR__.'/../routes/console.php',
    health: '/up',
)
```

## 6. 编写 Task API

新增文件：

```text
routes/api.php
```

实现两个接口：

```php
use App\Models\Task;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/tasks', function () {
    return Task::query()
        ->latest()
        ->get();
});

Route::post('/tasks', function (Request $request) {
    $attributes = $request->validate([
        'title' => ['required', 'string', 'max:255'],
    ]);

    return response()->json(Task::create($attributes), 201);
});
```

确认路由：

```bash
php artisan route:list --path=api/tasks
```

期望结果：

```text
GET|HEAD   api/tasks
POST       api/tasks
```

## 7. 编写 Feature Test

测试文件：

```text
tests/Feature/TaskApiTest.php
```

测试覆盖：

- 能获取任务列表
- 能创建任务
- 创建任务时必须传 `title`

运行测试：

```bash
composer test
```

当前结果：

```text
4 passed, 19 assertions
```

## 8. 前端 Vite 构建

确认 `package.json` 中有：

```json
{
  "scripts": {
    "build": "vite build",
    "dev": "vite"
  }
}
```

本地构建：

```bash
npm run build
```

构建成功后会生成：

```text
public/build/manifest.json
public/build/assets/*
```

## 9. 代码规范检查

Laravel 项目使用 Pint 检查 PHP 代码规范。

只检查：

```bash
./vendor/bin/pint --test
```

自动修复：

```bash
./vendor/bin/pint
```

CI 中使用：

```bash
./vendor/bin/pint --test
```

## 10. 本地完整验证

提交前执行：

```bash
./vendor/bin/pint --test
composer test
npm run build
```

全部通过后再提交代码。

## 11. 新增 GitHub Actions

新增 workflow：

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

CI job：

```text
test-and-build
```

CI 执行顺序：

1. 拉取代码
2. 安装 PHP 8.2
3. 安装 Node.js 22
4. 安装 Composer 依赖
5. 安装 npm 依赖
6. 准备 Laravel `.env`
7. 生成 APP_KEY
8. 执行 Pint 代码规范检查
9. 执行 Feature Test
10. 执行 Vite 前端构建

核心命令：

```bash
composer install --no-interaction --prefer-dist --no-progress
npm ci
cp .env.example .env
php artisan key:generate
./vendor/bin/pint --test
composer test
npm run build
```

## 12. CI 测试环境配置

CI 使用 SQLite 内存数据库跑测试。

workflow 中配置：

```yaml
env:
  APP_ENV: testing
  CACHE_STORE: array
  DB_CONNECTION: sqlite
  DB_DATABASE: ':memory:'
  MAIL_MAILER: array
  QUEUE_CONNECTION: sync
  SESSION_DRIVER: array
```

好处：

- 不依赖 MySQL
- 不需要额外数据库服务
- 测试运行快
- 每次测试数据都是干净的

## 13. 添加测试环境部署

新增部署 job：

```text
deploy-testing
```

部署条件：

```yaml
needs: test-and-build
if: github.event_name == 'push' && github.ref == 'refs/heads/master'
environment: testing
```

含义：

- 必须等 CI 成功后才部署
- 只在 push 到 `master` 时部署
- Pull Request 不部署
- 部署绑定 GitHub Environment `testing`

## 14. 配置部署 Secrets

部署通过 SSH 登录测试服务器，需要配置 GitHub secrets。

Repository secrets 配置路径：

```text
GitHub Repository
-> Settings
-> Secrets and variables
-> Actions
-> New repository secret
```

如果使用 Environment secrets，配置路径：

```text
GitHub Repository
-> Settings
-> Environments
-> testing
-> Environment secrets
-> Add secret
```

需要配置：

```text
TEST_SERVER_HOST
TEST_SERVER_USER
TEST_SERVER_SSH_KEY
TEST_DEPLOY_PATH
```

说明：

| Secret | 示例 | 说明 |
| --- | --- | --- |
| `TEST_SERVER_HOST` | `192.168.1.10` | 测试服务器 IP 或域名 |
| `TEST_SERVER_USER` | `deploy` | SSH 用户名 |
| `TEST_SERVER_SSH_KEY` | 私钥内容 | 用于登录服务器的 SSH private key |
| `TEST_DEPLOY_PATH` | `/var/www/ldemo` | 服务器上的项目目录 |

注意：

- `TEST_SERVER_HOST` 不要写 `http://`
- secret 名称必须和 workflow 完全一致
- 如果 workflow 绑定 `environment: testing`，优先检查 `testing` Environment 下的 secrets

## 15. 部署脚本流程

部署使用：

```yaml
appleboy/ssh-action@v1.2.0
```

服务器上执行：

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

每一步作用：

| 步骤 | 作用 |
| --- | --- |
| `cd "$TEST_DEPLOY_PATH"` | 进入服务器项目目录 |
| `git fetch --prune` | 拉取远程更新 |
| `git reset --hard origin/master` | 让服务器代码和远程 `master` 一致 |
| `composer install --no-dev` | 安装生产 PHP 依赖 |
| `npm ci` | 按 lockfile 安装前端依赖 |
| `npm run build` | 构建前端资源 |
| `php artisan migrate --force` | 执行数据库迁移 |
| `php artisan config:cache` | 缓存配置 |
| `php artisan route:cache` | 缓存路由 |

## 16. 常见问题：missing server host

错误：

```text
Error: missing server host
```

原因：

```text
appleboy/ssh-action 没有拿到服务器 host。
```

通常是 `TEST_SERVER_HOST` 没有配置或配置位置不对。

解决：

1. 检查 repository secrets
2. 检查 `testing` Environment secrets
3. 确认 secret 名称是 `TEST_SERVER_HOST`
4. 确认值是 IP 或域名，不包含 `http://`

## 17. 常见问题：Missing secret: TEST_SERVER_HOST

错误：

```text
Run test -n "$TEST_SERVER_HOST" || (echo "Missing secret: TEST_SERVER_HOST" && exit 1)
Missing secret: TEST_SERVER_HOST
Process completed with exit code 1.
```

原因：

```text
workflow 中的 Validate deployment secrets 步骤提前检查失败。
```

这说明部署 job 已启动，但 GitHub Actions 没有读到 `TEST_SERVER_HOST`。

处理方式：

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

如果使用 `testing` Environment，则去：

```text
GitHub Repository
-> Settings
-> Environments
-> testing
-> Environment secrets
-> Add secret
```

同时确认：

```text
TEST_SERVER_HOST
TEST_SERVER_USER
TEST_SERVER_SSH_KEY
TEST_DEPLOY_PATH
```

都已经配置完整。

## 18. Git 提交和推送

查看状态：

```bash
git status --short --branch
```

暂存：

```bash
git add .
```

提交：

```bash
git commit -m "Add task API and CI workflow"
```

推送：

```bash
git push origin master
```

推送成功后，GitHub Actions 会自动运行。

## 19. GitHub Actions 验证

推送后打开：

```text
GitHub Repository -> Actions
```

检查 workflow：

```text
CI
```

期望结果：

- `test-and-build` 成功
- `deploy-testing` 成功，前提是测试环境 secrets 已配置完整

如果 `test-and-build` 失败：

- 看 Pint 是否报格式错误
- 看 Feature Test 是否失败
- 看 `npm run build` 是否失败

如果 `deploy-testing` 失败：

- 优先检查 secrets
- 再检查服务器 SSH 权限
- 再检查服务器项目目录和环境命令

## 20. 当前 Demo 关键文件

```text
app/Models/Task.php
bootstrap/app.php
database/migrations/2026_05_05_000000_create_tasks_table.php
routes/api.php
tests/Feature/TaskApiTest.php
.github/workflows/ci.yml
package.json
package-lock.json
vite.config.js
```

## 21. 最终验收清单

本地：

- `./vendor/bin/pint --test` 通过
- `composer test` 通过
- `npm run build` 通过
- `php artisan route:list --path=api/tasks` 能看到两个 API

GitHub：

- push 到 `master` 后自动触发 CI
- CI 中代码规范检查通过
- CI 中测试通过
- CI 中前端构建通过
- secrets 配置完整后，测试环境部署通过

API：

- `GET /api/tasks` 返回任务列表
- `POST /api/tasks` 能创建任务
- `POST /api/tasks` 缺少 `title` 返回 `422`

## 22. 后续可选优化

当前 Demo 已满足最小要求。后续如果要扩展，可以考虑：

- 增加 `PATCH /api/tasks/{task}` 更新完成状态
- 增加 `DELETE /api/tasks/{task}` 删除任务
- 增加 TaskFactory
- 增加 API Resource 统一响应格式
- 将部署脚本改成 release 目录软链接部署
- 增加 Actions 缓存 Composer vendor
