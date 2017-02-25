<?php
/**
 * @link https://github.com/stelin/ysf
 * @copyright Copyright 2016-2017 stelin develper.
 * @license https://github.com/stelin/ysf/license/
 */
namespace ysf\base;

/**
 * base application
 *
 * @author stelin <phpcrazy@126.com>
 * @since 0.1
 */
abstract class Application extends Component{
    public $id = "";
    public $tcp = [];
    public $http = [];
    public $params = [];
    public $tcpEnable = true;
    public $processName = "php-ysf";
    private $version = "0.1";
    
    
    /**
     * @var \Swoole\Http\Server
     */
    public $server = null;
    
    public function __construct($config)
    {
        $this->initServer($config);
        
        parent::__construct($config);
    }
    
    public function initServer(&$config)
    {
        $serverConfigs = [];
        if(isset($config['configs'])){
            $serverConfigs = $config['configs'];
            unset($config['configs']);
        }
        if(isset($serverConfigs['http'])){
            $this->http = $serverConfigs['http'];
        }
        if(isset($serverConfigs['tcp'])){
            $this->tcp = $serverConfigs['tcp'];
        }
    }
    
    /**
     * 运行服务
     */
    public function run()
    {
        global $argv;
        var_dump($argv);
        $this->start();
    }
    
    public abstract function start();
    
    public function getVersion()
    {
        return $this->version;
    }
}