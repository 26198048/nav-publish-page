<?php
/**
 * 数据库配置文件。
 * 如果面板不支持修改文件权限，请用文件管理里的“铅笔编辑”手动填写数据库信息。
 * 填好后访问域名，会进入安装页设置站点信息和后台账号密码。
 */
return [
    'db' => [
        'host' => 'localhost',
        'port' => 3306,
        'database' => '',
        'username' => '',
        'password' => '',
        'charset' => 'utf8mb4',
    ],
    'setup_key' => 'MANUAL_CONFIG_MODE',
    'session_name' => 'NAV_ADMIN_SESSID',
    'force_https_cookie' => false,
    'login_max_failures' => 5,
    'login_window_minutes' => 15,
    'login_lock_minutes' => 15,
];
