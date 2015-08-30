# 服务器组件

在很多时候，php在项目中都不能单独存在。对于web项目，大部分情况需要搭配nginx/apache等web服务器。

而且很多情况下，用php来做长链接的服务时，会显得很力不从心。

幸好现在有swoole和workerman这类服务端框架存在。

本人相对来说比较熟悉workerman，所以在这个组件中最终却是选择使用workerman。
