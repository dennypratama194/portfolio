-- Add excerpt field to ebook_chapters
-- Run in phpMyAdmin on digh8452_staging or digh8452_portfolio

ALTER TABLE `ebook_chapters`
  ADD COLUMN `excerpt` VARCHAR(400) DEFAULT NULL AFTER `title`;
