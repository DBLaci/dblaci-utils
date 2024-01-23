#!/usr/bin/php
<?php
/**
 * (C) DBLaci 2013
 */
require('/usr/share/dblaci-utils/utils_console.php');
$all = false;

$target = $argv[1];
$balance = 25;
foreach ($argv as $k => $arg) {
    if ($k === 0) {
        continue;
    }
    if (count($argv) === 2) {
        break;// ez target az biztos.
    }
    if (preg_match("/^\\d{2}\$/", $arg)) {
        $balance = (int)$arg;
        echo2("Balance: " . $balance . "<br/>");
    } else {
        $target = $arg; // ha nem szám, akkor target... :)
    }
}

if ($target === 'all') {
    ob_start();
    passthru('mount -t btrfs');
    $list0 = ob_get_clean();
    $m = preg_match_all("/(^.*? on (.*?) .*\$)+/m", $list0, $matches);
    foreach ($matches[2] as $mount) {
        $list[] = $mount;
    }
} elseif ($target) {
    $list = array($target);
} else {
    echo2("<span class=\"error_msg\">Kötelező paraméter: mit? Opctionális paraméter: hány %os tele blokkokat balance (default 25)</span><br/>");
    die();
}
if (is_array($list)) foreach ($list as $mount) {
    echo $mount . " defrag, optimalizálás!\n";
    $cmd = 'btrfs fi de -v -c ' . escapeshellarg($mount);
    passthru($cmd);
    $cmd = 'btrfs fi ba start -dusage=' . $balance . ' -musage=' . $balance . ' ' . escapeshellarg($mount);
    passthru($cmd);
}
