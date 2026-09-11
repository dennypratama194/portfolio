-- Showcase only. Run after 004_projects.sql; safe to rerun on this schema.
CREATE TABLE IF NOT EXISTS showcase_categories (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(100) NOT NULL,
  slug VARCHAR(100) NOT NULL UNIQUE,
  sort_order INT NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS showcase_projects (
  id INT AUTO_INCREMENT PRIMARY KEY,
  title VARCHAR(255) NOT NULL,
  slug VARCHAR(180) NOT NULL UNIQUE,
  short_description TEXT,
  category_id INT DEFAULT NULL,
  thumbnail JSON DEFAULT NULL,
  thumbnail_alt VARCHAR(500) NOT NULL DEFAULT '',
  year SMALLINT UNSIGNED DEFAULT NULL,
  tools VARCHAR(255) NOT NULL DEFAULT '',
  client VARCHAR(100) NOT NULL DEFAULT '',
  related_case_study_id INT DEFAULT NULL,
  is_featured TINYINT(1) NOT NULL DEFAULT 0,
  is_published TINYINT(1) NOT NULL DEFAULT 0,
  sort_order INT NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_showcase_order (is_published, sort_order, id),
  INDEX idx_showcase_category (category_id, is_published, sort_order, id),
  INDEX idx_showcase_featured (is_featured, is_published, sort_order, id),
  CONSTRAINT fk_showcase_category FOREIGN KEY (category_id) REFERENCES showcase_categories(id) ON DELETE SET NULL,
  CONSTRAINT fk_showcase_case_study FOREIGN KEY (related_case_study_id) REFERENCES projects(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS showcase_images (
  id INT AUTO_INCREMENT PRIMARY KEY,
  showcase_id INT NOT NULL,
  media JSON NOT NULL,
  alt_text VARCHAR(500) NOT NULL DEFAULT '',
  caption VARCHAR(1000) NOT NULL DEFAULT '',
  sort_order INT NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_showcase_images (showcase_id, sort_order, id),
  CONSTRAINT fk_showcase_images FOREIGN KEY (showcase_id) REFERENCES showcase_projects(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Rollback (DESTRUCTIVE to Showcase data only): export these three tables and
-- back up admin/uploads/showcase first. Then drop showcase_images,
-- showcase_projects, showcase_categories, in that order. Never drop projects.
