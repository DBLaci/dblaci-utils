#!/usr/bin/php
<?php

// Gentoo rebuild every package before a date

class BuildList
{
    static $limit = 100;

    static $list = [];

    static function push($token)
    {
        if (static::$limit !== 0 && count(static::$list) >= static::$limit) {
            return;
        }
        static::$list[] = $token;
    }
}

$old = $argv[1];
if (!preg_match('/^\\d{4}-\\d{2}-\\d{2}( \\d{2}:\\d{2}:\\d{2})?$/', $old)) {
    echo "Please add a date parameter in format '2016-05-05 12:22:22'\n";
    die(1);
}
if (isset($argv[2])) {
    BuildList::$limit = (int)$argv[2];
}

//$old = '2017-12-02 22:31';
$to_build = [];
$db = 0;

foreach (glob('/var/db/pkg/*/*') as $dir) {
    if (!file_exists($dir . '/BUILD_TIME')) {
        continue;
    }
    $buildtime = file_get_contents($dir . '/BUILD_TIME');
    $buildts = date('Y-m-d H:i:s', (int)$buildtime);
    if ($buildts >= $old) {
        continue;
    }
    $package = basename($dir);
    $category = basename(dirname($dir));
    echo $buildts . " ";

    $token = $category . '/' . $package;

    echo $token . "\n";
    $db++;

//    if (strpos($category, 'x11') === 0 || strpos($category, 'kde-') === 0) {
    BuildList::push('=' . $token);

//      $to_build[] = '='.$category.'/'.$package;
//    if (strpos($category, 'x11') === 0) {
//      $to_build[] = '='.$category.'/'.$package;
//    }
//    if (strpos($category, 'kde-') === 0) {
//      $to_build[] = '='.$category.'/'.$package;
//    }
//    if (strpos($category, 'plasma') !== false) {
//      $to_build[] = '='.$category.'/'.$package;
//    }

}

echo "Összes: " . $db . "\n";
echo "Buildelni: " . count(BuildList::$list) . "\n";
if (BuildList::$list) {
    $cmd = 'emerge -av1 --buildpkg --keep-going ' . implode(' ', BuildList::$list);
//    echo $cmd;
    file_put_contents('/tmp/rebuild.sh', $cmd);
    chmod('/tmp/rebuild.sh', 0777);
    echo "Start emerge ...";
    sleep(2);
    passthru($cmd);
}
