CREATE TABLE IF NOT EXISTS remembered_logins (
    selector CHAR(32) CHARACTER SET ascii COLLATE ascii_bin NOT NULL PRIMARY KEY,
    token_hash CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    user_id BIGINT UNSIGNED NOT NULL,
    password_fingerprint CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    expires_at BIGINT UNSIGNED NOT NULL,
    KEY idx_remembered_user (user_id),
    KEY idx_remembered_expiry (expires_at),
    CONSTRAINT fk_remembered_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
