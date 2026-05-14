#!/usr/bin/php
<?php
/**
 * (C) DBLaci 2009-08-24
 * v2 2010-05-21: szebb mysqldump parameterezes + dump lehetőség
 *    ez a script minden nap lefut. a felevnel regebbi menteseket szetpakoljuk:
 *    minden honap elso mentese az ev mappaba (2009 pl) a tobbit _del mappaba.
 * v3 2012-03-28: extended insert kiszedve, nem sokkal kisebb filet csinált, viszont nem lehetett benne jol keresni.
 * v4 2012-07-10: verzió kezelés, és csomagkezelés. Mostmár a gites az etalon
 * v5 2013-05-02: parancssorból megadható config útvonal
 * v6 2013-06-17: debug, üres adatbázis hibának tűnt
 * v7 2014-08-11: databases option nélkül nincs benne a USE a dumpban, ami jó, ha más néven kell restorozni.
 * v8 2021-04-13: PDO használata, php 7.4, utils_console upgrade
 * v9 2026-05-14: config validálás (isset $backupbase, $user, $pass), --db= paraméter támogatás
 *                  egyedi db backup retenció nélkül, nap/óra backup ütemezéshez
 */

define('DEBUG', 0);
require('/usr/share/dblaci-utils/utils_console.php');

// alapértékek:
$delregi = true;
$kivetel = [];
$napig = 7;
$host = '127.0.0.1';
$archiv = true; // Controls retention/archival: true = old backups organized into yearly/monthly folders, old files moved to _del
$show_vanished = true;
$single_db = null; // --db= paraméterrel megadott egyedi adatbázis
$dump_to_dump_dir = false; // --dump flag: dump all backups to _dump folder instead of normal structure

/**
 * Required configuration variables (see etc/mysql_admin_config.php):
 *
 * $backupbase  (string) Base directory for backup storage, e.g. "/mnt/bak/mysql"
 * $user        (string) MySQL username for authentication
 * $pass        (string) MySQL password for authentication
 * $napig       (int)    Keep daily backups for this many days (default: 7)
 * $kivetel     (array)  List of database names to exclude from backup
 * $show_vanished (bool) Show databases that no longer exist (default: true)
 */
$config_fn = '/etc/mysql_admin_config.php';

foreach ($argv as $v) {
    if (preg_match("/^--config=(.*)\$/", $v, $tomb)) {
        $config_fn = $tomb[1];
    }
    if (preg_match("/^--db=(.*)\$/", $v, $tomb)) {
        $single_db = $tomb[1];
    }
    if (preg_match("/^--help\$/", $v)) {
        echo2("--help         this help<br/>");
        echo2("--config=/path/to/config<br/>");
        echo2("--db=name      backup only the specified database (archival limited to this db)<br/>");
        echo2("--dump         _dump mappába ment mindent.<br/>");
        echo2("--cron         csöndes kimenet, csak hibát ír ki<br/>");
        die();
    }

}

global $silent;
$silent = false;
if (in_array('--dump', $argv)) {
    $dump_to_dump_dir = true;
    $archiv = false;
}
if (in_array('--cron', $argv)) $silent = true;


require($config_fn);

// Validate required config variables
if (!isset($backupbase) || $backupbase === '') {
    echo2("<span class=\"error_msg\">ERROR: Config variable \$backupbase is not set</span>\n");
    die();
}
if (!isset($user) || $user === '') {
    echo2("<span class=\"error_msg\">ERROR: Config variable \$user is not set</span>\n");
    die();
}
if (!isset($pass) || $pass === '') {
    echo2("<span class=\"error_msg\">ERROR: Config variable \$pass is not set</span>\n");
    die();
}

// When --db= is used, disable retention for other databases
// The single database backup will still be archived normally

$path_del = $backupbase . "/_del/";

if (!function_exists('glob_recursive')) {
    // Does not support flag GLOB_BRACE
    function glob_recursive($pattern, $flags = 0)
    {
        $files = glob($pattern, $flags);
        foreach (glob(dirname($pattern) . '/*', GLOB_ONLYDIR | GLOB_NOSORT) as $dir) {
            $files = array_merge($files, glob_recursive($dir . '/' . basename($pattern), $flags));
        }
        return $files;
    }
}

function unlink2($file)
{
    global $delregi;
    global $path_del;
    global $silent;
    global $backupbase;

    if (!$delregi) {
        @mkdir($backupbase . "/_del");
    }

    if (!$silent) echo2($file . " <span class=\"warning_msg\">DEL</span><br/>");
    if ($delregi) {
        unlink($file);
    } else {
        rename($file, $path_del . basename($file));
    }
}

$date_format = 'Ymd_His';
$most = date($date_format);
$regi = date($date_format, time() - $napig * 24 * 3600);
//echo $d;
//http://vitobotta.com/smarter-faster-backups-restores-mysql-databases-with-mysqldump/#sthash.pa6t8vY1.dpbs
//http://forums.mysql.com/read.php?28,357782,358064#msg-358064
$parts = explode(':', $host);
$host0 = $parts[0];
$port = $parts[1] ?? 3306; // Ha nincs 1-es index, 3306-ot használ

$pars = '--create-options --skip-extended-insert --quick --add-locks --no-autocommit --single-transaction --host ' . $host0 . " --user $user --password=$pass";
$pars .= ' --port ' . $port;

$pdo = new PDO('mysql:host=' . $host, $user, $pass, [PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// Determine database list: single DB from --db= or all databases
if ($single_db !== null) {
    // Validate that the specified database exists
    $stmt = $pdo->prepare("SHOW DATABASES LIKE ?");
    $stmt->execute([$single_db]);
    $found = $stmt->fetch();
    if (!$found) {
        echo2("<span class=\"error_msg\">ERROR: Database '$single_db' does not exist</span>\n");
        die();
    }
    $db = [$single_db];
    echo2("<span class=\"ok_msg\">Single database mode: {$single_db}</span>\n");
} else {
    $sql = "SHOW DATABASES";
    $res = $pdo->query($sql);
    $db = [];
    while ($row = $res->fetch()) {
        if (in_array($row['Database'], $kivetel)) {
            continue;
        }
        $db[] = $row['Database'];
        //print_r($row);
    }
    sort($db);
}

//print_r($db);

//megkeressuk a mentes konyvtarban levo konyvtarakat.

//print_r($konyvtar);

if (!file_exists($backupbase)) {
    mkdir($backupbase);
}

//regiek atrendezese
if ($archiv) {
    $konyvtar = glob($backupbase . "/*");
    foreach ($konyvtar as $m) {
        $m = basename($m);
        if (in_array($m, array('_del', '_dump'))) {
            continue; // valószínű speciális
        }
        // In single_db mode, only archive the specified database
        if ($single_db !== null && $m !== $single_db) {
            continue; // skip other databases in single_db mode
        }
        if ($show_vanished && !in_array($m, $db)) {
            //kiírjuk, mert ez kb. hiba.
            echo2_warning($m . " nincs az adatbazisban.", true);
        }

        $files = glob($backupbase . '/' . $m . '/*');
        foreach ($files as $file) {
            $file0 = basename($file);
            if (is_dir($file)) {
                continue;
            }
//	echo $file0."\n";
            $tomb = [];
            if (preg_match("/^" . preg_quote($m, "/") . "_(\\d{4})(\\d{2})(\\d{2})_(\\d{6})\\.sql\\.gz$/", $file0, $tomb) === 1) {
                $date = $tomb[1] . $tomb[2] . $tomb[3] . '_' . $tomb[4];
//	    echo $mdate."\n";
                if ($date >= $regi) {
                    continue;//tul uj, marad
                }
                //ha nem uj, akkor vagy a _del be rakjuk, vagy az ev konyvtarba.
//	    print_r($tomb);
                //van mar ilyen datum az ev konyvtarban?
                $honap_path = $backupbase . "/" . $m . "/" . $tomb[1];
                $evho_list = glob($honap_path . "/" . $m . "_" . $tomb[1] . $tomb[2] . "*");
                //havonta egyet rakunk csak el, úgyhogy ezeket kkukázzuk.
                foreach ($evho_list as $evho_file) {
                    unlink2($evho_file);
                }

                if (!is_dir($honap_path)) {
                    @mkdir($honap_path);
                }
                if (!$silent) echo $file . " > " . $tomb[1] . "\n";
                rename($file, $honap_path . "/" . $file0);
            } else {
                continue; // ezekkel nem nagyon tudjuk mi legyen.
            }
        }
    }
}

//vegul az aktualis backup

if (!$silent) {
    echo2("Táblák módosítási ideje ... ");
}
$res2 = $pdo->query("SELECT MAX(UPDATE_TIME) AS `update_time`, TABLE_SCHEMA AS `table_schema` FROM `information_schema`.`TABLES` GROUP BY TABLE_SCHEMA");
while ($row = $res2->fetch()) {
    //üres adatbázisról nem készül dump, mert a dátuma üres lesz. (ez nem baj, elvileg)
    $db_updated[$row['table_schema']] = $row['update_time'];
}
if (!$silent) {
    echo2_ok(null, true);
}

foreach ($db as $m) {
    $update_file = $backupbase . "/" . $m . "/.update";
    if (in_array($m, array('information_schema'))) continue;
    if (!$silent) echo2("<b>" . $m . "</b> ... ");

    $newest_dump = '0000-00-00';

    //megkeressük a legfrissebb dumpot belőle
    foreach (glob_recursive($backupbase . "/" . $m . "/*") as $db_file) {
        if (!preg_match("/^" . preg_quote($m, "/") . "_(\\d{4})(\\d{2})(\\d{2})_(\\d{2})(\\d{2})(\\d{2})\\.sql(\\.gz)?$/", basename($db_file), $tomb)) continue;
        $datetime = $tomb[1] . "-" . $tomb[2] . "-" . $tomb[3] . " " . $tomb[4] . ":" . $tomb[5] . ":" . $tomb[6];
        if ($datetime > $newest_dump) $newest_dump = $datetime;
    }

    @mkdir($backupbase . "/" . $m);//ha nincs
    if (DEBUG) echo $newest_dump . " <> " . $db_updated[$m] . "\n";
    if (isset($db_updated[$m]) && $newest_dump > $db_updated[$m]) {
        if (!$silent) echo2("<span class=\"ok_msg\">Nem változott</span><br/>");
        touch($update_file);
        continue;
    }

    $dir = "$backupbase/$m/$m" . "_" . $most;
    if ($dump_to_dump_dir) {
        @mkdir($backupbase . "/_dump");
        $dir = "$backupbase/_dump/$m";
    }
    if (!$silent) {
        echo2("<br/>");
        $pv = "pv | ";
    } else {
        ob_start();
        $pv = '';
    }
    $dump_fn = $dir . '.sql.gz';

    $cmd = "mysqldump \"$m\" $pars | " . $pv . "gzip > " . escapeshellarg($dump_fn);
    //echo $cmd."\n";//DBG
    passthru($cmd . " 2>&1", $ret);

    if ($silent) {
        $output = ob_get_clean();
    }
//bashben lehetne ilyet:    if [ ${PIPESTATUS[0]} -ne "0" ];
//    echo $ret;
//    exit;
    if (!file_exists($dump_fn) || filesize($dump_fn) === 20) {
        @unlink($dump_fn);
        if ($silent) echo2("<b>" . $m . "</b> ");
        echo2("<span class=\"error_msg\">FAIL</span><br/>");
        echo2($output);
    } else {
        if (!$silent) {
            echo2("<span class=\"ok_msg\">OK</span><br/>");
        }
        touch($update_file);
    }
}
