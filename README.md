# Synapse / 突触 （暂定名）
神经元与另一个神经元、肌肉细胞或腺细胞之间进行信号传递的连接部位，是神经系统中细胞彼此“交接信息”的关键位置。它是一个名词，复数形式是 synapses。



一个个人内容管理网站。内容包括  博客 图集 瞬间(微博) 短视频 资源(文件共享)（内容阅读有严格的权限）。  
站点和站点之间 可以聚合 , 关注(输入另一个站的网址后) 在自己网站可见 人家授权的信息 在自己的站点也可以查看 也可以跳转到原文。 自己的内容发在自己的站点上，别人看见后可以引用或二创后转发到自己的网站。

**自己为自己发布的内容负责，转发的人为自己转发的内容负责。**  每个人或站点就像一个网络节点，更像两个神经元的交汇点 **Synapse**



# 内容分享基础架构

## 内容表

- `post`：内容统一主表，保存作者、标题、类型、发布状态、可见级别和时间；类型字段为字符串，后续新增类型不需要修改数据库字段定义。当前类型值为 `blog`、`images`、`moment`、`short_video`、`shared_file`。
- `blog`：博客正文和摘要。
- `images`：独立图片集的图片明细，每张图片一行，保存所属 `post_id`、图片标题、图片地址、说明和排序；一个图片集主记录可以关联多张图片，与博客没有关联。
- `moment`：短动态；图片列表先以 JSON 保存。
- `short_video`：视频元数据、存储路径、封面和时长。
- `shared_file`：文件元数据、原文件名、MIME 类型、字节数、正常/回收站状态、删除时间及共享起止时间。内容作者与可见级别统一从 `post` 读取。
- `shared_file_recipient`：指定用户共享的授权名单；仅当对应 `post.visibility` 为指定用户时生效。
- `shared_file_folder`：用户网盘目录树，保存父目录、所有者、回收站状态和目录共享规则。
- `shared_file_folder_recipient`：文件夹共享给指定用户时的授权名单。

`post.visibility` 控制博客、图片集、瞬间、短视频，以及显式单文件共享：`0` 仅作者可见（默认）、`1` 已登录用户可见、`2` 公开访问、`3` 指定用户可见。指定用户通过 `shared_file_recipient` 授权。

文件夹 `share_mode` 为 `0` 时继承最近的父目录规则，根目录默认仅所有者可见；也可以设置为 `1` 私有、`2` 指定用户、`3` 注册用户或 `4` 公开。`shared_file.share_mode=0` 时继承所在文件夹规则；设为 `1` 时使用该文件对应 `post.visibility`。文件/目录的共享开始和结束时间为空时分别表示立即生效和不设到期时间。文件访问必须同时满足文件及其目录链都未进入回收站。放入或恢复目录时，应用层应在事务内处理整个子目录树；删除目录时优先使用回收站，物理清理需先处理子目录。

五类内容通过 `post` 主表统一记录，并由各自详情表保存专属字段；详情表通过 `post_id` 关联主表，主表关联现有 `user` 表。图片集与其图片明细是一对多关系。文件夹通过 `parent_id` 构成目录树，文件通过可空 `folder_id` 归档；不同用户之间的目录移动必须由应用层拒绝。文件路径字段保存相对存储键，不应保存用户提交的本机绝对路径。文件上传、鉴权下载、大小与 MIME 白名单、缩略图/转码和审核需要在上线前接入专门的存储服务。

## 应用

主站采用 Yii2 Advanced 模板的 `frontend` 应用。登录用户可以从首页进入 `/content/create?type=blog|images|moment|video|file` 创建记录；匿名访问创建入口会被重定向到登录。图片集数据库关系已就绪，批量图片上传/编辑流程后续接入。

## 初始化数据库

确认 `common/config/main-local.php` 的连接指向目标库后，在项目根目录执行：

```powershell
php yii migrate --migrationPath=@console/migrations
```

迁移采用 `safeUp`，只创建新表、索引和外键，不更改现有用户表。回滚这条迁移会删除新增内容表、共享授权表及其数据。



DIRECTORY STRUCTURE
-------------------

```
common
    config/              contains shared configurations
    mail/                contains view files for e-mails
    models/              contains model classes used in both backend and frontend
    tests/               contains tests for common classes
console
    config/              contains console configurations
    controllers/         contains console controllers (commands)
    migrations/          contains database migrations
    models/              contains console-specific model classes
    runtime/             contains files generated during runtime
backend
    assets/              contains application assets such as JavaScript and CSS
    config/              contains backend configurations
    controllers/         contains Web controller classes
    models/              contains backend-specific model classes
    runtime/             contains files generated during runtime
    tests/               contains tests for backend application
    views/               contains view files for the Web application
    web/                 contains the entry script and Web resources
frontend
    assets/              contains application assets such as JavaScript and CSS
    config/              contains frontend configurations
    controllers/         contains Web controller classes
    models/              contains frontend-specific model classes
    runtime/             contains files generated during runtime
    tests/               contains tests for frontend application
    views/               contains view files for the Web application
    web/                 contains the entry script and Web resources
    widgets/             contains frontend widgets
vendor/                  contains dependent 3rd-party packages
environments/            contains environment-based overrides
```

Initialize the application for the `Development` environment:

```bash
php init --env=Development --overwrite=All
```

Now you should be able to access the application through the following URLs, assuming `advanced` is the directory
directly under the Web root.

```
http://localhost/advanced/frontend/web/
http://localhost/advanced/backend/web/
```

## Install with Docker

Build and start the containers:

```bash
docker compose up -d --build
```

Install dependencies inside the container:

```bash
docker compose exec frontend composer update --prefer-dist --no-interaction
```

Initialize the application for the `Development` environment:

```bash
docker compose exec frontend php /app/init --env=Development --overwrite=All
```

After running `init`, update the database connection in `common/config/main-local.php` to use the `mysql`
service hostname:

```php
'db' => [
    'class' => \yii\db\Connection::class,
    'dsn' => 'mysql:host=mysql;dbname=yii2advanced',
    'username' => 'yii2advanced',
    'password' => 'secret',
    'charset' => 'utf8',
],
```

You can then access the application through the following URLs:

```
http://127.0.0.1:20080  (frontend)
http://127.0.0.1:21080  (backend)
```

To run the test suite, also update `common/config/test-local.php` to use the `mysql` hostname and create the
test database:

```php
'db' => [
    'dsn' => 'mysql:host=mysql;dbname=yii2advanced_test',
],
```

```bash
docker compose exec -T mysql mysql -uroot -pverysecret -e "CREATE DATABASE IF NOT EXISTS yii2advanced_test; GRANT ALL PRIVILEGES ON yii2advanced_test.* TO 'yii2advanced'@'%'; FLUSH PRIVILEGES;"
docker compose exec -T frontend php /app/yii_test migrate --interactive=0
docker compose exec -T frontend vendor/bin/codecept build
docker compose exec -T frontend vendor/bin/codecept run
```

**NOTES:**
- Minimum required Docker engine version `17.04` for development (see [Performance tuning for volume mounts](https://docs.docker.com/docker-for-mac/osxfs-caching/))
- The default configuration uses a host-volume in your home directory `~/.composer-docker/cache` for Composer caches

CONFIGURATION
-------------

## Database

Edit the file `common/config/main-local.php` with real data, for example:

```php
return [
    'components' => [
        'db' => [
            'class' => \yii\db\Connection::class,
            'dsn' => 'mysql:host=localhost;dbname=yii2advanced',
            'username' => 'root',
            'password' => '1234',
            'charset' => 'utf8',
        ],
    ],
];
```

When using Docker, the MySQL service is pre-configured. Update `common/config/main-local.php` to use:

```php
'db' => [
    'class' => \yii\db\Connection::class,
    'dsn' => 'mysql:host=mysql;dbname=yii2advanced',
    'username' => 'yii2advanced',
    'password' => 'secret',
    'charset' => 'utf8',
],
```

Apply migrations:

```bash
php yii migrate
```

Or with Docker:

```bash
docker compose exec frontend php /app/yii migrate
```

**NOTES:**
- Yii won't create the database for you, this has to be done manually before you can access it.
  When using Docker, the MySQL service creates the database automatically.
- Check and edit the other files in the `config/` directories to customize your application as required.
- Refer to the README in the `tests` directory for information specific to application tests.

TESTING
-------

Tests are located in `frontend/tests`, `backend/tests`, and `common/tests` directories.
They are developed with [Codeception PHP Testing Framework](https://codeception.com/).

Tests can be executed by running:

```bash
vendor/bin/codecept run --env php-builtin
```

Or using the Composer script:

```bash
composer tests
```

## Support the project

[![Open Collective](https://img.shields.io/badge/Open%20Collective-sponsor-7eadf1?style=for-the-badge&logo=open%20collective&logoColor=7eadf1&labelColor=555555)](https://opencollective.com/yiisoft)

## Follow updates

[![Official website](https://img.shields.io/badge/Powered_by-Yii_Framework-green.svg?style=for-the-badge&logo=yii)](https://www.yiiframework.com/)
[![Follow on X](https://img.shields.io/badge/-Follow%20on%20X-1DA1F2.svg?style=for-the-badge&logo=x&logoColor=white&labelColor=000000)](https://x.com/yiiframework)
[![Telegram](https://img.shields.io/badge/telegram-join-1DA1F2?style=for-the-badge&logo=telegram)](https://t.me/yii_framework_in_english)
[![Slack](https://img.shields.io/badge/slack-join-1DA1F2?style=for-the-badge&logo=slack)](https://yiiframework.com/go/slack)

## License

[![License](https://img.shields.io/badge/License-BSD--3--Clause-brightgreen.svg?style=for-the-badge&logo=opensourceinitiative&logoColor=white&labelColor=555555)](LICENSE.md)
=======


# Synapse / 突触 （暂定名）
神经元与另一个神经元、肌肉细胞或腺细胞之间进行信号传递的连接部位，是神经系统中细胞彼此“交接信息”的关键位置。它是一个名词，复数形式是 synapses。



一个个人内容管理网站。内容包括  博客 图集 瞬间(微博) 短视频 资源(文件共享)（内容阅读有严格的权限）。  
站点和站点之间 可以聚合 , 关注(输入另一个站的网址后) 在自己网站可见 人家授权的信息 在自己的站点也可以查看 也可以跳转到原文。 自己的内容发在自己的站点上，别人看见后可以引用或二创后转发到自己的网站。

**自己为自己发布的内容负责，转发的人为自己转发的内容负责。**  每个人或站点就像一个网络节点，更像两个神经元的交汇点 **Synapse**
>>>>>>> 3050e94bdb43fed343add3a47d5aac4a4cfe0766
