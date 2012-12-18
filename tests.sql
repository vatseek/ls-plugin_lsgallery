ALTER TABLE `prefix_comment` MODIFY target_type enum('image','topic','talk') DEFAULT 'topic';

SHOW CREATE TABLE `prefix_comment`;