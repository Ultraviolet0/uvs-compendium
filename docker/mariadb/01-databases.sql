-- Local development and test databases only. These credentials are deliberately
-- public, placeholder values that must never be used outside Docker on localhost.
CREATE DATABASE IF NOT EXISTS uvs_dev CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE DATABASE IF NOT EXISTS uvs_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

CREATE USER IF NOT EXISTS 'uvs_dev'@'%' IDENTIFIED BY 'local-dev-only';
CREATE USER IF NOT EXISTS 'uvs_test'@'%' IDENTIFIED BY 'local-test-only';

GRANT ALL PRIVILEGES ON uvs_dev.* TO 'uvs_dev'@'%';
GRANT ALL PRIVILEGES ON uvs_test.* TO 'uvs_test'@'%';
FLUSH PRIVILEGES;
