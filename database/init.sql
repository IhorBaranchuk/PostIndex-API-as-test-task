CREATE TABLE IF NOT EXISTS post_indexes (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    post_code CHAR(5) NOT NULL,
    region VARCHAR(255) NULL,
    district VARCHAR(255) NULL,
    locality VARCHAR(255) NULL,
    address TEXT NULL,
    source ENUM('archive', 'api') NOT NULL DEFAULT 'archive',
    last_import_id VARCHAR(64) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_post_code (post_code),
    INDEX idx_address (address(255)),
    INDEX idx_source (source),
    INDEX idx_last_import_id (last_import_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;