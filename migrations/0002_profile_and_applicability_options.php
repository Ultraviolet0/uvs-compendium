<?php

declare(strict_types=1);

/*
 * Character profiles are descriptive, not a rules check: drop the constraint
 * that tied Monk, Bard, and Barbarian to Hellfire (DevilutionX allows other
 * combinations, and members may describe whatever they play).
 *
 * Guides may also apply to DevilutionX alone or in combination. The existing
 * stored values ('diablo', 'hellfire', 'both') keep their meaning; new values
 * are appended to the ENUM, which leaves existing rows untouched.
 *
 * Every step is idempotent so an interrupted run can be repeated.
 */

use Uvs\Console\Migrator;

$appliesTo = "ENUM('diablo','hellfire','both','devilutionx','diablo_devilutionx','hellfire_devilutionx','diablo_hellfire_devilutionx') NULL";

return [
    static function (Migrator $migrator): void {
        if ($migrator->constraintExists('user_characters', 'user_characters_class_game')) {
            $migrator->db()->execute('ALTER TABLE user_characters DROP CONSTRAINT user_characters_class_game');
        }
    },
    "ALTER TABLE guides MODIFY applies_to {$appliesTo}",
    "ALTER TABLE guide_revisions MODIFY applies_to {$appliesTo}",
];
