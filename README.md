# Laravel 中间件-Response 缓存

## 功能

-   支持缓存渲染后数据
-   支持指定缓存过期时间（最低 10 分钟）
-   header 头输出缓存命中状态、缓存 Key 及过期时间
-   支持分组缓存（如果缓存支持）
-   支持清空缓存（必须支持分组缓存）
-   支持跳过缓存
-   支持清理当前缓存

## 安装

```sh
composer require anerg2046/laravel-cache-page
```

## 配置

> `\app\Http\Kernel.php`文件中`$routeMiddleware`增加：

```php
'cache.response' => \anerg\Laravel\Http\Middleware\CacheResponse::class,
// cache.response 命名随意，你开心就好
```

> 增加配置文件`config\pagecache.php`

```php
return [
    //是否不进行缓存 - 开发模式下，应该为true
    'skip'       => false,
    //是否允许url参数 跳过缓存
    'allowSkip'  => true,
    //是否允许url参数 清空缓存
    'allowFlush' => true,
    //是否允许url参数 清除当前地址缓存
    'allowClear' => true
];
```

## 使用

```php
<?php
Route::get('/', function () {
    return view('welcome');
})->middleware('cache.response');

Route::get('/', function () {
    return view('welcome');
})->middleware('cache.response:20');  // 指定缓存时间20分钟
```

> 一般来说只应该缓存 get 请求的页面

## 附录

**缓存规则**

-   当前 URL 路径+json_encode 查询键值数组 md5

**Headers**

```
X-Cache:Missed
X-Cache-Expires:2018-03-29 15:08:29 CST
X-Cache-Key:6c9b19774e2c304a42d200f314d8c80b
```

## 修改自

https://github.com/flc1125/laravel-middleware-cache-response

## License

MIT
