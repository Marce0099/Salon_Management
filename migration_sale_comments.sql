-- Invoice comments: append-only chat-style notes attached to a sale.
-- Idempotent -- safe to run more than once.
--   mysql -u root coco_cosmetics2 < migration_sale_comments.sql

CREATE TABLE IF NOT EXISTS `sale_comments` (
  `id_sale_comment` int(11) NOT NULL AUTO_INCREMENT,
  `id_sale` int(10) UNSIGNED NOT NULL,
  `id_admin` int(11) DEFAULT NULL,
  `comment` text NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id_sale_comment`),
  KEY `idx_sale_comments_sale` (`id_sale`,`created_at`),
  CONSTRAINT `fk_sale_comments_sale` FOREIGN KEY (`id_sale`) REFERENCES `sales` (`id_sale`) ON DELETE CASCADE,
  CONSTRAINT `fk_sale_comments_admin` FOREIGN KEY (`id_admin`) REFERENCES `admins` (`id_admin`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
