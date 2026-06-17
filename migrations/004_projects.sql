-- Case Studies: projects table
-- Run in phpMyAdmin on the production database.

CREATE TABLE IF NOT EXISTS projects (
  id           INT AUTO_INCREMENT PRIMARY KEY,
  title        VARCHAR(255)  NOT NULL,
  slug         VARCHAR(255)  NOT NULL UNIQUE,
  excerpt      TEXT,
  cover_image  VARCHAR(500),
  client       VARCHAR(100),
  role         VARCHAR(100)  DEFAULT 'UI/UX Designer',
  year         YEAR,
  tools        VARCHAR(255),

  s1_body      TEXT,
  s2_body      TEXT,
  s3_body      TEXT,
  s4_body      TEXT,
  s5_body      TEXT,

  s1_images    JSON,
  s2_images    JSON,
  s3_images    JSON,
  s4_images    JSON,
  s5_images    JSON,

  is_published TINYINT(1)    NOT NULL DEFAULT 0,
  sort_order   INT           NOT NULL DEFAULT 0,
  created_at   DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
