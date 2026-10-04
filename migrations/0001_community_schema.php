<?php

declare(strict_types=1);

/*
 * Initial community schema. Statements are idempotent so an interrupted run can
 * be repeated safely: MySQL/MariaDB DDL commits implicitly and cannot be rolled back.
 */

use Uvs\Console\Migrator;

$table = 'ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci';

return [
    "CREATE TABLE IF NOT EXISTS users (
        id INT UNSIGNED NOT NULL AUTO_INCREMENT,
        username VARCHAR(24) NOT NULL,
        username_key VARCHAR(24) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
        email VARCHAR(254) NOT NULL,
        email_key VARCHAR(254) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL,
        password_hash VARCHAR(255) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
        role ENUM('member','moderator','admin') NOT NULL DEFAULT 'member',
        status ENUM('pending','active','rejected','suspended') NOT NULL DEFAULT 'pending',
        status_reason VARCHAR(500) NULL,
        admin_note TEXT NULL,
        status_changed_at DATETIME NULL,
        status_changed_by INT UNSIGNED NULL,
        auth_epoch INT UNSIGNED NOT NULL DEFAULT 1,
        theme ENUM('dark','light') NULL,
        mfa_secret VARCHAR(255) CHARACTER SET ascii COLLATE ascii_bin NULL,
        mfa_enabled_at DATETIME NULL,
        mfa_last_step BIGINT UNSIGNED NULL,
        last_login_at DATETIME NULL,
        created_at DATETIME NOT NULL,
        updated_at DATETIME NOT NULL,
        PRIMARY KEY (id),
        UNIQUE KEY users_username_key_unique (username_key),
        UNIQUE KEY users_email_key_unique (email_key),
        KEY users_status_created (status, created_at),
        KEY users_role (role),
        CONSTRAINT users_status_changed_by_fk FOREIGN KEY (status_changed_by) REFERENCES users (id) ON DELETE SET NULL,
        CONSTRAINT users_username_key_matches CHECK (username_key = LOWER(username)),
        CONSTRAINT users_username_format CHECK (username_key REGEXP '^[a-z0-9][a-z0-9_-]{2,23}$')
    ) {$table}",

    "CREATE TABLE IF NOT EXISTS guides (
        id INT UNSIGNED NOT NULL AUTO_INCREMENT,
        author_id INT UNSIGNED NULL,
        slug VARCHAR(80) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
        title VARCHAR(140) NOT NULL,
        summary VARCHAR(400) NOT NULL DEFAULT '',
        body MEDIUMTEXT NOT NULL,
        applies_to ENUM('diablo','hellfire','both') NULL,
        review_status ENUM('draft','in_review','needs_changes','approved','rejected') NOT NULL DEFAULT 'draft',
        visibility ENUM('private','published','hidden') NOT NULL DEFAULT 'private',
        lock_version INT UNSIGNED NOT NULL DEFAULT 1,
        published_revision_id INT UNSIGNED NULL,
        moderation_note TEXT NULL,
        submitted_at DATETIME NULL,
        reviewed_at DATETIME NULL,
        reviewed_by INT UNSIGNED NULL,
        first_published_at DATETIME NULL,
        published_at DATETIME NULL,
        deleted_at DATETIME NULL,
        deleted_by INT UNSIGNED NULL,
        created_at DATETIME NOT NULL,
        updated_at DATETIME NOT NULL,
        PRIMARY KEY (id),
        UNIQUE KEY guides_slug_unique (slug),
        KEY guides_public (visibility, deleted_at, published_at),
        KEY guides_queue (review_status, submitted_at),
        KEY guides_author (author_id, updated_at),
        CONSTRAINT guides_author_fk FOREIGN KEY (author_id) REFERENCES users (id) ON DELETE SET NULL,
        CONSTRAINT guides_reviewed_by_fk FOREIGN KEY (reviewed_by) REFERENCES users (id) ON DELETE SET NULL,
        CONSTRAINT guides_deleted_by_fk FOREIGN KEY (deleted_by) REFERENCES users (id) ON DELETE SET NULL,
        CONSTRAINT guides_slug_format CHECK (slug REGEXP '^[a-z0-9]+(-[a-z0-9]+)*$')
    ) {$table}",

    "CREATE TABLE IF NOT EXISTS guide_revisions (
        id INT UNSIGNED NOT NULL AUTO_INCREMENT,
        guide_id INT UNSIGNED NOT NULL,
        revision_number INT UNSIGNED NOT NULL,
        kind ENUM('submission','resubmission','admin_edit','publication','restore') NOT NULL,
        title VARCHAR(140) NOT NULL,
        summary VARCHAR(400) NOT NULL DEFAULT '',
        body MEDIUMTEXT NOT NULL,
        applies_to ENUM('diablo','hellfire','both') NULL,
        content_hash CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
        created_by INT UNSIGNED NULL,
        note VARCHAR(500) NULL,
        created_at DATETIME NOT NULL,
        PRIMARY KEY (id),
        UNIQUE KEY guide_revisions_number_unique (guide_id, revision_number),
        CONSTRAINT guide_revisions_guide_fk FOREIGN KEY (guide_id) REFERENCES guides (id) ON DELETE CASCADE,
        CONSTRAINT guide_revisions_created_by_fk FOREIGN KEY (created_by) REFERENCES users (id) ON DELETE SET NULL
    ) {$table}",

    static function (Migrator $migrator): void {
        if (!$migrator->constraintExists('guides', 'guides_published_revision_fk')) {
            $migrator->db()->execute('ALTER TABLE guides ADD CONSTRAINT guides_published_revision_fk
                FOREIGN KEY (published_revision_id) REFERENCES guide_revisions (id) ON DELETE SET NULL');
        }
    },

    "CREATE TABLE IF NOT EXISTS media (
        id INT UNSIGNED NOT NULL AUTO_INCREMENT,
        public_id CHAR(22) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
        owner_id INT UNSIGNED NOT NULL,
        purpose ENUM('avatar','guide') NOT NULL,
        guide_id INT UNSIGNED NULL,
        mime_type VARCHAR(32) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
        extension VARCHAR(5) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
        width SMALLINT UNSIGNED NOT NULL,
        height SMALLINT UNSIGNED NOT NULL,
        byte_size INT UNSIGNED NOT NULL,
        sha256 CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
        alt_text VARCHAR(200) NULL,
        created_at DATETIME NOT NULL,
        PRIMARY KEY (id),
        UNIQUE KEY media_public_id_unique (public_id),
        KEY media_owner (owner_id, purpose),
        KEY media_guide (guide_id),
        KEY media_created (created_at),
        CONSTRAINT media_owner_fk FOREIGN KEY (owner_id) REFERENCES users (id) ON DELETE CASCADE,
        CONSTRAINT media_guide_fk FOREIGN KEY (guide_id) REFERENCES guides (id) ON DELETE SET NULL,
        CONSTRAINT media_public_id_format CHECK (public_id REGEXP '^[A-Za-z0-9_-]{22}$')
    ) {$table}",

    "CREATE TABLE IF NOT EXISTS user_profiles (
        user_id INT UNSIGNED NOT NULL,
        bio VARCHAR(1000) NULL,
        preferred_game ENUM('diablo','hellfire','both') NULL,
        website_url VARCHAR(200) NULL,
        discord_handle VARCHAR(40) NULL,
        avatar_media_id INT UNSIGNED NULL,
        updated_at DATETIME NOT NULL,
        PRIMARY KEY (user_id),
        CONSTRAINT user_profiles_user_fk FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE,
        CONSTRAINT user_profiles_avatar_fk FOREIGN KEY (avatar_media_id) REFERENCES media (id) ON DELETE SET NULL
    ) {$table}",

    "CREATE TABLE IF NOT EXISTS user_characters (
        id INT UNSIGNED NOT NULL AUTO_INCREMENT,
        user_id INT UNSIGNED NOT NULL,
        name VARCHAR(15) NOT NULL,
        class ENUM('warrior','rogue','sorcerer','monk','bard','barbarian') NOT NULL,
        game ENUM('diablo','hellfire') NOT NULL,
        play_mode ENUM('single','multi') NULL,
        platform ENUM('devilutionx','original','other') NULL,
        level TINYINT UNSIGNED NULL,
        notes VARCHAR(280) NULL,
        sort_order SMALLINT UNSIGNED NOT NULL DEFAULT 0,
        created_at DATETIME NOT NULL,
        updated_at DATETIME NOT NULL,
        PRIMARY KEY (id),
        KEY user_characters_order (user_id, sort_order),
        CONSTRAINT user_characters_user_fk FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE,
        CONSTRAINT user_characters_level CHECK (level IS NULL OR level BETWEEN 1 AND 50),
        CONSTRAINT user_characters_class_game CHECK (game = 'hellfire' OR class IN ('warrior','rogue','sorcerer'))
    ) {$table}",

    "CREATE TABLE IF NOT EXISTS account_tokens (
        id INT UNSIGNED NOT NULL AUTO_INCREMENT,
        user_id INT UNSIGNED NOT NULL,
        purpose ENUM('password_reset','email_verification') NOT NULL,
        token_hash CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
        created_at DATETIME NOT NULL,
        expires_at DATETIME NOT NULL,
        used_at DATETIME NULL,
        PRIMARY KEY (id),
        UNIQUE KEY account_tokens_hash_unique (token_hash),
        KEY account_tokens_user (user_id, purpose),
        KEY account_tokens_expiry (expires_at),
        CONSTRAINT account_tokens_user_fk FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
    ) {$table}",

    "CREATE TABLE IF NOT EXISTS mfa_recovery_codes (
        id INT UNSIGNED NOT NULL AUTO_INCREMENT,
        user_id INT UNSIGNED NOT NULL,
        code_hash CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
        used_at DATETIME NULL,
        created_at DATETIME NOT NULL,
        PRIMARY KEY (id),
        UNIQUE KEY mfa_recovery_codes_unique (user_id, code_hash),
        CONSTRAINT mfa_recovery_codes_user_fk FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
    ) {$table}",

    "CREATE TABLE IF NOT EXISTS settings (
        name VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
        value VARCHAR(1000) NOT NULL,
        updated_at DATETIME NOT NULL,
        updated_by INT UNSIGNED NULL,
        PRIMARY KEY (name),
        CONSTRAINT settings_updated_by_fk FOREIGN KEY (updated_by) REFERENCES users (id) ON DELETE SET NULL
    ) {$table}",

    "CREATE TABLE IF NOT EXISTS audit_events (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        actor_id INT UNSIGNED NULL,
        actor_label VARCHAR(40) NOT NULL,
        action VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
        target_type ENUM('user','guide','setting','media','system') NOT NULL,
        target_id INT UNSIGNED NULL,
        target_label VARCHAR(160) NULL,
        metadata TEXT NULL,
        created_at DATETIME NOT NULL,
        PRIMARY KEY (id),
        KEY audit_events_created (created_at),
        KEY audit_events_action (action, created_at),
        KEY audit_events_target (target_type, target_id),
        CONSTRAINT audit_events_actor_fk FOREIGN KEY (actor_id) REFERENCES users (id) ON DELETE SET NULL
    ) {$table}",

    "CREATE TABLE IF NOT EXISTS rate_limits (
        bucket CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
        window_start INT UNSIGNED NOT NULL,
        hits INT UNSIGNED NOT NULL,
        expires_at DATETIME NOT NULL,
        PRIMARY KEY (bucket),
        KEY rate_limits_expiry (expires_at)
    ) {$table}",
];
