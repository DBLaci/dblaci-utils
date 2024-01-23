<?php
//(C) DBLaci 2012-12-10
/**
  * mentés rsynccel + btrfs snapshot
  *
  * $p(array):
  *   src: forrás (rsync vagy könyvtár (teljes))
  *   dst: cél könyvtár
  *   cron: ha true, akkor nincs progress (autodetect)
  *   limit: sávszéllimit kbyte/sec
  *   password_file: nem kötelező, jelszó file
  *   password: maga a jelszó
  *   rsync_other: egyéb rsync kapcsolók (opcionális)
  */
include('/usr/share/dblaci-utils/utils_console.php');//kiíratáshoz
function backup($p) {
    global $argv;

    $p_default = array(
        'old' => 14 * 24 * 3600,//két hetes (másodpercben) (ennél régebbi szabvány nevű snapshotokat töröljük)
        'cron' => (is_array($argv) && in_array('cron', $argv))?true:false,
    );
    $p = array_merge($p_default, $p);

    $MIT = $p['src'];
    $HOVA = $p['dst'];
    $logfile = '/var/log/backup/'.basename($HOVA).'_'.date('Ymd-His').'.log';

    if ($p['old']) {
        $keep_from = date('Ymd_His', time() - $p['old']);
        $list = glob($HOVA.'_*');
        foreach ($list as $dir) {
            if (!is_dir($dir)) continue;
            if (preg_match("/^".preg_quote($HOVA, '/')."_\\d{8}_\\d{6}\$/", $dir)) {
                if ($dir < $HOVA.'_'.$keep_from) {
                    if ($p['cron']) {
                        ob_start();
                    }
                    echo2($dir." <span class=\"warning_msg\">DEL</span><br/>");
                    passthru('/usr/sbin/bakdel.sh '.escapeshellarg($dir));
                    if ($p['cron']) {
                        ob_end_clean();
                    }
                }
            }
        }
    }

    $pref = "";
    if (array_key_exists('password_file', $p)) {
        $pref .= " --password-file ".$p['password_file'];
    }
    
    if (array_key_exists('cron', $p)) {
    } else {
        $pref .= " --progress";
    }

    if (array_key_exists('limit', $p)) {
        $pref .= " --bwlimit $LIMIT ".$p['limit'];
    }

    $rsyncOther = array_key_exists('rsync_other', $p) ? $p['rsync_other'] : '';
    $cmd = "ionice -c 3 rsync -avi $MIT $HOVA --numeric-ids --delete --delete-excluded --stats --inplace" . $pref . $rsyncOther;

    if (array_key_exists('password', $p)) {
        $cmd = "RSYNC_PASSWORD=\"".$p['password']."\" ".$cmd;
    }

    $cmd .= " 2>&1 | tee ".escapeshellarg($logfile);

    if ($p['cron']) {
        ob_start();
    }
    passthru($cmd, $ret);
    if ($p['cron']) {
        ob_end_clean();//már kiírtuk a tee miatt
    }
    if (!in_array($ret, array(
        0,//OK
        24,//vanished
    ))) $fail = true;
    if ($fail) {
        $error = "rsync hibakód: ".$ret."\n";
    }
    if ($p['cron']) {
        if (!is_writable(dirname($logfile))) {
            $error .= ' '.$logfile.' nem írható';
            echo $error;
            $fail = true;
        } else {
            file_put_contents($logfile, $error, FILE_APPEND);
        }
    } else {
        echo $error;
    }
    $cmd = "/sbin/btrfs subvolume snapshot $HOVA $HOVA".'_'.date('Ymd_Hms');
    if ($p['cron']) {
        ob_start();
    }
    passthru($cmd);
    if ($p['cron']) {
        $res = ob_get_clean();
    }
}
