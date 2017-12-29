<?php

namespace framework\core;

class Loader {

    // 类名映射
    protected static $map = [];
    // PSR-4
    private static $prefixLengthsPsr4 = [];
    private static $prefixDirsPsr4 = [];
    private static $fallbackDirsPsr4 = [];
    // PSR-0
    private static $prefixesPsr0 = [];
    private static $fallbackDirsPsr0 = [];
    // 自动加载的文件
    private static $autoloadFiles = [];

    /**
     * 查找文件
     * @param $class
     * @return bool
     */
    private static function findFile($class) {
        if (!empty(self::$map[$class])) {
            // 类库映射
            return self::$map[$class];
        }

        // 查找 PSR-4
        $logicalPathPsr4 = strtr($class, '\\', '/') . '.php';

        $first = $class[0];
        if (isset(self::$prefixLengthsPsr4[$first])) {
            foreach (self::$prefixLengthsPsr4[$first] as $prefix => $length) {
                if (0 === strpos($class, $prefix)) {
                    foreach (self::$prefixDirsPsr4[$prefix] as $dir) {
                        if (is_file($file = $dir . '/' . substr($logicalPathPsr4, $length))) {
                            return $file;
                        }
                    }
                }
            }
        }

        // 查找 PSR-4 fallback dirs
        foreach (self::$fallbackDirsPsr4 as $dir) {
            if (is_file($file = $dir . '/' . $logicalPathPsr4)) {
                return $file;
            }
        }

        // 查找 PSR-0
        if (false !== $pos = strrpos($class, '\\')) {
            // namespaced class name
            $logicalPathPsr0 = substr($logicalPathPsr4, 0, $pos + 1)
                    . strtr(substr($logicalPathPsr4, $pos + 1), '_', '/');
        } else {
            // PEAR-like class name
            $logicalPathPsr0 = strtr($class, '_', '/') . '.php';
        }

        if (isset(self::$prefixesPsr0[$first])) {
            foreach (self::$prefixesPsr0[$first] as $prefix => $dirs) {
                if (0 === strpos($class, $prefix)) {
                    foreach ($dirs as $dir) {
                        if (is_file($file = $dir . '/' . $logicalPathPsr0)) {
                            return $file;
                        }
                    }
                }
            }
        }

        // 查找 PSR-0 fallback dirs
        foreach (self::$fallbackDirsPsr0 as $dir) {
            if (is_file($file = $dir . '/' . $logicalPathPsr0)) {
                return $file;
            }
        }

        return self::$map[$class] = false;
    }

    // 注册classmap
    public static function addClassMap($class, $map = '') {
        if (is_array($class)) {
            self::$map = array_merge(self::$map, $class);
        } else {
            self::$map[$class] = $map;
        }
    }

    // 注册命名空间
    public static function addNamespace($namespace, $path = '') {
        if (is_array($namespace)) {
            foreach ($namespace as $prefix => $paths) {
                self::addPsr4($prefix . '\\', rtrim($paths, '/'), true);
            }
        } else {
            self::addPsr4($namespace . '\\', rtrim($path, '/'), true);
        }
    }

    // 添加Ps0空间
    private static function addPsr0($prefix, $paths, $prepend = false) {
        if (!$prefix) {
            if ($prepend) {
                self::$fallbackDirsPsr0 = array_merge(
                        (array) $paths, self::$fallbackDirsPsr0
                );
            } else {
                self::$fallbackDirsPsr0 = array_merge(
                        self::$fallbackDirsPsr0, (array) $paths
                );
            }

            return;
        }

        $first = $prefix[0];
        if (!isset(self::$prefixesPsr0[$first][$prefix])) {
            self::$prefixesPsr0[$first][$prefix] = (array) $paths;

            return;
        }
        if ($prepend) {
            self::$prefixesPsr0[$first][$prefix] = array_merge(
                    (array) $paths, self::$prefixesPsr0[$first][$prefix]
            );
        } else {
            self::$prefixesPsr0[$first][$prefix] = array_merge(
                    self::$prefixesPsr0[$first][$prefix], (array) $paths
            );
        }
    }

    // 添加Psr4空间
    private static function addPsr4($prefix, $paths, $prepend = false) {
        if (!$prefix) {
            // Register directories for the root namespace.
            if ($prepend) {
                self::$fallbackDirsPsr4 = array_merge(
                        (array) $paths, self::$fallbackDirsPsr4
                );
            } else {
                self::$fallbackDirsPsr4 = array_merge(
                        self::$fallbackDirsPsr4, (array) $paths
                );
            }
        } elseif (!isset(self::$prefixDirsPsr4[$prefix])) {
            // Register directories for a new namespace.
            $length = strlen($prefix);
            if ('\\' !== $prefix[$length - 1]) {
                throw new \InvalidArgumentException("A non-empty PSR-4 prefix must end with a namespace separator.");
            }
            self::$prefixLengthsPsr4[$prefix[0]][$prefix] = $length;
            self::$prefixDirsPsr4[$prefix] = (array) $paths;
        } elseif ($prepend) {
            // Prepend directories for an already registered namespace.
            self::$prefixDirsPsr4[$prefix] = array_merge(
                    (array) $paths, self::$prefixDirsPsr4[$prefix]
            );
        } else {
            // Append directories for an already registered namespace.
            self::$prefixDirsPsr4[$prefix] = array_merge(
                    self::$prefixDirsPsr4[$prefix], (array) $paths
            );
        }
    }

    /**
     * Registers this instance as an autoloader.
     *
     * @param bool $prepend Whether to prepend the autoloader or not
     */
    public function register() {
        // 注册框架自动加载
        spl_autoload_register("\\framework\\core\\Loader::autoload", true, true);

        /* Composer自动加载支持 */
        if (defined(VENDOR_PATH) && is_dir(VENDOR_PATH . 'composer')) {
            self::registerComposerLoader();
        }
    }

    // 注册composer自动加载
    private static function registerComposerLoader() {
        if (is_file(VENDOR_PATH . 'composer/autoload_namespaces.php')) {
            $map = require VENDOR_PATH . 'composer/autoload_namespaces.php';
            foreach ($map as $namespace => $path) {
                self::addPsr0($namespace, $path);
            }
        }

        if (is_file(VENDOR_PATH . 'composer/autoload_psr4.php')) {
            $map = require VENDOR_PATH . 'composer/autoload_psr4.php';
            foreach ($map as $namespace => $path) {
                self::addPsr4($namespace, $path);
            }
        }

        if (is_file(VENDOR_PATH . 'composer/autoload_classmap.php')) {
            $classMap = require VENDOR_PATH . 'composer/autoload_classmap.php';
            if ($classMap) {
                self::addClassMap($classMap);
            }
        }

        if (is_file(VENDOR_PATH . 'composer/autoload_files.php')) {
            $includeFiles = require VENDOR_PATH . 'composer/autoload_files.php';
            foreach ($includeFiles as $fileIdentifier => $file) {
                if (empty(self::$autoloadFiles[$fileIdentifier])) {
                    __include_file($file);
                    self::$autoloadFiles[$fileIdentifier] = true;
                }
            }
        }
    }

    /**
     * 自动加载
     *
     * @param  string    $class The name of the class
     * @return bool|null True if loaded, null otherwise
     */
    public static function autoload($class) {
        /* 先采用 psr4标准 加载 */
        if ($file = self::findFile($class)) {
            __include_file($file);

            return true;
        }
        
        /* 旧加载方式 */
        $class_file = $class . '.class.php';

        if (substr($class, -5) == 'Model') {
            /* 加载数据层 */
            $class_file = 'model/' . $class_file;
        } elseif (substr($class, -7) == 'Service') {
            /* 加载服务层 */
            $class_file = 'service/' . $class_file;
        } elseif (substr($class, -5) == 'Logic') {
            /* 加载逻辑层 */
            $class_file = 'logic/' . $class_file;
        } elseif (substr($class, -6) == 'Action') {
            /* 加载控制 */
            $class_file = 'action/' . $class_file;
        } elseif ((substr($class, -6) == 'Widget') && ($class != 'Widget')) {
            /* 加载Widget */
            $class_file = 'widget/' . $class_file;
        } else {
            /* 默认应用下公共 class */
            $class_file = 'class/' . $class_file;
        }
        /* 判断是否启用分组 */
        if (defined('GROUP_NAME')) {
            $file = APP_PATH . GROUP_NAME . '/' . $class_file;
        } else {
            $file = APP_PATH . $class_file;
        }
        if ((is_file($file)) && (file_exists($file))) {
            require $file;
            return true;
        }
        /* 加载公共 */
        $file = APP_PATH . 'common/' . $class_file;
        if ((is_file($file)) && (file_exists($file))) {
            require $file;
            return true;
        }
        
        return false;
    }

    /**
     * 字符串命名风格转换
     * type 0 将 Java 风格转换为 C 的风格 1 将 C 风格转换为 Java 的风格
     * @access public
     * @param  string  $name    字符串
     * @param  integer $type    转换类型
     * @param  bool    $ucfirst 首字母是否大写（驼峰规则）
     * @return string
     */
    public static function parseName($name, $type = 0, $ucfirst = true) {
        if ($type) {
            $name = preg_replace_callback('/_([a-zA-Z])/', function ($match) {
                return strtoupper($match[1]);
            }, $name);

            return $ucfirst ? ucfirst($name) : lcfirst($name);
        }

        return strtolower(trim(preg_replace("/[A-Z]/", "_\\0", $name), "_"));
    }

}

/**
 * 作用范围隔离
 *
 * @param $file
 * @return mixed
 */
function __include_file($file) {
    return include $file;
}
