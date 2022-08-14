<?php

namespace zap;

class ZErrorHandler
{

}

set_error_handler("_gc_error_handler");
set_exception_handler('_gc_exception_handler');
register_shutdown_function('_gc_shutdown_handler');

// class GC_Error {}

function _gc_shutdown_handler() {
    if ($error = error_get_last()) {
        // print_r($error); die;
        if (ob_get_length()) {
            ob_end_clean();
        }

        $html = gc_highlight_file($error['file'], $error['line'], $error['message'], 'shut');
        GC_View::render(GCINC . '/res/tpl/error', ['html' => $html]);
        exit;
    }
}

function _gc_error_handler($errno, $errstr, $error_file, $error_line) {
    if ($errno == E_USER_ERROR || $errno == E_USER_WARNING) {
        if (ob_get_length()) {
            ob_end_clean();
        }
        $html = gc_highlight_file($error_file, $error_line, $errstr, 'PHP错误 Errno:' . $errno);
        GC_View::render(GCINC . '/res/tpl/error', ['html' => $html]);
        exit;
    }
}

function _gc_exception_handler($exception) {


    if (ob_get_length()) {
        ob_end_clean();
    }
    // $html = gc_highlight_file($exception->getFile(), $exception->getLine(), $exception->getMessage(), get_class($exception) . ' 异常');
    GC_View::render(GCINC . '/res/tpl/exception', ['exception' => $exception]);

}

function _gc_read_file($filename, $line_no, $offset = 5) {
    $start = ($line_no-$offset) < 0 ?: 0;
    $end = $line_no+$offset;
    $fp = fopen($filename, "r") or die("Can't open $filename");
    $lines = array();
    $_line_no = 0;
    while (!feof($fp)) {
        $line = fgets($fp);
        if ($_line_no < $start)
            continue;
        array_push($lines, $line);
        $_line_no++;
        if ($_line_no >= $end)
            break;
    }
    fclose($fp);
    return $lines;
}

function gc_highlight_file($filename, $line_no, $message = '', $title = '错误信息', $offset = 5) {
    // echo $line_no-$offset;
    $start = ($line_no-$offset) > 1  ? $line_no-$offset : 1;

    $end = $line_no+$offset;
    $li_start = $start;
    // $fp = fopen($filename, "r") or die("Can't open $filename");
    // $lines = '';
    // $_line_no = 0;
    // while (!feof($fp)) {
    //     $_line_no++;
    //     $line = fgets($fp);
    //     if ($_line_no < $start)
    //         continue;
    //     $lines .= $line;

    //     if ($_line_no >= $end)
    //         break;
    // }
    // fclose($fp);

    // $html_content = highlight_string("<?php\n".$lines, true);

    // $code = substr($html_content, 36, -15);
    // $lines = explode('<br />', $code);
    // $lines = array_combine(range(1, count($lines)), $lines);
    $code = substr(highlight_file($filename, true), 36, -15);
    //Split lines
    $lines = explode('<br />', $code);

    $lines = array_slice($lines, $start, 10);
    $line_count = count($lines);

    $pad_length = strlen($line_count);

    $return = '';
    if ($message) {
        $return .= "<div style=\"padding: 5px;color: #545454;background-color: #d8d8d8;border: 1px solid #b1b1b1;\">$title</div>";
        $return .= "<div style=\"padding: 5px;color: #545454;border: 1px solid #b1b1b1;\">$message</div><br/>";
    }
    $return .= "<div style=\"padding: 5px;color: #545454;background-color: #d8d8d8;border: 1px solid #b1b1b1;\">文件名: $filename</div>";
    $return .= '<div style="width: 100%; display: inline-block; display: flex;border: 1px solid #cacaca;"><code style="width: 100%;"><ol start="'.$li_start.'">';

    foreach ($lines as $i => $line) {

        $lineNumber = str_pad($i + 1, $pad_length, '0', STR_PAD_LEFT);

        if ($i % 2 == 0) {

            $numbgcolor = '#C8E1FA';

            $linebgcolor = '#F7F7F7';

            $fontcolor = '#3F85CA';

        } else {

            $numbgcolor = '#DFEFFF';

            $linebgcolor = '#FDFDFD';

            $fontcolor = '#5499DE';

        }



        if ($line == '') $line = '&nbsp;';
        if ($start+1 == $line_no) {
            $linebgcolor = "#fbcbcb";
        }

        $return .= '<li ><div style="background-color: ' . $linebgcolor . '; width: 100%;display: inline-block;">' . $line . '</div></li>';
        $start++;
    }

    $return .= '</ol></code></div>';



    return $return;

}