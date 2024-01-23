<?php

// sample configuration for mysql_replication_sync.php

$config = [
    'local_host' => "127.0.0.1",
    'local_port' => 3306,
    'local_user' => "root",
//SET    'local_pass' => "",

//SET    'local_host_remote_ip' => "192.168.10.84",
    'local_data_path' => '/mnt/mysql-app1-2',
    'local_mysql_stop' => 'docker compose stop mysql',
    'local_mysql_start' => 'docker compose start mysql',

    'remote_host' => "192.168.10.83",
//OPTIONAL    'remote_ssh' => 'root@10.10.21.14', // default: root@remote_host
    'remote_sudo' => true, // if not root
    'remote_port' => 3306,
    'remote_user' => "root",
//SET    'remote_pass' => "",
    'remote_data_path' => "/mnt/mysql-app1-1",// snapshot directory
//OPTIONAL    'remote_data_path_real_relative' => '/mysql',//relatív, ha a snapshot könyvtár nem ez!
    'remote_slave_init' => true, // élesítse a remoteot slaveként

    'slave_user' => 'slave',
/*
    CREATE USER 'slave'@'%' IDENTIFIED BY 'slave_password';
    GRANT REPLICATION SLAVE ON *.* TO 'slave'@'%';
    FLUSH PRIVILEGES;
 */
//SET    'slave_pass' => '',
    'live_sync' => false,
    'live_sync_pass' => 1,
];
