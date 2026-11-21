CREATE TABLE IF NOT EXISTS telegram_student_drafts (
 chat_id VARCHAR(32) NOT NULL,
 user_id VARCHAR(32) NOT NULL,
 nonce CHAR(16) NOT NULL,
 step VARCHAR(24) NOT NULL,
 data_json TEXT NOT NULL,
 prompt_id BIGINT NOT NULL DEFAULT 0,
 expires_at BIGINT NOT NULL,
 PRIMARY KEY (chat_id, user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
