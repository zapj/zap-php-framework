<?php
namespace zap\console;

class Output {

    private $stdout;
    private $stderr;
    private Input $input;
    protected bool $verbose1 = false;
    protected bool $verbose2 = false;
    protected bool $verbose3 = false;

    public function __construct(Input $input)
    {
        $this->stdout = $this->openOutputStream();
        $this->stderr = $this->openErrorStream();
        $this->input = $input;

        $this->verbose1 = $this->input->hasParam('v');

        if($this->input->hasParam('vv')){
            $this->verbose1 = $this->verbose2 = true;
        }
        if($this->input->hasParam('vvv')){
            $this->verbose1 = $this->verbose2 = $this->verbose3 = true;
        }
    }

    /**
     * @return false|resource
     */
    public function getStderr(): bool
    {
        return $this->stderr;
    }

    private function openOutputStream()
    {
        return \defined('STDOUT') ? \STDOUT : (@fopen('php://stdout', 'w') ?: fopen('php://output', 'w'));
    }


    private function openErrorStream()
    {
       return \defined('STDERR') ? \STDERR : (@fopen('php://stderr', 'w') ?: fopen('php://output', 'w'));
    }

    protected function hasColorSupport(): bool
    {
        // Follow https://no-color.org/
        if (isset($_SERVER['NO_COLOR']) || false !== getenv('NO_COLOR')) {
            return false;
        }

        if ('Hyper' === getenv('TERM_PROGRAM')) {
            return true;
        }

        if (\DIRECTORY_SEPARATOR === '\\') {
            return false !== getenv('ANSICON')
                || 'ON' === getenv('ConEmuANSI')
                || 'xterm' === getenv('TERM');
        }

        return stream_isatty($this->stdout);
    }

    public function write($data)
    {
        return fwrite($this->stdout,$data);
    }

    public function writeln($data)
    {
        return fwrite($this->stdout,$data . PHP_EOL);
    }

    public function printf($fmt,...$args): int
    {
        return fprintf($this->stdout,$fmt ,$args);
    }

    public function printlnV($fmt,...$args): int
    {
        if($this->verbose1){
            return fprintf($this->stdout,$fmt . PHP_EOL ,$args);
        }
        return 0;
    }

    public function printlnVV($fmt,...$args): int
    {
        if($this->verbose2){
            return fprintf($this->stdout,$fmt . PHP_EOL ,$args);
        }
        return 0;
    }

    public function printlnVVV($fmt,...$args): int
    {
        if($this->verbose3){
            return fprintf($this->stdout,$fmt . PHP_EOL ,$args);
        }
        return 0;
    }


}