# 服务器模块

在很多时候，php在项目中都不能单独存在。对于web项目，大部分情况需要搭配nginx/apache等web服务器。

传统的PHP开发方式，在一些特定场景下，会显得很力不从心，如：

1. 长链接服务
2. 需要持久化对象或请求的服务

幸好现在有[swoole](http://www.swoole.com)和[workerman](www.workerman.net)这类服务端框架存在。

本人相对来说比较熟悉workerman，所以在平时使用时，会偏向选择workerman。

那既然有了workerman，为什么还要写这个模块呢？在我看来，workerman虽然很好，但是还不能做到开箱即用，而且一些使用方法上，个人也很不习惯。

所以我希望在workerman之上再封装一层，在整合了workerman强大功能的同时，为用户提供更高的便捷性。

## 安装

首先需要下载和安装[composer](https://getcomposer.org/)，具体请查看官网的[Download页面](https://getcomposer.org/download/)

在你的`composer.json`中增加：

    "require": {
        "tourze/server": "^1.0"
    },

或直接执行

    composer require tourze/server:"^1.0"

建议在main.php配置文件中加入：

```
    'component' => [
        'http' => [
            'class' => 'tourze\Server\Component\Http',
            'params' => [
            ],
            'call' => [
            ],
        ],
        'session' => [
            'class' => 'tourze\Server\Component\Session',
            'params' => [
            ],
            'call' => [
            ],
        ],
        'log' => [
            'class' => 'tourze\Server\Component\Log',
            'params' => [
            ],
            'call' => [
            ],
        ],
    ],
```

以保证输出的header正确。
