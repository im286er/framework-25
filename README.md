# framework
一个简洁的php框架，在实际应用中，不是一个通用框架。

# 安装
php composer.phar require huangshaowen/framework:dev-master

框架结构目录

├── README.md                                       说明文件
├── src                                             框架源码
│   ├── core                                 
│   │   ├── Action.php                          控制器基类
│   │   ├── App.php                             应用运行
│   │   ├── Config.php                          配置设置与获取
│   │   ├── Dispatcher.php                      应用分发
│   │   ├── Loader.php                          框架加载器
│   │   ├── Log.php                             日志工具
│   │   ├── Request.php                         获取http请求
│   │   ├── Response.php                        http响应
│   │   ├── Route.php                           路由解析
│   │   └── View.php                            模板类
│   ├── db
│   │   ├── Driver
│   │   │   ├── DbDriver.php                数据库驱动基类
│   │   │   ├── MSSQL.php                   微软数据库
│   │   │   ├── MySQL.php                   MySQL数据库
│   │   │   └── PGSQL.php                   PgSql数据库
│   │   └── Model
│   │       ├── MSSQLModel.php                 mssql模型类
│   │       ├── MYSQLModel.php                 mysql模型类
│   │       └── PGSQLModel.php                 pgsql模型类
│   ├── nosql
│   │   └── Cache.php                          memcached缓存类
│   │   ├── SimpleSSDB.php                     ssdb基类供ssdbService.php调用
│   │   └── ssdbService.php                    ssdb 服务类
│   └── tpl
│       └── dispatch_jump.tpl.php                 跳转模板

