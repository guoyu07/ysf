<?php
/**
 * @link https://github.com/stelin/ysf
 * @copyright Copyright 2016-2017 stelin develper.
 * @license https://github.com/stelin/ysf/license/
 */
namespace ysf\base;

use ysf\web\UrlManager;
use ysf\Ysf;
use ysf\exception\InvalidParamException;
use ysf\exception\InvalidConfigException;
use ysf\di\ServiceLocator;
use ysf\log\Logger;

/**
 * base application
 *
 * @property \ysf\web\UrlManager $urlManager The URL manager for this application. This property is read-only.
 * @property \ysf\log\Dispatcher $log log is
 * 
 * @author stelin <phpcrazy@126.com>
 * @since 0.1
 */
abstract class Application extends ServiceLocator{
    
    public $id;
    public $name;
    public $basePath;
    public $components;
    public $params = [];
    public $runtimePath;
    public $settingPath;
    public $defaultRoute = "/index/index";
    public $controllerNamespace = 'app\\controllers';
    
    
    
    /**
     * @var \Swoole\Http\Server
     */
    public $server = null;
    
    public function __construct($config)
    {
        $this->preInit($config);
        $this->setComponents($config['components']);
        
        parent::__construct($config);
        
        // 初始化日志
        $this->log;
        
        // 错误处理
        register_shutdown_function([$this, 'handlerFataError']);
        set_error_handler([$this, 'handlerError']);
    }
    
    public function coreComponents()
    {
        return [
            'urlManager' => ['class' => 'ysf\web\UrlManager'],
            'log' => ['class' => 'ysf\log\Dispatcher'],
        ];
    }
    
    /**
     * Sets the root directory of the module.
     * This method can only be invoked at the beginning of the constructor.
     * @param string $path the root directory of the module. This can be either a directory name or a path alias.
     * @throws InvalidParamException if the directory does not exist.
     */
    public function setBasePath($path)
    {
        $path = Ysf::getAlias($path);
        $p = strncmp($path, 'phar://', 7) === 0 ? $path : realpath($path);
        if ($p !== false && is_dir($p)) {
            $this->basePath = $p;
        } else {
            throw new InvalidParamException("The directory does not exist: $path");
        }
    }
    
    /**
     * Sets the directory that stores runtime files.
     * @param string $path the directory that stores runtime files.
     */
    public function setRuntimePath($path)
    {
        $this->runtimePath = Ysf::getAlias($path);
        Ysf::setAlias('@runtime', $this->runtimePath);
    }
    
    /**
     * Returns the directory that stores runtime files.
     * @return string the directory that stores runtime files.
     * Defaults to the "runtime" subdirectory under [[basePath]].
     */
    public function getRuntimePath()
    {
        if ($this->runtimePath === null) {
            $this->setRuntimePath($this->getBasePath() . DIRECTORY_SEPARATOR . 'runtime');
        }
    
        return $this->runtimePath;
    }
    
    /**
     * Returns the root directory of the module.
     * It defaults to the directory containing the module class file.
     * @return string the root directory of the module.
     */
    public function getBasePath()
    {
        if ($this->basePath === null) {
            $class = new \ReflectionClass($this);
            $this->basePath = dirname($class->getFileName());
        }
    
        return $this->basePath;
    }
    
    /**
     * Sets the time zone used by this application.
     * This is a simple wrapper of PHP function date_default_timezone_set().
     * Refer to the [php manual](http://www.php.net/manual/en/timezones.php) for available timezones.
     * @param string $value the time zone used by this application.
     * @see http://php.net/manual/en/function.date-default-timezone-set.php
     */
    public function setTimeZone($value)
    {
        date_default_timezone_set($value);
    }
    
    public function preInit(&$config)
    {
        if (!isset($config['id'])) {
            throw new InvalidConfigException('The "id" configuration for the Application is required.');
        }
        if (isset($config['basePath'])) {
            $this->setBasePath($config['basePath']);
            unset($config['basePath']);
        } else {
            throw new InvalidConfigException('The "basePath" configuration for the Application is required.');
        }
    
        if (isset($config['runtimePath'])) {
            $this->setRuntimePath($config['runtimePath']);
            unset($config['runtimePath']);
        } else {
            // set "@runtime"
            $this->getRuntimePath();
        }
    
        if (isset($config['timeZone'])) {
            $this->setTimeZone($config['timeZone']);
            unset($config['timeZone']);
        } elseif (!ini_get('date.timezone')) {
            $this->setTimeZone('UTC');
        }
    
        // merge core components with custom components
        foreach ($this->coreComponents() as $id => $component) {
            if (!isset($config['components'][$id])) {
                $config['components'][$id] = $component;
            } elseif (is_array($config['components'][$id]) && !isset($config['components'][$id]['class'])) {
                $config['components'][$id]['class'] = $component['class'];
            }
        }
    }
    
    public function createController($route)
    {
        list($controllerId, $actionId) = $this->getPathRoute($route);
        
        $controller = ObjectPool::getInstance()->getObject($controllerId);
        if($controller == null){
            $controller = $this->createControllerById($controllerId);
        }
        
        if ($controller === null && $route !== '') {
            $controller = $this->createControllerByID($controllerId . '/' . $actionId);
            $route = '';
        }
        
        if($controller === null){
            // exceptions
        }
        
        return [$controller, $actionId];
    }
    
       
    public function getPathRoute($route)
    {
        if ($route === '') {
            $route = $this->defaultRoute;
        }
        
        $route = trim($route, '/');
        if (strpos($route, '//') !== false) {
            return false;
        }
        
        if (strpos($route, '/') !== false) {
            list ($id, $route) = explode('/', $route, 2);
        } else {
            $id = $route;
            $route = '';
        }
        
        if (($pos = strrpos($route, '/')) !== false) {
            $id .= '/' . substr($route, 0, $pos);
            $route = substr($route, $pos + 1);
        }
        
        return [$id, $route];
    }
    
    /**
     * Creates a controller based on the given controller ID.
     *
     * The controller ID is relative to this module. The controller class
     * should be namespaced under [[controllerNamespace]].
     *
     * Note that this method does not check [[modules]] or [[controllerMap]].
     *
     * @param string $id the controller ID
     * @return Controller the newly created controller instance, or null if the controller ID is invalid.
     * @throws InvalidConfigException if the controller class and its file name do not match.
     * This exception is only thrown when in debug mode.
     */
    public function createControllerById($id)
    {
        $pos = strrpos($id, '/');
        if ($pos === false) {
            $prefix = '';
            $className = $id;
        } else {
            $prefix = substr($id, 0, $pos + 1);
            $className = substr($id, $pos + 1);
        }

    
        // 匹配正则修改兼容controller LoginUser/testOne loginUser/testOne login-user/testOne
        if (!preg_match('%^[a-zA-Z][a-zA-Z0-9\\-_]*$%', $className)) {
            return null;
        }
        if ($prefix !== '' && !preg_match('%^[a-z0-9_/]+$%i', $prefix)) {
            return null;
        }
    
        // namespace和prefix保持一致，搜字母都大写或都小写，namespace app\controllers\SecurityKey; prefix=SecurityKey
        $className = str_replace(' ', '', ucwords(str_replace('-', ' ', $className))) . 'Controller';
        $className = ltrim($this->controllerNamespace . '\\' . str_replace('/', '\\', $prefix)  . $className, '\\');
        if (strpos($className, '-') !== false || !class_exists($className)) {
            return null;
        }
    
        $isConsole = defined('CONSOLE') && CONSOLE == 1 && is_subclass_of($className, 'ysf\console\Controller');
        if (is_subclass_of($className, 'ysf\base\Controller') || $isConsole) {
            $controller = new $className($id);
            return get_class($controller) === $className ? $controller : null;
        }else{
            return null;
        }
    }
    
    public function handlerFataError()
    {
        $error = error_get_last();
        if (isset($error['type'])) {
            $message = $error['message'];
            $file = $error['file'];
            $line = $error['line'];
            Ysf::error($filename.":".$line." ".$error_string);
        }
    }
    
    public function getUrlManager()
    {
        return $this->get('urlManager');
    }
    
    public function getLog()
    {
        return $this->get('log');
    }
    
    public function handlerError($error, $error_string, $filename, $line, $symbols)
    {
        Ysf::error($filename.":".$line." ".$error_string);
    }
    
    abstract public function run();
}