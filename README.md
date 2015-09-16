# 服务器组件

在很多时候，php在项目中都不能单独存在。对于web项目，大部分情况需要搭配nginx/apache等web服务器。

而且很多情况下，用php来做长链接的服务时，会显得很力不从心。

幸好现在有swoole和workerman这类服务端框架存在。

本人相对来说比较熟悉workerman，所以在这个组件中最终却是选择使用workerman。

本组件的使用方法很简单，只需要`composer require tourze/server`

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

## 安装

首先需要下载和安装[composer](https://getcomposer.org/)，具体请查看官网的[Download页面](https://getcomposer.org/download/)

在你的`composer.json`中增加：

    "require": {
        "tourze/server": "^1.0"
    },

或直接执行

    composer require tourze/server:"^1.0"
