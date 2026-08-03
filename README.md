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

## 验证器

内置灵活的数据验证组件，支持链式调用、嵌套字段、自定义规则和自定义错误消息。

### 基本使用

```php
use zap\validator\Validator;

// 方式一：静态工厂（默认从 $_GET/$_POST 读取数据）
$v = Validator::make()
    ->rule('required', ['name', 'email'])
    ->rule('email', 'email')
    ->rule('integer', 'age')
    ->rule('between', 'age', [1, 120])
    ->setLabels(['name' => '姓名', 'email' => '邮箱', 'age' => '年龄']);

if ($v->validate()) {
    $data = $v->getValidData();  // 验证通过的数据
} else {
    $errors = $v->firstOfAll();  // 每个字段的第一个错误
}

// 方式二：手动传入数据
$v = Validator::make($_POST)->rule('required', 'username')->validate();
```

### 链式配置 API

```php
$v = Validator::make($data)
    // 添加规则：rule(规则名, 字段, [参数])
    ->rule('required', ['title', 'content'])
    ->rule('max', 'title', 100)
    ->rule('in', 'status', ['draft', 'published', 'archived'])

    // 自定义错误消息
    ->messages([
        'title.required' => '请输入文章标题',
        'title.max'      => '标题不能超过 :param 个字符',
    ])

    // 字段标签（用于错误消息中的 {field} 占位）
    ->setLabels([
        'title'   => '标题',
        'content' => '内容',
        'status'  => '状态',
    ])

    // 字段级遇错停检（title 的第一个规则失败后跳过其余 title 规则）
    ->bail('title')

    // 可空字段（值为空时跳过除 required 以外的规则）
    ->nullable('avatar')

    // 全局遇第一个错误立即停止
    ->stopOnFirstFailure()

    // 验证后回调
    ->after(function($v) {
        if ($v->passes()) {
            // 后置处理，如数据清洗
        }
    })

    // 注册自定义规则命名空间
    ->addNamespace('App\\Validators');
```

### 获取结果

```php
$v = Validator::make($data)->rule('required', 'name');

$v->validate();           // 执行验证，返回 bool
$v->fails();              // 是否失败
$v->passes();             // 是否通过
$v->getValidData();       // 通过验证的数据数组
$v->get('name');          // 获取单个通过验证的值
$v->errors();             // 所有错误 ['field' => ['rule' => 'message']]
$v->firstOfAll();         // 每字段第一个错误 ['field' => 'message']
$v->error('name');        // 指定字段的第一个错误（字符串）
$v->error('name', true);  // 指定字段的所有错误（数组）
$v->validated();          // 通过返回数据，失败抛出 RuntimeException
$v->reset();              // 重置验证器状态
```

### 嵌套字段 & 通配符

```php
// 点号分隔嵌套字段
$data = ['user' => ['name' => 'Zap', 'email' => 'a@b.com']];
$v = Validator::make($data)
    ->rule('required', 'user.name')
    ->rule('email', 'user.email');

// 通配符匹配数组子项
$data = ['items' => [
    ['name' => '商品A', 'price' => 100],
    ['name' => '商品B', 'price' => 200],
]];
$v = Validator::make($data)
    ->rule('required', 'items.*.name')
    ->rule('integer', 'items.*.price');
```

### 可用规则一览

| 规则 | 说明 | 参数示例 |
|------|------|----------|
| `required` | 字段必填 | 无 |
| `required_with` | 指定的其他字段有值时必填 | `['other_field']` |
| `email` | 有效邮箱地址 | 无 |
| `url` | 有效 URL | 无 |
| `domain` | 有效域名 | 无 |
| `ip` | 有效 IP 地址（v4/v6） | 无 |
| `ipv4` | 有效 IPv4 地址 | 无 |
| `ipv6` | 有效 IPv6 地址 | 无 |
| `integer` | 整数 | 无 |
| `double` | 浮点数 | 无 |
| `numeric` | 数字（整数或浮点） | 无 |
| `boolean` | 布尔值（支持 0/1/true/false/yes/no/on/off） | 无 |
| `alpha` | 纯字母 (a-z) | 无 |
| `alpha_num` | 字母 + 数字 | 无 |
| `ascii` | 纯 ASCII 字符 | 无 |
| `min` | 数值最小值 | `10` |
| `max` | 数值最大值 | `100` |
| `between` | 数值范围 | `[1, 120]` |
| `length` | 字符串长度范围 | `[6, 20]` |
| `length_min` | 字符串最小长度 | `6` |
| `length_max` | 字符串最大长度 | `20` |
| `range_length` | 字符串长度范围（Length 别名） | `[6, 20]` |
| `in` | 值必须在列表中 | `['admin', 'editor']` |
| `not_in` | 值不能在列表中 | `['root', 'super']` |
| `regex` | 正则匹配 | `'/^\d{6}$/'` |
| `date` | 有效日期（默认 Y-m-d） | `'Y-m-d H:i:s'` |
| `date_format` | 日期格式（Date 别名） | `'d/m/Y'` |
| `json` | 合法 JSON 字符串 | `'array'` / `'object'` |
| `confirmed` | 字段与 `{field}_confirmation` 一致 | 无 |
| `same` | 与指定字段值相同 | `'email'` |
| `different` | 与指定字段值不同 | `'old_password'` |
| `distinct` | 数组值唯一无重复 | 无 |
| `is_array` | 必须为数组 | 无 |
| `callback` | 自定义验证函数或类 | `function($name, $value) { ... }` |

### Callback 规则

```php
// 闭包方式
$v = Validator::make($data)
    ->rule('callback', 'custom_field', function($name, $value) {
        return $value === 'expected_value';
    });

// 类方式（需实现 check($name, $value) 方法）
$v = Validator::make($data)
    ->rule('callback', 'custom_field', MyValidationRule::class);
```

### 扩展自定义规则

```php
// 1. 创建规则类
namespace App\Validators;

use zap\validator\AbstractRule;

class Mobile extends AbstractRule
{
    public function validate($name, $value)
    {
        return preg_match('/^1[3-9]\d{9}$/', $value) === 1;
    }

    public function translateMsgKey()
    {
        return 'rule_mobile';  // 语言文件中的 key
    }
}

// 2. 运行时注册命名空间
$v = Validator::make($data)
    ->addNamespace('App\\Validators')
    ->rule('mobile', 'phone');
```

> 框架查找规则时，先搜索内置的 `zap\validator\rules`，再搜索通过 `addNamespace()` 注册的自定义命名空间。

---

## 国际化 (i18n)

框架内置灵活的国际化组件，支持多语言文件加载、参数替换、回退语言和复数翻译。

### 基本使用

```php
use zap\i18n\Language;

// 设置当前语言
Language::locale('zh_CN');

// 获取翻译
echo __('messages.welcome', ['name' => 'Zap']);    // "欢迎 Zap"
echo trans('messages.welcome', ['name' => 'Zap']);  // 同上
echo __('messages.title');                            // 无参数
echo __('messages.greeting', 'Zap');                  // 旧式 {value} 替换

// 复数翻译
echo trans_choice('messages.apples', 1);    // "1 个苹果"
echo trans_choice('messages.apples', 5);    // "5 个苹果"
```

### 语言文件

框架支持 PHP 和 JSON 两种格式，存放在 `resources/languages/{locale}/` 目录下：

**PHP 格式** — `resources/languages/zh_CN/messages.php`：
```php
<?php
return [
    'welcome' => '欢迎 {name}',
    'title'   => '我的网站',
    'apples'  => [
        'one'   => '{count} 个苹果',
        'other' => '{count} 个苹果',
    ],
];
```

**JSON 格式** — `resources/languages/en/messages.json`：
```json
{
    "welcome": "Welcome {name}",
    "title": "My Website",
    "apples": {
        "one": "{count} apple",
        "other": "{count} apples"
    }
}
```

### 配置方法

```php
use zap\i18n\Language;

// 获取 / 设置当前语言
$locale = Language::locale();           // 'zh_CN'
Language::locale('en');                 // 切换到英文

// 设置回退语言（当前语言找不到翻译时自动尝试）
Language::fallback('en');

// 添加自定义语言文件路径
Language::addPath('/my-app/resources/languages');

// 获取所有可用语言
$locales = Language::availableLocales();  // ['zh_CN', 'en']

// 获取所有搜索路径
$paths = Language::getPaths();
```

### 参数替换

支持多种参数格式，通过 `{key}` 占位符替换：

```php
// 数组参数：{key} → value
__('messages.greeting', ['name' => 'Zap', 'role' => '管理员']);
// "你好 Zap，你的角色是 管理员"

// 字符串参数（旧式，仅替换 {value}）
__('messages.input', 'hello');
// 消息中 {value} 被替换为 hello
```

### 复数翻译

语言文件中定义 `.one` / `.other` 后缀区分单复数：

```php
// messages.php
return [
    'comment' => [
        'one'   => '{count} 条评论',
        'other' => '{count} 条评论',
    ],
];

// 使用
echo trans_choice('messages.comment', 1);  // "1 条评论"
echo trans_choice('messages.comment', 8);  // "8 条评论"
```

> 中文通常不需要区分单复数，但框架保留此能力以支持英文等多语言场景。

### 动态消息注册

```php
// 运行时添加翻译（不依赖文件）
Language::with(['app.name' => '我的应用', 'app.version' => '1.0']);
// 或
Language::set('app.author', 'Zap Team');

// 获取
__('app.name');  // "我的应用"

// 检查是否存在
Language::has('app.name');  // true
```

### Helper 函数速查

| 函数 | 说明 |
|------|------|
| `__($key, $params)` | 推荐用法：获取翻译（参数数组） |
| `trans($key, $params, $value)` | 翻译（支持旧式 {value} 替换） |
| `trans_choice($key, $count, $params)` | 复数翻译 |

---

## HTTP 网络请求

框架提供 `Requests`（全功能）和 `Curl`（精简兼容）两种 HTTP 客户端，后者内部复用前者。

### 基本请求

```php
use zap\net\Requests;

// GET 请求
$response = Requests::get('https://api.example.com/users', ['page' => 1]);
echo $response->body();       // 响应体
echo $response->status();     // 200

// POST 表单
$response = Requests::post('https://api.example.com/login', [
    'username' => 'admin',
    'password' => 'secret',
]);

// PUT / PATCH / DELETE
$response = Requests::put('https://api.example.com/users/1', ['name' => '新名称']);
$response = Requests::delete('https://api.example.com/users/1');
```

### Response 对象

所有请求方法返回 `zap\net\Response` 对象：

```php
$r = Requests::get('https://api.example.com/data');

$r->body();              // string — 响应体
(string) $r;             // 同 body()，可当字符串用
$r->json();              // array — JSON 解析
$r->json(false);         // object — JSON 解析为对象
$r->status();            // int — HTTP 状态码 (200, 404, …)
$r->ok();                // bool — 2xx 检查
$r->clientError();       // bool — 4xx 检查
$r->serverError();       // bool — 5xx 检查
$r->contentType();       // string — Content-Type
$r->totalTime();         // float — 请求耗时（秒）
$r->effectiveUrl();      // string — 最终 URL（跟随重定向后）
$r->info();              // array — curl_getinfo 完整信息
```

### JSON 请求

```php
// 发送 JSON
$r = Requests::json('POST', 'https://api.example.com/data', [
    'title' => 'Hello',
    'body'  => 'Content',
]);

// 快捷方法
$r = Requests::postJson('https://api.example.com/data', ['key' => 'value']);
$data = Requests::getJson('https://api.example.com/data', ['page' => 1]);
```

### 自定义请求头

```php
$r = Requests::post('https://api.example.com/data', $data, [
    'Authorization: Bearer token123',
    'X-Custom-Header: value',
    'Accept: application/json',
]);
```

### 请求选项

```php
$r = Requests::post('https://api.example.com/data', $data, $headers, [
    'timeout'          => 30,    // 超时（秒）
    'connect_timeout'  => 5,     // 连接超时（秒）
    'ssl_verify'       => false, // 跳过 SSL 验证（不推荐）
    'follow_redirects' => true,  // 跟随重定向
    'max_redirects'    => 5,     // 最大重定向次数
    'cookie'           => 'key=value',           // Cookie 字符串
    'cookie_file'      => '/tmp/cookies.txt',    // Cookie 文件路径
    'auth'             => ['username', 'pass'],  // HTTP Basic Auth
    'referer'          => 'https://example.com', // Referer
]);
```

### 文件上传

```php
$r = Requests::multipart('https://api.example.com/upload', [
    'title' => '我的图片',         // 普通字段
], [
    'image' => '/path/to/photo.jpg',  // 文件字段
]);
```

### 并发请求

```php
$responses = Requests::multi([
    ['method' => 'GET',  'url' => 'https://api.example.com/users'],
    ['method' => 'GET',  'url' => 'https://api.example.com/posts'],
    ['method' => 'POST', 'url' => 'https://api.example.com/log', 'params' => ['event' => 'visit']],
]);

foreach ($responses as $r) {
    echo $r->status() . ': ' . $r->body();
}
```

### 全局配置

```php
Requests::setUserAgent('MyApp/1.0');
Requests::setDefaultTimeout(60);
Requests::setDefaultConnectTimeout(15);
Requests::setCaCert('/custom/cacert.pem');
```

### Curl 精简版（向后兼容，返回字符串）

```php
use zap\net\Curl;

$html = Curl::get('https://example.com/page', ['id' => 1]);
$result = Curl::post('https://api.example.com/login', ['user' => 'admin', 'pass' => '123']);
```

### 错误处理

```php
use zap\exception\CurlException;

try {
    $r = Requests::get('https://down.example.com/api');
} catch (CurlException $e) {
    echo '错误码: ' . $e->getCode();
    echo '错误信息: ' . $e->getMessage();
}
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
