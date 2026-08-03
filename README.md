# Zap PHP Framework

轻量级 PHP MVC 框架，开箱即用，适合快速开发中小型 Web 应用。

## 环境要求

- PHP >= 7.4
- PDO 扩展（MySQL / PostgreSQL / SQLite）
- OpenSSL 扩展（可选，加密模块需要）
- Redis 扩展或 Predis（可选，Redis 缓存需要）
- Monolog（可选，高级日志功能需要）

## 安装

```bash
composer require zapj/zap-php-framework
```

## 项目目录结构

```
project/
├── app/
│   ├── controllers/       # 控制器
│   ├── models/            # 模型
│   ├── middlewares/       # 中间件
│   └── views/             # 视图模板
├── config/                # 配置文件
│   ├── config.php         # 应用配置
│   ├── database.php       # 数据库配置
│   ├── log.php            # 日志配置
│   ├── cache.php          # 缓存配置
│   └── route.php          # 路由配置
├── public/                # 入口目录
│   └── index.php          # 入口文件
├── var/
│   └── cache/             # 缓存文件
├── vendor/
└── composer.json
```

## 快速开始

### 入口文件 `public/index.php`

```php
<?php
error_reporting(E_ALL);
ini_set('display_errors', true);

define('ROOT_PATH', dirname(__DIR__));
define('APP_PATH', ROOT_PATH . DIRECTORY_SEPARATOR . 'app');
define('CONFIG_PATH', ROOT_PATH . DIRECTORY_SEPARATOR . 'config');
define('VAR_PATH', ROOT_PATH . DIRECTORY_SEPARATOR . 'var');
define('VENDOR_PATH', ROOT_PATH . DIRECTORY_SEPARATOR . 'vendor');

require_once VENDOR_PATH . '/autoload.php';

(new \zap\App())
    ->environment('development')    // 开发/生产环境
    ->withRoutes()
    ->withView()
    ->withMiddlewares()
    ->run();
```

---

## 路由

### 基本路由

```php
use zap\http\Route;

// GET 请求
Route::get('/user/{id}', function($id) {
    return "User ID: $id";
});

// 多方法
Route::any('/contact', 'ContactController@index');

// RESTful 路由
Route::resource('/posts', 'PostController');
Route::resources([
    '/posts' => 'PostController',
    '/tags'  => 'TagController',
]);
```

### 路由组

```php
Route::group('/admin', function() {
    Route::get('/dashboard', 'Admin\DashboardController@index');
    Route::get('/users', 'Admin\UserController@index');
})->middleware('auth');
```

### 命名路由与 URL 生成

```php
Route::get('/user/profile', 'UserController@profile')->name('profile');
// 使用: route('profile')

Route::addController('/user', 'UserController');  // 自动映射方法
```

### 路由缓存（生产环境推荐）

```php
// 生成缓存
\zap\http\Router::cacheRoutes();

// 清除缓存
\zap\http\Router::clearRouteCache();
```

> 缓存文件将生成到 `var/cache/routes.php`，框架会自动检测路由文件更新时间并在过期时重新生成。

---

## 控制器

```php
namespace App\Controllers;

use zap\http\Controller;

class UserController extends Controller
{
    public function index()
    {
        // 渲染视图
        return view('user.index', ['users' => $users]);
    }

    public function show($id)
    {
        $user = UserModel::findOrFail($id);
        return json($user);
    }
}
```

### RESTful 控制器

```php
use zap\http\RestController;

class PostController extends RestController
{
    public function index()   { /* GET    /posts */ }
    public function create()  { /* GET    /posts/create */ }
    public function save()    { /* POST   /posts */ }
    public function show($id) { /* GET    /posts/{id} */ }
    public function update($id) { /* PUT/PATCH /posts/{id} */ }
    public function destroy($id) { /* DELETE /posts/{id} */ }
}
```

---

## 请求 & 响应

### 获取请求数据

```php
use zap\http\Request;

// 获取输入
$name = Request::get('name');
$all  = Request::all();

// 请求信息
$method  = Request::method();    // GET/POST/PUT...
$ip      = Request::ip();        // 客户端 IP（防伪造）
$isAjax  = Request::isAjax();
$isJson  = Request::isJson();
$isPost  = Request::isPost();
$isMobile = Request::isMobile();

// 文件上传
$file = Request::file('avatar');
```

### IP 获取安全性

IP 地址默认从 `REMOTE_ADDR` 获取，不信任代理头。如果需要从代理头获取，需在配置中显式启用：

```php
// config/config.php
return [
    'trusted_proxies' => true,  // 仅在可信代理环境下开启
];
```

### 响应

```php
use zap\http\Response;

// JSON 响应
Response::json(['code' => 0, 'data' => $result]);
Response::jsonError('操作失败', 500);

// 视图响应
return view('welcome', ['name' => 'Zap']);

// 重定向
Response::redirect('/login');
Response::back();    // 返回上一页
```

### Flash 消息（一次性会话消息）

```php
Response::flash('操作成功');    // success
Response::flash('保存失败', new RedirectResponse('/form'), 'error');

// 视图中获取
session()->getFlash('message');
session()->getFlash('type');
```

### 可测试性

测试环境中可禁用 `send()` 的 `exit` 调用：

```php
\zap\http\Response::$shouldExit = false;
```

---

## 数据库

### 配置

```php
// config/database.php
return [
    'default' => 'mysql',
    'connections' => [
        'mysql' => [
            'driver'    => 'mysql',
            'host'      => '127.0.0.1',
            'port'      => '3306',
            'database'  => 'test',
            'username'  => 'root',
            'password'  => '',
            'charset'   => 'utf8mb4',
            'prefix'    => 'zap_',
        ],
        // PostgreSQL, SQLite 同样支持
    ],
];
```

### Query Builder（参数化查询，防 SQL 注入）

```php
use zap\DB;

// 查询
$users = DB::table('users')->where('status', 1)->get();

// 条件查询
$posts = DB::table('posts')
    ->where('status', 'published')
    ->whereIn('category_id', [1, 2, 3])
    ->whereLike('title', '%框架%')
    ->whereBetween('created_at', '2024-01-01', '2024-12-31')
    ->orderBy('id', 'desc')
    ->limit(10)
    ->get();

// 聚合
$count = DB::table('users')->count();
$max   = DB::table('orders')->max('amount');
$min   = DB::table('orders')->min('amount');
$sum   = DB::table('orders')->sum('amount');
$avg   = DB::table('orders')->avg('amount');

// 增删改
DB::table('users')->insert(['name' => '张三', 'email' => 'zs@example.com']);
DB::table('users')->where('id', 1)->update(['status' => 1]);
DB::table('users')->where('id', 1)->delete();

// 分页
$paginator = DB::table('posts')->paginate(15);
```

### Model（ActiveRecord 风格）

```php
namespace App\Models;

use zap\db\Model;

class UserModel extends Model
{
    protected string $table = 'users';
    protected string $primaryKey = 'id';
}

// 基本操作
$user = UserModel::find(1);
$user = UserModel::findOrFail(1);        // 未找到抛异常
$user = UserModel::findBy('email', 'test@example.com');

// 查询
$users = UserModel::all();
$users = UserModel::where('status', 1)->get();
$users = UserModel::limit(10)->offset(0)->orderBy('id', 'desc')->get();

// 增删改
$user = UserModel::create(['name' => '李四', 'email' => 'ls@example.com']);
$user->save(['name' => '王五']);
$user->delete();
$user->forceDelete();    // 如果有软删除

// 批量操作
UserModel::updateAll(['status' => 0], ['role' => 'guest']);
UserModel::deleteAll(['status' => -1]);
```

### 事务

```php
\zap\DB::transaction(function() {
    UserModel::create(['name' => '用户A']);
    LogModel::create(['action' => 'create_user']);
});

// 指定连接
\zap\DB::transaction(function() {
    // ...
}, 'mysql');
```

> 事务异常会记录日志并重新抛出，调用方可捕获处理。

### 多数据库连接

```php
DB::connection('mysql')->table('users')->get();
DB::connection('pgsql')->table('logs')->get();
```

---

## 视图

### 基本使用

```php
// 渲染视图
return view('users.profile', ['user' => $user]);
return view('emails.welcome', $data);

// 直接渲染
$html = view('widgets.sidebar')->render();
```

### 模板引擎

使用原生 PHP 模板，支持布局和包含：

```php
// views/layouts/main.php
<!DOCTYPE html>
<html>
<head><title><?= $title ?></title></head>
<body>
    <?= $this->section('content') ?>
    <?= $this->include('partials.footer') ?>
</body>
</html>

// views/home.php
<?php $this->layout('layouts.main') ?>
<h1><?= $title ?></h1>
<p><?= $content ?></p>
```

### 模板路径

默认路径为 `app/views/`。支持主题切换：

```php
// config/config.php
return [
    'theme' => 'default',   // 将自动追加 themes/default/ 到模板路径
];
```

---

## 配置

```php
// 读取配置（支持点号分隔）
$debug = config('config.debug', false);
$host  = config('database.mysql.host', '127.0.0.1');

// 动态设置
config(['app.name' => 'Zap App']);

// 清除配置缓存
\zap\Config::clearCache();
```

> 配置按需懒加载，同一文件只会被加载一次。

---

## 日志

```php
use zap\Log;

// 级别：DEBUG / INFO / NOTICE / WARNING / ERROR / CRITICAL / ALERT / EMERGENCY
Log::info('用户登录', ['user_id' => 123]);
Log::warning('磁盘空间不足');
Log::error('支付失败', ['order_id' => 456]);
Log::debug('SQL执行时间', ['sql' => $sql, 'time' => 0.05]);

// 配置（config/log.php）
return [
    'default' => 'app',
    'level'   => 200,   // INFO 级别
    'path'    => VAR_PATH . '/logs/app.log',
    'app' => [
        'handler' => \Monolog\Handler\StreamHandler::class,
        'params'  => [VAR_PATH . '/logs/app.log', 200],
    ],
];
```

> Monolog 为可选依赖。未安装时，框架使用内置 `SimpleLogger` 将日志写入文件或 `error_log`。

---

## 缓存

```php
use zap\facades\Cache;

// PSR-16 风格接口
Cache::set('key', 'value', 3600);     // 设置，TTL 3600 秒
$value = Cache::get('key', '默认值');  // 获取
Cache::has('key');                     // 是否存在
Cache::delete('key');                  // 删除
Cache::clear();                        // 清空

// 批量操作
Cache::setMultiple(['a' => 1, 'b' => 2], 3600);
$items = Cache::getMultiple(['a', 'b']);

// 配置（config/cache.php）
return [
    'default' => 'file',          // file | redis
    'stores' => [
        'file' => [
            'path' => VAR_PATH . '/cache',
        ],
        'redis' => [
            'host'     => '127.0.0.1',
            'port'     => 6379,
            'password' => null,
            'database' => 0,
            'prefix'   => 'zap:',
        ],
    ],
];
```

---

## Session & Flash 消息

```php
use zap\http\Session;

// 读取
Session::get('user_id');
Session::all();

// 写入
Session::set('user_id', 123);

// 删除
Session::delete('user_id');
Session::destroy();

// Flash 消息（一次性）
session()->setFlash('操作成功', 'success');    // 设置
session()->getFlash('message');                // 读取
```

---

## Hooks 系统

WordPress 风格的过滤器和动作系统：

```php
use zap\component\Hooks;

// 添加过滤器
Hooks::add_filter('content_format', function($content) {
    return nl2br($content);
});

// 添加动作
Hooks::add_action('user_registered', function($user) {
    Log::info('新用户注册', ['user' => $user]);
});

// 应用过滤器
$content = Hooks::apply_filters('content_format', $rawContent);

// 触发动作
Hooks::do_action('user_registered', $user);
```

---

## 中间件

```php
namespace App\Middlewares;

use zap\http\Middleware;

class AuthMiddleware implements Middleware
{
    public function handle($request, \Closure $next)
    {
        if (!isset($_SESSION['user'])) {
            return redirect('/login');
        }
        return $next($request);
    }
}

// 注册（config/route.php 或启动时）
Route::middleware('auth', AuthMiddleware::class);

// 使用
Route::group('/admin', function() {
    // ...
})->middleware('auth');

// 或给单条路由
Route::get('/dashboard', 'DashboardController@index')->middleware('auth');
```

---

## 控制台命令

### 入口文件 `console`

```php
#!/usr/bin/env php
<?php

define('ROOT_PATH', dirname(__DIR__));
define('APP_PATH', ROOT_PATH . '/app');
define('CONFIG_PATH', ROOT_PATH . '/config');
define('VAR_PATH', ROOT_PATH . '/var');
define('VENDOR_PATH', ROOT_PATH . '/vendor');

require_once VENDOR_PATH . '/autoload.php';

$console = new \zap\console\Console(ROOT_PATH);
$console->addCommand('app/commands', 'app');
exit($console->execute());
```

### 创建命令

```php
namespace App\Commands;

use zap\console\Command;
use zap\console\Input;
use zap\console\Output;

class HelloCommand extends Command
{
    public function configure(): void
    {
        $this->setName('hello')
             ->setDescription('输出欢迎信息')
             ->addArgument('name', '名称', true)
             ->addArgument('greeting', '问候语', false, 'Hello')
             ->addOption('uppercase', 'u', '转为大写输出');
    }

    public function execute(Input $input, Output $output): int
    {
        $name = $input->getArgument(0);
        $greeting = $input->getArgument(1) ?? 'Hello';

        $message = "{$greeting}, {$name}!";

        if ($input->getOption('uppercase')) {
            $message = strtoupper($message);
        }

        $output->success($message);
        return self::SUCCESS;
    }
}
```

### 运行命令

```bash
# 基本执行
php console hello zap

# 传入多个参数
php console hello zap "Good morning"

# 使用选项
php console hello zap -u
php console hello zap --uppercase

# 查看帮助
php console hello -h
php console hello --help

# 调试模式（显示详细输出）
php console hello zap -v     # 一般详情
php console hello zap -vv    # 更详细
php console hello zap -vvv   # 调试级别

# 列表所有可用命令
php console
php console list
```

### 命令配置 API

```php
public function configure(): void
{
    $this
        // 命令名
        ->setName('hello')
        // 描述（在列表命令中显示）
        ->setDescription('输出欢迎信息')
        // 位置参数：名称、描述、是否必填、默认值
        ->addArgument('name', '名称', true)
        ->addArgument('title', '标题', false, 'Mr.')
        // 命名选项：名称、短选项、描述、默认值
        ->addOption('uppercase', 'u', '转为大写')
        ->addOption('times', 't', '重复次数', 1);
}
```

### Input API

```php
class MyCommand extends Command
{
    public function execute(Input $input, Output $output): int
    {
        // 位置参数（0-based 索引）
        $firstName = $input->getArgument(0);
        $secondArg = $input->getArgument(1, '默认值');
        $allArgs   = $input->getArguments();  // 所有位置参数数组

        // 命名选项
        $uppercase = $input->getOption('uppercase');
        $times     = $input->getOption('times', 1);
        $allOpts   = $input->getOptions();

        // 检查是否存在
        $input->hasOption('verbose');
        $input->hasParam('v');

        // 兼容旧接口（1-based）
        $first = $input->getParam(1);

        return self::SUCCESS;
    }
}
```

### Output API

```php
public function execute(Input $input, Output $output): int
{
    // 基本输出
    $output->writeln('普通文本');
    $output->write('不换行...');

    // 带颜色标签的输出
    $output->writeln('<info>信息</info>');
    $output->writeln('<error>错误</error>');
    $output->writeln('<warning>警告</warning>');
    $output->writeln('<success>成功</success>');
    $output->writeln('<debug>调试信息</debug>');

    // 快捷彩色方法
    $output->info('信息消息');
    $output->error('错误消息');     // 输出到 stderr
    $output->warning('警告消息');
    $output->success('成功消息');
    $output->debug('调试消息');

    // 格式化输出
    $output->printf('共处理 %d 条记录', $count);

    // 按详细级别输出
    $output->writelnV('详细信息（-v）');    // 需 -v
    $output->writelnVV('更详细（-vv）');      // 需 -vv
    $output->writelnVVV('调试详情（-vvv）');  // 需 -vvv

    // 检测颜色支持
    if ($output->hasColorSupport()) {
        $output->writeln('<red>红色文字</red>');
    }

    return self::SUCCESS;
}
```

### 颜色样式

当终端支持颜色时，以下标签自动转为 ANSI 彩色输出（不支持时自动去除标签）：

| 标签 | 效果 | 颜色 |
|---|---|---|
| `<info>...</info>` | 信息 | 绿色 |
| `<success>...</success>` | 成功 | 绿色 |
| `<error>...</error>` | 错误 | 红色 |
| `<warning>...</warning>` | 警告 | 黄色 |
| `<comment>...</comment>` | 注释 | 黄色 |
| `<debug>...</debug>` | 调试 | 灰色 |
| `<red>...</red>` | 红色 | 红色 |
| `<green>...</green>` | 绿色 | 绿色 |
| `<yellow>...</yellow>` | 黄色 | 黄色 |
| `<blue>...</blue>` | 蓝色 | 蓝色 |
| `<magenta>...</magenta>` | 品红 | 品红 |
| `<cyan>...</cyan>` | 青色 | 青色 |
| `<white>...</white>` | 白色 | 白色 |
| `<gray>...</gray>` | 灰色 | 灰色 |

> 设置环境变量 `NO_COLOR=1` 可禁用彩色输出。

### 注册命令目录

```php
$console = new \zap\console\Console(ROOT_PATH);

// 注册命令目录（路径 => 命名空间）
$console->addCommand('app/commands', 'app');

// 多个命令目录
$console->addCommand('vendor/my-package/src/commands', 'my-package');

$console->execute();
```

### 命令命名规则

- 命令类放在注册的目录下，文件名即命令名
- `app/commands/HelloCommand.php` → 命令名 `HelloCommand`
- 运行时：`php console HelloCommand arg1`
- 支持 `vendor:CommandName` 格式（适合第三方包）

### 自定义默认命令

```php
$console->setDefaultCommand(MyDefaultCommand::class);
```

> `--version` / `-V` 为内置命令，直接输出版本号，无需注册。

---

## Facades

```php
use zap\facades\Cache;   // 缓存
use zap\facades\Date;    // 日期时间
use zap\facades\Url;     // URL 生成
```

---

## 辅助函数

```php
// 路径
base_path('app/views');        // 项目根路径
config_path('database.php');   // 配置路径
app_path('models/User.php');   // 应用路径
storage_path('logs/app.log');  // 存储路径

// 应用 & 依赖
app();                          // 获取 App 实例
app('router');                  // 获取容器中的服务

// 配置
config('database.default');
config(['app.debug' => true]);

// 路由
route('profile');               // 命名路由 URL
url('/users');                  // 完整 URL

// 响应
redirect('/login');             // 重定向
view('home', ['name' => 'Zap']);// 视图
json(['code' => 0]);            // JSON 响应

// 其他
dd($data);                      // dump & die
env('APP_ENV', 'production');   // 环境变量
value(function() { ... });      // 执行回调

// 字符串
snake_case('HelloWorld');       // hello_world
camel_case('hello_world');      // helloWorld
studly_case('hello_world');     // HelloWorld
str_contains('hello world', 'world');  // true
str_starts_with('hello', 'he');        // true
str_ends_with('hello', 'lo');          // true
str_limit('very long text', 10);       // very lon...
```

---

## HTML 构建器

```php
use zap\html\Html;

echo Html::a('/users', '用户列表');          // <a href="/users">用户列表</a>
echo Html::ul(['首页', '关于', '联系']);     // <ul><li>...</li></ul>
echo Html::form('/login', 'POST')
    ->text('username')
    ->password('password')
    ->submit('登录');
```

---

## 国际化 i18n

```php
// 加载语言文件
\zap\i18n\Language::loadApp('zh-cn');

// 翻译
echo __('welcome');                          // 欢迎
echo __('user.greeting', ['name' => 'Zap']); // 你好 Zap
```

语言文件示例 (`app/lang/zh-cn.php`)：
```php
return [
    'welcome' => '欢迎',
    'user' => [
        'greeting' => '你好 :name',
    ],
];
```

---

## 加密

```php
use zap\crypto\OpenSSL;

$encrypted = OpenSSL::encrypt('敏感数据', 'your-secret-key');
$decrypted = OpenSSL::decrypt($encrypted, 'your-secret-key');
```

默认使用 AES-256-CBC 算法。

---

## 错误处理

框架内置错误处理器，根据环境模式切换行为：

```php
(new \zap\App())->environment('development');  // 显示详细错误
(new \zap\App())->environment('production');   // 不显示敏感信息
```

---

## URL Rewrite

### Apache

```apacheconf
Options +FollowSymLinks -Indexes
RewriteEngine On

RewriteCond %{HTTP:Authorization} .
RewriteRule .* - [E=HTTP_AUTHORIZATION:%{HTTP:Authorization}]

RewriteCond %{REQUEST_FILENAME} !-d
RewriteCond %{REQUEST_FILENAME} !-f
RewriteRule ^ index.php [QSA,PT,L]
```

### Nginx

```nginx
location / {
    try_files $uri $uri/ /index.php?$query_string;
}
```

---

## License

MIT
