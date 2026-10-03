<?php

declare(strict_types=1);

namespace Uvs\Controller;

use Uvs\Database;
use Uvs\Http\HttpException;
use Uvs\Http\Response;
use Uvs\Support\Text;
use Uvs\Support\ValidationException;

final class CharacterController extends Controller
{
    public const CLASSES = [
        'warrior' => 'Warrior', 'rogue' => 'Rogue', 'sorcerer' => 'Sorcerer',
        'monk' => 'Monk', 'bard' => 'Bard', 'barbarian' => 'Barbarian',
    ];
    public const DIABLO_CLASSES = ['warrior', 'rogue', 'sorcerer'];
    public const GAMES = ['diablo' => 'Diablo', 'hellfire' => 'Hellfire'];
    public const MODES = ['single' => 'Single player', 'multi' => 'Multiplayer'];
    public const PLATFORMS = ['devilutionx' => 'DevilutionX', 'original' => 'Original release', 'other' => 'Other'];
    public const MAX_CHARACTERS = 30;

    public function index(): Response
    {
        $user = $this->authorize('account.manage');
        return $this->page($user);
    }

    /**
     * @param array<string, mixed> $user
     * @param array<string, string> $errors
     * @param array<string, string> $old
     */
    private function page(array $user, array $errors = [], array $old = [], int $status = 200, ?array $editing = null): Response
    {
        return $this->render('account/characters', [
            'user' => $user,
            'characters' => $this->characters((int) $user['id']),
            'errors' => $errors,
            'old' => $old,
            'editing' => $editing,
            'options' => self::options(),
            'max' => self::MAX_CHARACTERS,
        ], ['title' => $editing === null ? 'Your characters' : 'Edit character', 'current' => 'account'], $status);
    }

    /**
     * @return array<string, array<string, string>>
     */
    public static function options(): array
    {
        return ['classes' => self::CLASSES, 'games' => self::GAMES, 'modes' => self::MODES, 'platforms' => self::PLATFORMS];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function characters(int $userId): array
    {
        return $this->app->db()->all('SELECT * FROM user_characters WHERE user_id = :id ORDER BY sort_order, id', ['id' => $userId]);
    }

    /**
     * @return array<string, mixed>
     */
    private function owned(array $user, string $id): array
    {
        $row = $this->app->db()->one('SELECT * FROM user_characters WHERE id = :id AND user_id = :user', ['id' => (int) $id, 'user' => (int) $user['id']]);
        if ($row === null) {
            throw HttpException::notFound();
        }
        return $row;
    }

    /**
     * @return array<string, string>
     */
    private function input(): array
    {
        return [
            'name' => Text::line($this->request->input('name'), 15),
            'class' => $this->request->input('class'),
            'game' => $this->request->input('game'),
            'play_mode' => $this->request->input('play_mode'),
            'platform' => $this->request->input('platform'),
            'level' => trim($this->request->input('level')),
            'notes' => Text::line($this->request->input('notes'), 280),
        ];
    }

    /**
     * @param array<string, string> $input
     * @return array<string, mixed>
     */
    public static function validate(array $input): array
    {
        $errors = [];
        if ($input['name'] === '' || !preg_match('/^[\p{L}\p{N} _\'-]{1,15}$/u', $input['name'])) {
            $errors['name'] = 'Character names are 1–15 letters, numbers, spaces, hyphens, or apostrophes.';
        }
        if (!isset(self::GAMES[$input['game']])) {
            $errors['game'] = 'Choose Diablo or Hellfire.';
        }
        if (!isset(self::CLASSES[$input['class']])) {
            $errors['class'] = 'Choose a class.';
        } elseif ($input['game'] === 'diablo' && !in_array($input['class'], self::DIABLO_CLASSES, true)) {
            $errors['class'] = 'Monk, Bard, and Barbarian are Hellfire classes.';
        }
        if ($input['play_mode'] !== '' && !isset(self::MODES[$input['play_mode']])) {
            $errors['play_mode'] = 'Choose single player or multiplayer.';
        }
        if ($input['platform'] !== '' && !isset(self::PLATFORMS[$input['platform']])) {
            $errors['platform'] = 'Choose one of the listed versions.';
        }
        $level = null;
        if ($input['level'] !== '') {
            $level = filter_var($input['level'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 50]]);
            if ($level === false) {
                $errors['level'] = 'Level must be a whole number from 1 to 50.';
            }
        }
        if (mb_strlen($input['notes']) > 280) {
            $errors['notes'] = 'Keep notes under 280 characters.';
        }
        if ($errors !== []) {
            throw new ValidationException($errors);
        }
        return [
            'name' => $input['name'], 'class' => $input['class'], 'game' => $input['game'],
            'mode' => $input['play_mode'] === '' ? null : $input['play_mode'],
            'platform' => $input['platform'] === '' ? null : $input['platform'],
            'level' => $level, 'notes' => $input['notes'] === '' ? null : $input['notes'],
        ];
    }

    public function create(): Response
    {
        $user = $this->authorize('account.manage');
        $input = $this->input();
        $count = count($this->characters((int) $user['id']));
        if ($count >= self::MAX_CHARACTERS) {
            return $this->page($user, ['name' => 'You can list up to ' . self::MAX_CHARACTERS . ' characters.'], $input, 422);
        }
        try {
            $clean = self::validate($input);
        } catch (ValidationException $error) {
            return $this->page($user, $error->errors, $input, 422);
        }
        $order = (int) $this->app->db()->value('SELECT COALESCE(MAX(sort_order), 0) + 1 FROM user_characters WHERE user_id = :id', ['id' => (int) $user['id']]);
        $this->app->db()->execute(
            'INSERT INTO user_characters (user_id, name, class, game, play_mode, platform, level, notes, sort_order, created_at, updated_at)
             VALUES (:user, :name, :class, :game, :mode, :platform, :level, :notes, :sort, :now, :now)',
            $clean + ['user' => (int) $user['id'], 'sort' => $order, 'now' => Database::now()],
        );
        $this->flash('success', $clean['name'] . ' was added.');
        return $this->redirect('account/characters/');
    }

    public function edit(string $id): Response
    {
        $user = $this->authorize('account.manage');
        $character = $this->owned($user, $id);
        $old = array_map(static fn ($value) => $value === null ? '' : (string) $value, $character);
        return $this->page($user, [], $old, 200, $character);
    }

    public function update(string $id): Response
    {
        $user = $this->authorize('account.manage');
        $character = $this->owned($user, $id);
        $input = $this->input();
        try {
            $clean = self::validate($input);
        } catch (ValidationException $error) {
            return $this->page($user, $error->errors, $input, 422, $character);
        }
        $this->app->db()->execute(
            'UPDATE user_characters SET name = :name, class = :class, game = :game, play_mode = :mode, platform = :platform,
                    level = :level, notes = :notes, updated_at = :now WHERE id = :id AND user_id = :user',
            $clean + ['now' => Database::now(), 'id' => (int) $character['id'], 'user' => (int) $user['id']],
        );
        $this->flash('success', $clean['name'] . ' was updated.');
        return $this->redirect('account/characters/');
    }

    public function delete(string $id): Response
    {
        $user = $this->authorize('account.manage');
        $character = $this->owned($user, $id);
        $this->app->db()->execute('DELETE FROM user_characters WHERE id = :id AND user_id = :user',
            ['id' => (int) $character['id'], 'user' => (int) $user['id']]);
        $this->flash('success', $character['name'] . ' was removed.');
        return $this->redirect('account/characters/');
    }

    public function move(string $id): Response
    {
        $user = $this->authorize('account.manage');
        $character = $this->owned($user, $id);
        $characters = $this->characters((int) $user['id']);
        $ids = array_map(static fn ($row) => (int) $row['id'], $characters);
        $index = array_search((int) $character['id'], $ids, true);
        $target = $this->request->input('direction') === 'up' ? $index - 1 : $index + 1;
        if ($index !== false && $target >= 0 && $target < count($ids)) {
            [$ids[$index], $ids[$target]] = [$ids[$target], $ids[$index]];
            $this->app->db()->transaction(function () use ($ids, $user): void {
                foreach ($ids as $position => $characterId) {
                    $this->app->db()->execute('UPDATE user_characters SET sort_order = :sort WHERE id = :id AND user_id = :user',
                        ['sort' => $position + 1, 'id' => $characterId, 'user' => (int) $user['id']]);
                }
            });
        }
        return $this->redirect('account/characters/#character-' . (int) $character['id']);
    }
}
