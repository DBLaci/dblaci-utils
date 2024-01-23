<?php

/**
 * kiíratás htmlbe, vagy parancssorba
 *
 * @param string $s
 * @param string $mode? html / cli (default auto = auto detect)
 */
function echo2($s, $mode = 'auto')
{
    if ( $mode == 'html' || ( $mode == 'auto' && PHP_SAPI != 'cli' ) ) {
        echo $s;
        return;
    }
    $s = preg_replace("/<br\s*\\/?>/", "\n", $s);
    $s = preg_replace("/<b>(.*?)<\\/b>/m", "\033[1m\$1\033[0m", $s);
    $s = preg_replace("/<pre>(.*?)<\\/pre>/m", "\033[1m\$1\033[0m", $s);
    $s = preg_replace("/<span class=\"error_msg\">(.*?)<\\/span>/m", "\033[31m\$1\033[0m", $s);
    $s = preg_replace("/<span class=\"ok_msg\">(.*?)<\\/span>/m", "\033[32m\$1\033[0m", $s);
    $s = preg_replace("/<span class=\"warning_msg\">(.*?)<\\/span>/m", "\033[33m\$1\033[0m", $s);
    $s = preg_replace("/<div class=\"error_msg\">(.*?)<\\/div>/m", "\033[31m\$1\033[0m\n", $s);
    $s = preg_replace("/<div class=\"ok_msg\">(.*?)<\\/div>/m", "\033[32m\$1\033[0m\n", $s);
    $s = preg_replace("/<div class=\"warning_msg\">(.*?)<\\/div>/m", "\033[33m\$1\033[0m\n", $s);
    $s = preg_replace("/&gt;/", ">", $s);
    $s = preg_replace("/&lt;/", "<", $s);
    echo $s;
}

function echo2_success($success = true) {
    if ($success) {
        echo2(" <span class=\"ok_msg\">OK</span><br/>");
    } else {
        echo2(" <span class=\"error_msg\">FAIL</span><br/>");
    }
}

/**
 * Error üzenet kiírása.
 *
 * @param string $s a szöveg
 * @param bool $nl legyen-e enter a végén
 */
function echo2_error($s, $nl = false)
{
    if (!$s) {
        echo2("<b>[</b><span class=\"error_msg\">FAIL</span><b>]</b>");
    } else {
        echo2('<span class="error_msg">' . $s . '</span>');
    }
    if ($nl) echo2_br();
}

/**
 * Warning üzenet kiírása
 *
 * @param string $s a szöveg
 * @param bool $nl legyen-e enter a végén
 */
function echo2_warning($s, $nl = false)
{
    echo2('<span class="warning_msg">' . $s . '</span>');
    if ($nl) {
        echo2_br();
    }
}

/**
 * Oké üzenet kiírása
 *
 * @param string $s a szöveg
 * @param bool $nl legyen-e enter a végén
 */
function echo2_ok($s, $nl = false)
{
    if (!$s) {
        echo2("<b>[</b><span class=\"ok_msg\">OK</span><b>]</b>");
    } else {
        echo2('<span class="ok_msg">' . $s . '</span>');
    }
    if ($nl) echo2_br();
}

/**
 * Újsor kiírása
 */
function echo2_br()
{
    echo2('<br/>');
}

/**
 * CLI futtatásnál stdin ről user input olvasása
 *
 * @return string
 */
function cli_stdin_read()
{
  if (PHP_SAPI != 'cli') return;
  $fp = fopen("php://stdin", "r");//dev/stdin nél nem működik a pipe
  $input = fgets($fp, 255);
  fclose($fp);
  return $input;
}

function spinner()
{
    global $g_spinner_state;
    switch ($g_spinner_state) {
        case 0:$s = ".\x08";break;
        case 1:$s = "o\x08";break;
        case 2:$s = "O\x08";break;
        case 3:$s = "@\x08";break;
    }

    $g_spinner_state = ($g_spinner_state + 1) % 4;
    return $s;
}

function spinner2()
{
    global $g_spinner_state2;
    switch ($g_spinner_state2) {
        case 0:$s = "/\x08";break;
        case 1:$s = "-\x08";break;
        case 2:$s = "\\\x08";break;
        case 3:$s = "|\x08";break;
    }

    $g_spinner_state2 = ($g_spinner_state2 + 1) % 4;
    return $s;
}
