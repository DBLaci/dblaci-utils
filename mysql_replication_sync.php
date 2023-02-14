#!/usr/bin/php
<?php
/*
(C) DBLaci 2011-10-14
MySQL fix adatbázisok szinkronizálása helybe, és slave indítása

v 1.1 2012-12-14 ssh is paraméter
v 1.2 2013-01-09 master beállítása is, + btrfs snapshot lvm helyett
v 2.0 2013-04-11 config file, további hibakezelések
v 3.0 2021-05-17 pdo mysql kapcsolat
*/
error_reporting(E_ALL & ~E_NOTICE);
require('/usr/share/dblaci-utils/utils_console.php');

require('config.php');

class Replication {
    public $config;

    public $remoteSudo = false;

    public function __construct($config)
    {
        $this->config = $config;
        if ($config['remote_sudo'] ?? false) {
            $this->remoteSudo = true;
        }
    }

    /**
     * a btrfs snapshot file rendszer útvonala a remoteon
     */
    public function getRemoteSnapshotPath(): string
    {
        return $this->config['remote_data_path'] . '/SYNC';
    }

    public function removeRemoteSnapshot(bool $soft = false)
    {
        global $config;
        echo2('REMOTE snapshot del ... ');
        $cmd = 'ssh ' . $this->getSshUserHost() . ' "' . ($this->remoteSudo ? 'sudo ' : '') . 'btrfs su de ' . escapeshellarg($this->getRemoteSnapshotPath()) . '" 2>&1';
        echo2($cmd . "\n");
        $ret = 0;
        passthru($cmd, $ret);
        if ($ret === 0) {
            echo2_success();
        } else {
            if ($soft) {
                echo2_ok('Nem létezett', true);
            } else {
                echo2_success(false);
            }
        }
    }
    public function getSshUserHost(): string
    {
        if (isset($this->config['remote_ssh'])) {
            return $this->config['remote_ssh'];
        }
        return 'root@' . $this->config['remote_host'];
    }
    public function isLocalSudo(): bool
    {
        return array_key_exists('localSudo', $this->config) && $this->config['localSudo'];
    }
}

$doit = in_array('doit', $argv);
$fail = false; // menet közben true lesz, akkor tudunk egykét dolgot lezárni, takarítani

foreach (['local_mysql_stop', 'local_mysql_start', 'local_data_path', 'slave_user', 'slave_pass', 'local_host_remote_ip'] as $_opt) {
    if (!$config[$_opt]) {
        echo2("Nincs " . $_opt . "!");
        echo2_success(false);
        die();
    }
}

$r = new Replication($config);

//var_export($r);die();

if (!is_dir($config['local_data_path'])) {
    echo2("local_data_path nem létezik!");
    echo2_success(false);
    die();
}
if ($config['live_sync'] && !in_array($config['live_sync_pass'], array(1, 2))) {
    echo2("live_sync nél live_sync_pass=(1|2) kötelező!");
    echo2_success(false);
    die();
}

//REMOTE adatok
echo2("<b>remote MySQL connect...</b>");
$_remote_host = $config['remote_host'];
if ($config['remote_port']) {
    $_remote_host .= ':' . $config['remote_port'];
} else {
    $config['remote_port'] = 3306;
}

try {
    $db_remote = new PDO('mysql:host=' . $_remote_host, $config['remote_user'], $config['remote_pass']);
    $db_remote->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $db_remote->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
#$conn_remote = mysql_connect($_remote_host, $config['remote_user'], $config['remote_pass']);
    echo2_success();
} catch (PDOException $e) {
    echo2("<br/>" . $e);
    echo2_success(false);
    die(1);
}

//REMOTE slave
echo2("<b>remote MySQL SLAVE állapot (ellenőrzés, nem hiba, ha nem megy)</b>");
$res = $db_remote->query("SHOW SLAVE STATUS");
$row = $res->fetch();
if ($row === false || $row['Slave_IO_Running'] != 'Yes' || $row['Slave_SQL_Running'] != 'Yes') {
    echo2_success(false);
//    die();//gáz, hagyjuk az egészet, még nem csináltunk kárt.
} else {
    echo2_success();
}

if (!$doit) {
    echo2("Nincs tovább preview módban!");
    echo2_success(false);
    die(1);
}

echo2("<b>local MySQL stop...</b>");
passthru($config['local_mysql_stop']);
echo2_success();

if ($config['remote_slave_init'] === true) {
    echo2("<b>remote STOP SLAVE</b>");
    try {
        $db_remote->query("STOP SLAVE");
        echo2_success();
    } catch (PDOException $e) {
        echo2(" STOP SLAVE: " . $e->getMessage());
        echo2_success(false);
        die(1);
    }

    if (!$fail) {
        echo2("<b>remote RESET SLAVE</b> (nem gond, FAIL)");
        try {
            $db_remote->query('RESET SLAVE');
            echo2_success(true);
        } catch (PDOException $e) {
            echo $e->getMessage();
            echo2_success(false);
        }
    }
}

echo2("<b>FLUSH TABLES WITH READ LOCK</b>");
$db_remote->query("FLUSH TABLES WITH READ LOCK");
echo2(", <b>FLUSH LOGS</b>");
$db_remote->query("FLUSH LOGS");
$result = $db_remote->query("SHOW MASTER STATUS");
if ($row = $result->fetch()) {
    $r_masterfile = $row['File'];
    $r_masterpos = $row['Position'];
    echo2_success();
} else {
    echo2("Nincs meg a SHOW MASTER STATUS.");
    echo2_success(false);
    $fail = true;
    die(1); // itt még ok leállni??
}

echo2("Master file: " . $r_masterfile . ", pos: " . $r_masterpos . "<br/>");
$sync_sleep = 5;
echo2("<b>Sleep (" . $sync_sleep . " secs) ...</b>");
sleep($sync_sleep);
echo2_success();

if (!$config['live_sync']) {
    $r->removeRemoteSnapshot(true); // nem biztos, hogy létezik.
    echo2("<b>remote btrfs snapshot</b><br/>");
    $remote_sync_path = $config['remote_data_path'] . "/SYNC";
    if ($config['remote_data_path_real_relative']) {
        $remote_sync_path .= $config['remote_data_path_real_relative'];
    }
    $cmd = 'ssh ' . $r->getSshUserHost() . ' "' . ($r->remoteSudo ? 'sudo ' : '') . 'sync && ' . ($r->remoteSudo ? 'sudo ' : '') . 'btrfs su sn ' . escapeshellarg($config['remote_data_path']) . " " . escapeshellarg($r->getRemoteSnapshotPath()) . '" 2>&1';
    echo $cmd . "\n";
    passthru($cmd, $ret);
    if ($ret != 0) {
        echo2_success(false);
        die();
    }
    echo2_success();//legalábbis reméljük
} else {
    $remote_sync_path = $config['remote_data_path'];
}

if (!$config['live_sync'] || $config['live_sync_pass'] != 2) {
    echo2("<b>UNLOCK...</b>");
    try {
        $db_remote->query('UNLOCK TABLES');
        echo2_success();
    } catch (PDOException $e) {
        $fail = true;
        echo2("<br/>Nem sikerült az UNLOCK!: " . $e->getMessage());
        echo2_success(false);
    }
}

if (!$fail) {
    echo2("<b>Rsync all included dir</b>");
    $ret = 0;
    passthru(($r->isLocalSudo() ? 'sudo ' : '') . 'rsync -aivce ssh --inplace --delete --progress ' . ($r->remoteSudo ? '--rsync-path="sudo rsync" ' : '') . $r->getSshUserHost() . ":" . $remote_sync_path . "/ " . $config['local_data_path'] . "/ --exclude my.cnf 2>&1", $ret);// --include-from /usr/local/sbin/mysql_slave_part.inc
    if ($ret === 0) {
        echo2_success();
    } else {
        die();
        echo2_error(true);
    }
}

// live_sync nél rsync után unlockolunk, ha pass=2
if ($config['live_sync'] && $config['live_sync_pass'] == 2) {
    echo2("<b>UNLOCK...</b>");
    try {
        $db_remote->query('UNLOCK TABLES');
        echo2_success();
    } catch (PDOException $e) {
        $fail = true;
        echo2("<br/>Nem sikerült az UNLOCK!: " . $e->getMessage());
        echo2_success(false);
    }
}

if (!$config['live_sync']) {
    //fail esetén is, ne maradjon meg!
    $r->removeRemoteSnapshot();
    if ($fail) die();//most lépünk ki, ha közben hiba volt. (szemetet nem hagyunk, ezért nem előbb)
}

echo2("<b>Local MySQL start...</b>");
passthru($config['local_mysql_start'], $ret);
if ($ret !== 0) {
    echo2_success(false);
    die(1);
}
echo2_success();
echo2("Sleep...");
sleep(2);
echo2_success();

echo2("<b>Local MySQL connect...</b>");
$_host = $config['local_host'];
if (!$config['local_port']) {
    $config['local_port'] = 3306; // default
}

$retries = 5;
while (--$retries >= 0) {
    try {
        $db_local = new PDO('mysql:host=' . $_host . ';port=' . $config['local_port'], $config['local_user'], $config['local_pass']);
        $db_local->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $db_local->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
#$conn_remote = mysql_connect($_remote_host, $config['remote_user'], $config['remote_pass']);
        echo2_success();
        break;
    } catch (PDOException $e) {
        echo "Local mysql connect failed: " . $e->getMessage() . ' ... retry ... ';
        sleep(15);
        if ($retries === 0) {
            echo2_success(false);
            die(1);
        }
    }
}

echo2("<b>local MySQL STOP SLAVE...</b>");
try {
    $db_local->query("STOP SLAVE");
    echo2_success();
} catch (PDOException $e) {
    echo "STOP SLAVE failed: " . $e->getMessage();
    echo2_success(false);
    die(1);
}

echo2("<b>local MySQL RESET SLAVE...</b>");
try {
    $db_local->query("RESET SLAVE");
    echo2_success();
} catch (PDOException $e) {
    echo "<br/>Connect failed: " . $e->getMessage();
    echo2_success(false);
    die(1);
}


echo2("<b>local MySQL CHANGE MASTER</b>");
$sql = "CHANGE MASTER TO MASTER_HOST = '" . $config['remote_host'] . "', MASTER_PORT = " . $config['remote_port'] . ", MASTER_USER = '" . $config['slave_user'] . "', MASTER_PASSWORD = '" . $config['slave_pass'] . "', MASTER_LOG_FILE = '" . $r_masterfile . "', MASTER_LOG_POS = " . $r_masterpos;
try {
    $db_local->query($sql);
    echo2_success();
} catch (PDOException $e) {
    echo2("<br/>" . $sql . " <span class=\"error_msg\">" . $e->getMessage() . "</span>");
    echo2_success(false);
    die(1);
}

echo2("<b>local MySQL START SLAVE...</b>");
$sql = "START SLAVE";
try {
    $db_local->query($sql);
    echo2_success();
} catch (PDOException $e) {
    echo2("<br/>" . $sql . " <span class=\"error_msg\">" . $e . "</span>");
    echo2_success(false);
    die(1);
}

echo2("<b>local MASTER positions...</b>");
$result = $db_local->query("SHOW MASTER STATUS");
if ($row = $result->fetch()) {
    $l_masterfile = $row['File'];
    $l_masterpos = $row['Position'];
    echo2_success();
} else {
    echo2("Nincs meg a SHOW MASTER STATUS.");
    echo2_success(false);
    $fail = true;
    die(1);
}

if ($config['remote_slave_init'] === true) {
    echo2("<b>remote CHANGE MASTER...</b>");
    $sql = "CHANGE MASTER TO MASTER_HOST = '" . $config['local_host_remote_ip'] . "', MASTER_PORT = " . $config['local_port'] . ", MASTER_USER = '" . $config['slave_user'] . "', MASTER_PASSWORD = '" . $config['slave_pass'] . "', MASTER_LOG_FILE = '" . $l_masterfile . "', MASTER_LOG_POS = " . $l_masterpos;
    try {
        $db_remote->query($sql);
        echo2_success();
    } catch (PDOException $e) {
        echo2("<br/>" . $sql . " <span class=\"error_msg\">" . $e->getMessage() . "</span>");
        echo2_success(false);
        die(1);
    }

    //remote slave indítása, live sync esetén csak pass 2 nél!
    if (!$config['live_sync'] || $config['live_sync_pass'] != 1) {
        echo2("<b>remote MySQL SLAVE START...</b>");
        $sql = "START SLAVE";
        try {
            $db_remote->query($sql);
            echo2_success();
        } catch (PDOException $e) {
            echo2("<br/>" . $sql . " <span class=\"error_msg\">" . $e->getMessage() . "</span>");
            echo2_success(false);
            die(1);
        }
    }
} else {
    echo2("<b>remote CHANGE MASTER/START SLAVE...</b> <span class=\"warning_msg\">[SKIP]</span><br/>");
}
