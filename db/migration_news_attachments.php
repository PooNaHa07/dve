<?php
require_once __DIR__ . '/../includes/functions.php';

$sql = "CREATE TABLE IF NOT EXISTS `news_attachments` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `news_id` int(11) NOT NULL,
    `file_name` varchar(255) NOT NULL,
    `original_name` varchar(255) NOT NULL,
    `file_type` varchar(50) NOT NULL COMMENT 'image, video, document',
    `created_at` datetime DEFAULT current_timestamp(),
    PRIMARY KEY (`id`),
    KEY `idx_news_id` (`news_id`),
    CONSTRAINT `fk_news_id` FOREIGN KEY (`news_id`) REFERENCES `news` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";

if ($conn->query($sql) === TRUE) {
    echo "Table 'news_attachments' created successfully.\n";
} else {
    echo "Error creating table: " . $conn->error . "\n";
}
