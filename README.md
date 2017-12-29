# framework
一个简洁的php框架，已经在生产环境中使用，不是一个通用框架。

# 安装
php composer.phar require huangshaowen/framework:dev-master


# 开始使用

/* 入口文件 */
----------------------- /index.php ---------------------------------------------
/* 定义根目录 */
define('ROOT_PATH', __DIR__ . '/');
/* 定义项目路径 */
define('APP_PATH', __DIR__ . '/apps/');
/* 引入框架 */
require( ROOT_PATH . 'vendor/autoload.php' );
/* 引入站点配置文件 */
//  \framework\core\Config::load(ROOT_PATH . 'data/config.php');
/* 应用类库命名空间 */
\framework\core\Loader::addNamespace(
        [
            'admin' => APP_PATH . 'admin/',
            'common' => APP_PATH . 'common/',
            'www' => APP_PATH . 'www/',
        ]
);
/* 启动程序 */
\framework\core\App::run();
---------------- end /index.php   ----------------------------------------------



/* 测试程序 */

---------------- /apps/www/action/index.php   ----------------------------------

namespace www\action;
use framework\core\Action;
class index extends Action {
    public function index() {
        $this->ajaxReturn(['ret'=>200,'data'=>null,'msg'=>'欢迎使用']);
    }
}

---------------- end /apps/www/action/index.php   ------------------------------






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

