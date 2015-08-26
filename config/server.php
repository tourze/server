<?php

return [

    // 默认的web配置
    'web' => [
        'count'          => 4, // 打开进程数
        'user'           => '', // 使用什么用户打开
        'reloadable'     => true, // 是否支持平滑重启
        'socketName'     => 'http://0.0.0.0:8080', // 默认监听8080端口
        'contextOptions' => [], // 上下文选项
        'siteList'       => [
            'www.example.com' => __DIR__ . '/../example/web/',
        ],
    ],

];
