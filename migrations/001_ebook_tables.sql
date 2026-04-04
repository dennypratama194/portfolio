-- Run this in phpMyAdmin on digh8452_staging (staging) or digh8452_portfolio (production)

-- ─────────────────────────────────────────────────────────────
-- Table 1: ebook_products
-- ─────────────────────────────────────────────────────────────
CREATE TABLE `ebook_products` (
  `id`           INT            NOT NULL AUTO_INCREMENT,
  `slug`         VARCHAR(100)   NOT NULL,
  `title`        VARCHAR(255)   NOT NULL,
  `description`  TEXT           DEFAULT NULL,
  `tagline`      VARCHAR(255)   DEFAULT NULL,
  `price`        INT            NOT NULL,
  `cover_image`  VARCHAR(255)   DEFAULT NULL,
  `is_active`    TINYINT(1)     NOT NULL DEFAULT 0,
  `created_at`   DATETIME       NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`   DATETIME       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_ebook_products_slug` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─────────────────────────────────────────────────────────────
-- Table 2: ebook_chapters
-- ─────────────────────────────────────────────────────────────
CREATE TABLE `ebook_chapters` (
  `id`            INT          NOT NULL AUTO_INCREMENT,
  `product_id`    INT          NOT NULL,
  `title`         VARCHAR(255) NOT NULL,
  `slug`          VARCHAR(100) NOT NULL,
  `body`          LONGTEXT     DEFAULT NULL,
  `sort_order`    INT          NOT NULL DEFAULT 0,
  `is_published`  TINYINT(1)  NOT NULL DEFAULT 0,
  `created_at`    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_ebook_chapters_product_slug` (`product_id`, `slug`),
  CONSTRAINT `fk_chapters_product`
    FOREIGN KEY (`product_id`) REFERENCES `ebook_products` (`id`)
    ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─────────────────────────────────────────────────────────────
-- Table 3: ebook_purchases
-- ─────────────────────────────────────────────────────────────
CREATE TABLE `ebook_purchases` (
  `id`                  INT          NOT NULL AUTO_INCREMENT,
  `product_id`          INT          NOT NULL,
  `email`               VARCHAR(255) NOT NULL,
  `token`               VARCHAR(64)  NOT NULL,
  `xendit_invoice_id`   VARCHAR(255) DEFAULT NULL,
  `paid_at`             DATETIME     NOT NULL,
  `last_read_chapter`   INT          DEFAULT NULL,
  `created_at`          DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_ebook_purchases_token` (`token`),
  INDEX `idx_ebook_purchases_email`      (`email`),
  INDEX `idx_ebook_purchases_product_id` (`product_id`),
  CONSTRAINT `fk_purchases_product`
    FOREIGN KEY (`product_id`) REFERENCES `ebook_products` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
