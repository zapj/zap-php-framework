<?php

namespace zap\view;


use zap\exception\ViewNotFoundException;
use zap\util\Str;

class View {

    public $layout = false;

    public $viewName;

    public $viewFile;

    public $params = [];

    public $blocks = [];

    private $_blocksStack = [];

    protected $templatePaths = [];

    /**
     * @var \zap\view\PHPRenderer
     */
    private $engine;



    public function __construct($name = null,$data = []){
        $this->params = $data;
        $this->viewName = $name;

        if(($theme = config('config.theme',false)) !== false){
            array_unshift($this->templatePaths,themes_path("/$theme"));
        }else{
            array_unshift($this->templatePaths,resource_path('/views'));
        }
        if(config('config.set_theme_include_path',false) === false){
            set_include_path(get_include_path() . PATH_SEPARATOR .  join(PATH_SEPARATOR,$this->templatePaths));
            config_set('config.set_theme_include_path',true);
        }
        $this->prepare($name);
    }

    public function __get($name)
    {
        if(isset($this->params[$name])){
            return $this->params[$name];
        }
        return null;
    }

    public function __set($name, $value)
    {
        $this->params[$name] = $value;
    }

    public function __isset($name)
    {
        return isset($this->params[$name]);
    }

    public function __unset($name)
    {
        unset($this->params[$name]);
    }

    public function getViewFile()
    {
        return $this->viewFile;
    }

    public function layout($layout) {
        $this->layout = $this->resolveTemplate($layout);
    }

    public function block($name) {
        return isset($this->blocks[$name]) ? $this->blocks[$name] : '';
    }

    public function addViewPath($path){
        $this->templatePaths[] = $path;
        return $this;
    }

    public function beginBlock($name) {
        ob_start();
        $this->_blocksStack[] = $name;
        echo $name;
    }

    public function endBlock() {
        $blockName = array_pop($this->_blocksStack);
        $this->blocks[$blockName] = rtrim(ob_get_clean());
    }

    public static function make($name = null,$data = []){
        return new View($name,$data);
    }

    public function display($return = false){
        Block::$view = $this;
        return $this->engine->render($return);
    }

    private function prepare($name){
        $this->viewFile = $this->resolveTemplate($name);
        if(is_null($this->viewFile)){
            throw new ViewNotFoundException('Template file not found');
        }
        $this->initViewRenderer();
    }

    private function resolveTemplate($template){
        $template = str_replace('.','/',$template);
        foreach($this->templatePaths as $tplPath){
            $tplFullPath = $tplPath . '/' . $template .'.php';
            if(is_file($tplFullPath)){
                return $tplFullPath;
            }
            $tplFullPath = $tplPath . '/' . $template .'.twig.php';
            if(is_file($tplFullPath)){
                return $tplFullPath;
            }
        }
        return null;
    }



    /**
     * 渲染模板
     * @param string $template 模板路径
     * @param array $data 参数
     * @param bool $output 为true返回模板内容，false输出内容
     * @return string
     */
    public static function render($template, $data = array(), $return = false) {
        $view = View::make($template,$data);

        return $view->display($return);
    }

    private function initViewRenderer(){
        if(Str::endsWith($this->viewFile,'.twig.php')){
            $this->engine = new TwigViewRenderer($this);
        }else{
            $this->engine = new PHPRenderer($this);
        }
    }

}