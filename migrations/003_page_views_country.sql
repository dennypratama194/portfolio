-- Add country code to page_views (captured from Cloudflare's CF-IPCountry header)
-- Run in phpMyAdmin on digh8452_staging or digh8452_portfolio

ALTER TABLE `page_views`
  ADD COLUMN `country` CHAR(2) DEFAULT NULL AFTER `ip_hash`,
  ADD INDEX `idx_country` (`country`);
