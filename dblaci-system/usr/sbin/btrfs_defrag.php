#!/usr/bin/php
<?php
/**
 * (C) DBLaci 2013-12-08
 *
 * töredezett fileok keresése és defragolása
 */

include('/usr/share/dblaci-utils/utils_console.php');
$doit = in_array('doit', $argv);

$path = $argv[1];

// config
$frag_threshold = 50;
$compress = in_array('nocompress', $argv) ? false : true;

echo2("Compress: " . ($compress ? "<span class=\"ok_msg\">Enabled</span>" : "Disabled") . " (to disable: nocompress)<br/>");

if (!is_dir($path)) {
    echo "First paraméter has to be a btrfs mounted directory\n";
}

$find_cmd = 'find ' . escapeshellarg($path) . ' -exec filefrag {} \; 2>&1';
ob_start();
passthru($find_cmd);
$res = ob_get_clean();
//echo $res;
$stat_frag = 0;
$stat_frag_total = 0;
$stat_fragmented = 0;
$stat_fragment_normalized = 0;
if (preg_match_all("/^(.*): (\\d+) extent(s?) found\$/m", $res, $matches, PREG_SET_ORDER)) {
//    print_r($matches);
    foreach ($matches as $m) {
        $frag_old = $frag_now = (int)$m[2];
        $stat_frag_total += $frag_old;
        if ($frag_old > $frag_threshold) {
            $stat_fragmented += 1;
            echo "Defrag '" . $m[1] . "' (" . $frag_old . "):\n";
            passthru("btrfs fi de -f" . ($compress ? ' -c' : '') . ' ' . escapeshellarg($m[1]));
            //nézzük mennyit javult
            ob_start();
            passthru("filefrag " . escapeshellarg($m[1]) . " 2>&1");
            $res0 = ob_get_clean();
            if (preg_match("/^(.*): (\\d+) extent(s?) found\$/m", $res0, $matches0)) {
                $frag_now = (int) $matches0[2];
                echo2("<span class=\"" . ($frag_old > $frag_now ? 'ok_msg' : 'warning_msg') . "\">Most: " . $frag_now . "</span><br/>");
                $stat_frag += $frag_old - $frag_now;
                if ($frag_now <= $frag_threshold) {
                    $stat_fragment_normalized++;
                }
            } else {
                echo2("<span class=\"error_msg\">Nem tudjuk mennyire töredezett defrag után? WTF?</span><br/>");
            }
        }


    }
}

echo2("A töredezés csökkent összesen: " . $stat_frag . "<br/>");
echo2("Az összes töredezés volt: " . $stat_frag_total . ", most: " . ($stat_frag_total - $stat_frag) . "<br/>");
echo2("Több, mint " . $frag_threshold . " töredezés: " . ($stat_fragmented - $stat_fragment_normalized) . ' volt: ' . $stat_fragmented . "<br/>");
