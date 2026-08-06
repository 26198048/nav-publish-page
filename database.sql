CREATE TABLE IF NOT EXISTS `admin_users` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `username` VARCHAR(50) NOT NULL UNIQUE,
  `password_hash` VARCHAR(255) NOT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `site_settings` (
  `setting_key` VARCHAR(80) NOT NULL,
  `setting_value` TEXT NULL,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`setting_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `buttons` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `title` VARCHAR(80) NOT NULL,
  `description` VARCHAR(255) NULL,
  `category` VARCHAR(80) NOT NULL DEFAULT '默认分类',
  `icon` VARCHAR(255) NOT NULL DEFAULT '🔗',
  `action_type` ENUM('link','popup','copy') NOT NULL DEFAULT 'link',
  `link_url` VARCHAR(1000) NULL,
  `open_new_tab` TINYINT(1) NOT NULL DEFAULT 1,
  `popup_title` VARCHAR(120) NULL,
  `popup_content` TEXT NULL,
  `copy_content` TEXT NULL,
  `is_enabled` TINYINT(1) NOT NULL DEFAULT 1,
  `sort_order` INT NOT NULL DEFAULT 100,
  `click_count` BIGINT UNSIGNED NOT NULL DEFAULT 0,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_enabled_sort` (`is_enabled`, `sort_order`, `id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `click_logs` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `button_id` INT UNSIGNED NOT NULL,
  `action_type` ENUM('link','popup','copy') NOT NULL,
  `ip` VARCHAR(45) NULL,
  `user_agent` VARCHAR(255) NULL,
  `referer` VARCHAR(500) NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_button_time` (`button_id`, `created_at`),
  CONSTRAINT `fk_click_button` FOREIGN KEY (`button_id`) REFERENCES `buttons` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `login_attempts` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `username` VARCHAR(50) NULL,
  `ip` VARCHAR(45) NOT NULL,
  `user_agent` VARCHAR(255) NULL,
  `success` TINYINT(1) NOT NULL DEFAULT 0,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_ip_time` (`ip`, `created_at`),
  KEY `idx_username_time` (`username`, `created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `site_settings` (`setting_key`, `setting_value`) VALUES
('site_title','安全导航发布页'),
('page_title','发布页'),
('subtitle','请 Ctrl+D 收藏本页到浏览器收藏夹'),
('permanent_url','https://example.com/'),
('notice','所有入口均可在后台自由增删改。支持跳转链接、弹窗提示、点击复制三种交互方式。'),
('logo','assets/img/logo.png'),
('theme_color','#2FD18A'),
('desktop_background','preset:aurora_dark'),
('mobile_background','preset:tech_blue'),
('footer','© 2026 All Rights Reserved'),
('enable_search','1'),
('enable_categories','1')
ON DUPLICATE KEY UPDATE `setting_value` = VALUES(`setting_value`);

INSERT INTO `buttons` (`title`, `description`, `category`, `icon`, `action_type`, `link_url`, `open_new_tab`, `popup_title`, `popup_content`, `copy_content`, `is_enabled`, `sort_order`)
SELECT * FROM (
  SELECT 'iOS 入口' AS title, 'iOS 系统图标示例' AS description, '系统平台' AS category, 'assets/img/icon-ios.svg' AS icon, 'link' AS action_type, 'https://example.com/' AS link_url, 1 AS open_new_tab, NULL AS popup_title, NULL AS popup_content, NULL AS copy_content, 1 AS is_enabled, 10 AS sort_order
  UNION ALL SELECT '安卓入口','Android 系统图标示例','系统平台','assets/img/icon-android.svg','link','https://example.com/',1,NULL,NULL,NULL,1,20
  UNION ALL SELECT '公告弹窗','弹窗提示按钮示例','公告通知','💬','popup',NULL,1,'温馨提示','这是一个弹窗提示按钮，内容可在后台修改。',NULL,1,30
  UNION ALL SELECT '复制示例','点击复制指定内容','常用工具','📋','copy',NULL,1,NULL,NULL,'这里是可复制内容，可在后台修改。',1,40
  UNION ALL SELECT '备用入口','后台可继续添加更多按钮','常用入口','🔗','link','https://example.com/',1,NULL,NULL,NULL,1,50
) AS seed
WHERE NOT EXISTS (SELECT 1 FROM `buttons` LIMIT 1);
