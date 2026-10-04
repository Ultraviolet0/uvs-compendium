<?php

declare(strict_types=1);

namespace Uvs\Tests\Integration;

use PDOException;
use Uvs\Console\Migrator;
use Uvs\Controller\CharacterController;
use Uvs\Guides\GuideWorkflow;

/**
 * Character profiles are descriptive (no game/class rules), and guides can
 * apply to DevilutionX alone or in combination. Covers migration 0002 on an
 * already-migrated development database as well as on a fresh one.
 */
final class ProfileAndApplicabilityTest extends DatabaseTestCase
{
    private const MIGRATION = '0002_profile_and_applicability_options';

    /**
     * @return array<string, string>
     */
    private function character(string $class, string $game): array
    {
        return ['name' => 'Hero', 'class' => $class, 'game' => $game, 'level' => '', 'play_mode' => '', 'platform' => '', 'notes' => ''];
    }

    /**
     * @param array<string, string> $input
     * @return array<string, string> validation errors (empty when accepted)
     */
    private static function errors(array $input): array
    {
        try {
            CharacterController::validate($input);
            return [];
        } catch (\Uvs\Support\ValidationException $error) {
            return $error->errors;
        }
    }

    public function testAnyListedClassIsAcceptedWithEitherGame(): void
    {
        foreach (array_keys(CharacterController::CLASSES) as $class) {
            foreach (array_keys(CharacterController::GAMES) as $game) {
                self::assertSame([], self::errors($this->character($class, $game)), "{$class} with {$game}");
            }
        }
        $clean = CharacterController::validate($this->character('bard', 'diablo'));
        self::assertSame(['bard', 'diablo'], [$clean['class'], $clean['game']]);
        self::assertSame([], self::errors($this->character('barbarian', 'diablo')));
        // Normal validation is unchanged: unknown classes, games, and levels are refused.
        self::assertArrayHasKey('class', self::errors($this->character('necromancer', 'diablo')));
        self::assertArrayHasKey('game', self::errors($this->character('bard', 'diablo2')));
        self::assertArrayHasKey('level', self::errors(['level' => '51'] + $this->character('barbarian', 'diablo')));
        self::assertArrayHasKey('name', self::errors(['name' => ''] + $this->character('bard', 'diablo')));
    }

    public function testTheDatabaseStoresDiabloBardAndBarbarian(): void
    {
        $user = $this->user('Descriptive');
        foreach ([['Lyra', 'bard'], ['Krag', 'barbarian'], ['Zealot', 'monk']] as $order => [$name, $class]) {
            $this->db->execute(
                "INSERT INTO user_characters (user_id, name, class, game, sort_order, created_at, updated_at)
                 VALUES (:user, :name, :class, 'diablo', :sort, UTC_TIMESTAMP(), UTC_TIMESTAMP())",
                ['user' => (int) $user['id'], 'name' => $name, 'class' => $class, 'sort' => $order],
            );
        }
        self::assertSame(3, (int) $this->db->value("SELECT COUNT(*) FROM user_characters WHERE game = 'diablo'"));
        // Column types still constrain the values themselves.
        $this->expectException(PDOException::class);
        $this->db->execute(
            "INSERT INTO user_characters (user_id, name, class, game, created_at, updated_at)
             VALUES (:user, 'Bad', 'necromancer', 'diablo', UTC_TIMESTAMP(), UTC_TIMESTAMP())",
            ['user' => (int) $user['id']],
        );
    }

    public function testSchemaAcceptsExactlyTheApplicabilityOptions(): void
    {
        foreach (['guides', 'guide_revisions'] as $table) {
            $type = (string) $this->db->value(
                "SELECT COLUMN_TYPE FROM information_schema.COLUMNS
                 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table AND COLUMN_NAME = 'applies_to'",
                ['table' => $table],
            );
            preg_match_all("/'([^']+)'/", $type, $matches);
            $stored = $matches[1];
            sort($stored);
            $offered = array_keys(GuideWorkflow::APPLIES_TO);
            sort($offered);
            self::assertSame($offered, $stored, "{$table}.applies_to matches the editor options");
        }
        self::assertSame([
            'diablo' => 'Diablo',
            'hellfire' => 'Hellfire',
            'devilutionx' => 'DevilutionX',
            'both' => 'Diablo and Hellfire',
            'diablo_devilutionx' => 'Diablo and DevilutionX',
            'hellfire_devilutionx' => 'Hellfire and DevilutionX',
            'diablo_hellfire_devilutionx' => 'Diablo, Hellfire, and DevilutionX',
        ], GuideWorkflow::APPLIES_TO, 'menu order and labels');
    }

    public function testEveryApplicabilityValueSurvivesTheWholeWorkflow(): void
    {
        $workflow = $this->application->guideWorkflow();
        $repo = $this->application->guides();
        $author = $this->user('Writer');
        $admin = $this->user('Boss', 'active', 'admin');
        $body = "## Notes\n\n" . str_repeat('DevilutionX keeps the original rules unless an option says otherwise. ', 4);
        foreach (array_keys(GuideWorkflow::APPLIES_TO) as $index => $value) {
            $content = $workflow->clean(['title' => "Applicability {$index}", 'summary' => 'Checks a stored applicability value.', 'body' => $body, 'applies_to' => 'diablo']);
            $id = $workflow->createDraft($author, $content, 50);
            // Edited (as autosave and the editor do) to the value under test.
            $guide = $workflow->saveDraft($repo->find($id), $workflow->clean(['applies_to' => $value] + $content), (int) $repo->find($id)['lock_version']);
            self::assertSame($value, $guide['applies_to']);
            $workflow->submit($author, $guide, (int) $guide['lock_version']);
            $guide = $repo->find($id);
            $workflow->adminAction($admin, $guide, 'approve_publish', '', '', (int) $guide['lock_version']);
            $guide = $repo->find($id);
            self::assertSame($value, $repo->revision($id, (int) $guide['published_revision_id'])['applies_to'], "{$value} revision");
            self::assertSame($value, $repo->findPublishedBySlug((string) $guide['slug'])['applies_to'], "{$value} published");
            // Administrator edits keep it too.
            $workflow->adminEdit($admin, $guide, $workflow->clean(['applies_to' => $value, 'title' => "Applicability {$index} edited"] + $content), (string) $guide['slug'], '', (int) $guide['lock_version']);
            self::assertSame($value, $repo->find($id)['applies_to']);
        }
        self::assertNull($workflow->clean(['applies_to' => 'devx'] + ['title' => 'x', 'summary' => '', 'body' => ''])['applies_to'], 'unknown values are dropped');
    }

    public function testMigrationUpgradesAnExistingDatabaseWithoutLosingData(): void
    {
        // Recreate the state of a development database that applied only 0001.
        $this->db->execute("DELETE FROM schema_migrations WHERE version = :version", ['version' => self::MIGRATION]);
        $old = "ENUM('diablo','hellfire','both') NULL";
        $this->db->execute("ALTER TABLE guides MODIFY applies_to {$old}");
        $this->db->execute("ALTER TABLE guide_revisions MODIFY applies_to {$old}");
        $this->db->execute("ALTER TABLE user_characters ADD CONSTRAINT user_characters_class_game CHECK (game = 'hellfire' OR class IN ('warrior','rogue','sorcerer'))");
        $migrator = new Migrator($this->db, $this->application->root . '/migrations');
        self::assertTrue($migrator->constraintExists('user_characters', 'user_characters_class_game'));

        $workflow = $this->application->guideWorkflow();
        $author = $this->user('Existing');
        $id = $workflow->createDraft($author, ['title' => 'Old Guide', 'summary' => '', 'body' => 'Text', 'applies_to' => 'both'], 5);
        $this->db->execute(
            "INSERT INTO user_characters (user_id, name, class, game, created_at, updated_at)
             VALUES (:user, 'Shade', 'monk', 'hellfire', UTC_TIMESTAMP(), UTC_TIMESTAMP())",
            ['user' => (int) $author['id']],
        );

        self::assertSame([self::MIGRATION], $migrator->migrate());
        self::assertFalse($migrator->constraintExists('user_characters', 'user_characters_class_game'));
        self::assertSame('both', $this->application->guides()->find($id)['applies_to'], 'stored values are preserved');
        self::assertSame('Diablo and Hellfire', GuideWorkflow::APPLIES_TO['both']);
        self::assertSame('monk', $this->db->value("SELECT class FROM user_characters WHERE name = 'Shade'"), 'existing characters load');
        self::assertSame([], $migrator->migrate(), 'nothing left to apply');
        foreach ($migrator->status() as $row) {
            self::assertSame('applied', $row['state'], $row['version']);
        }
        // Each step is idempotent, so an interrupted run can be repeated.
        foreach ((static fn (string $path): array => require $path)($migrator->available()[self::MIGRATION]) as $step) {
            is_callable($step) ? $step($migrator) : $this->db->execute($step);
        }
        self::assertSame('both', $this->application->guides()->find($id)['applies_to']);
    }
}
