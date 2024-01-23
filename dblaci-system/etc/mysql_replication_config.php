<?php
die('állítsd be!');
$config = [
    'local_host' => "127.0.0.1",
    'local_port' => 3306,
    'local_user' => "root",
//SET    'local_pass' => "",

//SET    'local_host_remote_ip' => "192.168.10.84",
    'local_data_path' => '/mnt/mysql_hir6_2',
    'local_mysql_initscript' => '/etc/init.d/mysql_hir6_2',

    'remote_host' => "192.168.10.83",
    'remote_port' => 3306,
    'remote_user' => "root",
//SET    'remote_pass' => "",
    'remote_data_path' => "/mnt/mysql_hir6_1",//a snapshot könyvtár, alapból ez a lib/mysql
//OPTIONAL    'remote_data_path_real_relative' => '/mysql',//relatív, ha a snapshot könyvtár nem ez!
    'remote_slave_init' => false,//élesítse a remoteot slaveként

    'slave_user' => 'slave',
//SET    'slave_pass' => '',
    'slave_port' => 3306,
//OPTIONAL    'remote_ssh' => "root@",
    'live_sync' => false,
    'live_sync_pass' => 1,
];
