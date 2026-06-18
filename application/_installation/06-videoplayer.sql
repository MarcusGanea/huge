-- ═══════════════════════════════════════════════════
-- TABELLE 1: videos
-- Speichert alle hochgeladenen Videos
-- ═══════════════════════════════════════════════════
CREATE TABLE IF NOT EXISTS `huge`.`videos` (
  `video_id`      INT(11)      NOT NULL AUTO_INCREMENT,
  `user_id`       INT(11)      NOT NULL,
  `original_name` VARCHAR(255) NOT NULL,
  `stored_name`   VARCHAR(255) NOT NULL,
  `file_size`     BIGINT       NOT NULL,
  `shared`        TINYINT(1)   NOT NULL DEFAULT 0,
  `downloads`     INT(11)      NOT NULL DEFAULT 0,
  `uploaded_at`   TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`video_id`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_shared`  (`shared`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

-- ═══════════════════════════════════════════════════
-- TABELLE 2: video_comments
-- Kommentare unter Videos
-- ═══════════════════════════════════════════════════
CREATE TABLE IF NOT EXISTS `huge`.`video_comments` (
  `comment_id`  INT(11)   NOT NULL AUTO_INCREMENT,
  `video_id`    INT(11)   NOT NULL,
  `user_id`     INT(11)   NOT NULL,
  `comment_text` TEXT     NOT NULL,
  `created_at`  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`comment_id`),
  KEY `idx_video_id` (`video_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

-- ═══════════════════════════════════════════════════
-- TABELLE 3: video_likes
-- Likes für Videos (jeder User kann nur 1x liken)
-- ═══════════════════════════════════════════════════
CREATE TABLE IF NOT EXISTS `huge`.`video_likes` (
  `like_id`   INT(11)   NOT NULL AUTO_INCREMENT,
  `video_id`  INT(11)   NOT NULL,
  `user_id`   INT(11)   NOT NULL,
  `liked_at`  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`like_id`),
  UNIQUE KEY `unique_like` (`video_id`, `user_id`),  -- verhindert doppelte Likes!
  KEY `idx_video_id` (`video_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;