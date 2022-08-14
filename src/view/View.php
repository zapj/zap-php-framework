<?php

namespace zap\view;


use zap\util\Str;

class View {

    public $layout = false;

    public $viewName;

    public $viewFile;

    public $params = [];

    public $blocks = [];

    private $_blocksStack = [];

    /**
     * @var \zap\view\PHPRenderer
     */
    private $engine;



    public function __construct($name = null,$data = []){
        $this->params = $data;
        $this->viewName = $name;
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

    public function beginBlock($name) {
        ob_start();
        $this->_blocksStack[] = $name;
    }

    public function endBlock() {
        $blockName = array_pop($this->_blocksStack);
        $this->blocks[$blockName] = ob_end_clean();
    }

    public static function make($name = null,$data = []){
        return new View($name,$data);
    }

    public function display($return = false){
        return $this->engine->render($return);
    }

    private function prepare($name){

        $this->viewFile = $this->resolveTemplate($name);
        if(is_null($this->viewFile)){
            die('Template not found');
        }
        $this->initViewRenderer();
    }

    private function resolveTemplate($template){
        $template = str_replace('.','/',$template);
        $templatePaths = [
            resource_path('/views/'.$template.'.php'),
            resource_path('/views/'.$template.'.twig.php')
        ];
        if(($theme = config('config.theme',false)) !== false){
            $templatePaths[] = themes_path("/$theme/".$template.'.php');
            $templatePaths[] = themes_path("/$theme/".$template.'.twig.php');
        }
        if(($theme_extend = config('config.theme_extend',false)) !== false){
            $templatePaths[] = themes_path("/$theme_extend/".$template.'.php');
            $templatePaths[] = themes_path("/$theme_extend/".$template.'.twig.php');
        }
        foreach($templatePaths as $tplFullPath){
            if(is_file($tplFullPath)){
                return $tplFullPath;
            }
        }
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
        }
        $this->engine = new PHPRenderer($this);
    }



}