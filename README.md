yii-framework-base (based on Yii2)
===========================

在去年的PHP工作中总结出来的一些比较有用的基础类库和业务封装。
代码风格是面向对象，充血模型，抽象类。
希望对各位有帮助。

由于对`yii`本身进行了扩展的增强，请注意`php`的版本和`yii`本身的版本，限制为`2.0.13.x`。

 ![Yii2](https://img.shields.io/badge/Powered_by-Yii_Framework-green.svg?style=flat)

Overview
-------------------

yii-framework-base web 服务端，使用环境 `PHP7.1.x` + `MySQL 5.6+` 亦或 `AliSQL 5.6`，推荐 `AliSQL 5.6`。

[代码风格 Yii 2 Core Framework Code Style](https://github.com/yiisoft/yii2/blob/master/docs/internals/core-code-style.md)

[注释风格 PHPDoc](https://www.phpdoc.org)

Overall we're using PSR-2 compatible style so everything that applies to PSR-2 is applied to our code style as well.

Author: William Chan

```
MySQL sql_mode

ONLY_FULL_GROUP_BY,
STRICT_TRANS_TABLES,
NO_ZERO_IN_DATE,
NO_ZERO_DATE,
ERROR_FOR_DIVISION_BY_ZERO,
NO_AUTO_CREATE_USER,
NO_ENGINE_SUBSTITUTION
```

DIRECTORY STRUCTURE
-------------------

    commands/           contains console commands (controllers)
    components/         contains misc component classes
    config/             contains application configurations
    controllers/        contains web controller classes
    migrations/         contains migrations of database
    models/             contains common model classes
    modules/            contains module directories, include its MVC structure
        api/
        admin/
    runtime/            contains files generated during runtime
    static/             contains static resource files
    vendor/             contains dependent 3rd-party packages
    views/              contains view files for the web application
    web/                contains the entry script and web resources

API DOCUMENT
------------

API文档使用`swagger`，具体请及时阅读 https://swagger.io/docs/specification/about/ 。

REQUIREMENTS
------------

The minimum requirement by this project template that your Web server supports PHP 7.0.0.


INSTALLATION
------------

请在工作环境安装好 composer 并克隆仓库至本地环境。

### 安装第三方依赖

~~~
composer global require "fxp/composer-asset-plugin:*"
composer install
~~~

### 修改配置

首次安装请复制 `config/custom-sample.php` 为 `config/custom.php`，并按需修改其中相应的条目（本地调试可不作修改）。
这里面的配置结构将直接覆盖应用于 `yii\base\Application`，请勿直接修改 `config/` 目录下的其它配置文件。


### 数据库

首先，创建一个专用库取名为 `yii-framework-base` 并将字符集设为 utf8。

~~~
CREATE DATABASE `yii-framework-base` DEFAULT CHARACTER SET utf8;
~~~

然后，依次导入初始的数据库表结构、数据，这里只包含必须的少量数据内容，其它数据内容请在使用过程中填充。

~~~
mysql -u root yii-framework-base < static/sql/data.sql
~~~

最后，运行 Yii2 的数据库升级工具更新库表结构。

~~~
./yii migrate
~~~


### 升级第三方依赖

~~~
composer update
~~~

### 配置本地环境

推荐本地使用`brew`解决一切操作，具体查看 https://brew.sh/ 。
nginx 的配置文件在 `static/conf/nginx`。
php 的配置文件在 `static/conf/php`。
You need to set `hosts`, If bind domain.

```shell
brew install php@7.1
brew install mysql@5.7
brew install redis
brew install nginx

brew services list
brew services start nginx
brew services start mysql@5.7
brew services start redis
brew services start php@7.1
```

### Final

You can then access the application through the following URL:

~~~shell
http://yii-framework-base.com:8001 # It Works
http://yii-framework-base.com:8001/debug # debug
http://yii-framework-base.com:8001/api/doc # Api Document
http://yii-framework-base.com:8001/api/doc/v2 # Api Document
~~~

导出本地数据库结构

```shell
mysqldump -uroot -d yii-framework-base | sed 's/ AUTO_INCREMENT=[0-9]*//g' > data.sql
```
