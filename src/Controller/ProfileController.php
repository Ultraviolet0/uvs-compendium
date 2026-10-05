<?php

declare(strict_types=1);

namespace Uvs\Controller;

use Uvs\Database;
use Uvs\Http\Response;
use Uvs\Support\Text;
use Uvs\Support\ValidationException;

final class ProfileController extends Controller
{
    public const GAMES = ['diablo' => 'Diablo', 'hellfire' => 'Hellfire', 'both' => 'Diablo and Hellfire'];

    public function edit(): Response
    {
        $user = $this->authorize('account.manage');
        return $this->page($user);
    }

    /**
     * @param array<string, mixed> $user
     * @param array<string, string> $errors
     * @param array<string, string> $old
     */
    private function page(array $user, array $errors = [], array $old = [], int $status = 200): Response
    {
        $media = $this->app->media();
        return $this->render('account/profile', [
            'user' => $user,
            'errors' => $errors,
            'old' => $old + [
                'bio' => (string) ($user['bio'] ?? ''),
                'preferred_game' => (string) ($user['preferred_game'] ?? ''),
                'website_url' => (string) ($user['website_url'] ?? ''),
                'discord_handle' => (string) ($user['discord_handle'] ?? ''),
            ],
            'games' => self::GAMES,
            'limits' => $media->limits(),
            'usage' => $media->usage((int) $user['id']),
        ], ['title' => 'Your profile', 'current' => 'account'], $status);
    }

    public function update(): Response
    {
        $user = $this->authorize('account.manage');
        $input = [
            'bio' => Text::block($this->request->input('bio'), 1000),
            'preferred_game' => $this->request->input('preferred_game'),
            'website_url' => Text::line($this->request->input('website_url'), 200),
            'discord_handle' => Text::line($this->request->input('discord_handle'), 40),
        ];
        try {
            $clean = self::validate($input);
        } catch (ValidationException $error) {
            return $this->page($user, $error->errors, $input, 422);
        }
        $this->app->db()->execute(
            'UPDATE user_profiles SET bio = :bio, preferred_game = :game, website_url = :website, discord_handle = :discord, updated_at = :now
             WHERE user_id = :id',
            $clean + ['now' => Database::now(), 'id' => (int) $user['id']],
        );
        $this->flash('success', 'Profile saved.');
        return $this->redirect('account/profile/');
    }

    /**
     * @param array<string, string> $input
     * @return array{bio: ?string, game: ?string, website: ?string, discord: ?string}
     */
    public static function validate(array $input): array
    {
        $errors = [];
        $bio = trim($input['bio']);
        if (mb_strlen($bio) > 1000) {
            $errors['bio'] = 'Keep your bio under 1,000 characters.';
        }
        $game = $input['preferred_game'] === '' ? null : $input['preferred_game'];
        if ($game !== null && !isset(self::GAMES[$game])) {
            $errors['preferred_game'] = 'Choose one of the listed games.';
        }
        $website = $input['website_url'];
        if ($website !== '') {
            $parts = parse_url($website);
            if (mb_strlen($website) > 200 || $parts === false || !in_array(strtolower($parts['scheme'] ?? ''), ['http', 'https'], true)
                || empty($parts['host']) || isset($parts['user']) || filter_var($website, FILTER_VALIDATE_URL) === false) {
                $errors['website_url'] = 'Enter a full web address starting with https:// (or leave it blank).';
            }
        }
        $discord = $input['discord_handle'];
        if ($discord !== '' && !preg_match('/^[A-Za-z0-9_.]{2,32}(?:#\d{4})?$/', $discord)) {
            $errors['discord_handle'] = 'Discord names use 2–32 letters, numbers, underscores, or periods.';
        }
        if ($errors !== []) {
            throw new ValidationException($errors);
        }
        return [
            'bio' => $bio === '' ? null : $bio,
            'game' => $game,
            'website' => $website === '' ? null : $website,
            'discord' => $discord === '' ? null : $discord,
        ];
    }

    public function uploadAvatar(): Response
    {
        $user = $this->authorize('account.manage');
        if (!$this->app->rateLimiter()->hit('media.upload', (string) $user['id'])) {
            throw $this->tooManyRequests('Too many uploads. Please wait a while before trying again.');
        }
        $media = $this->app->media();
        try {
            $media->storeAvatar($user, $media->uploadedPath($this->request->file('avatar')));
        } catch (ValidationException $error) {
            return $this->page($user, ['avatar' => $error->first()], [], 422);
        }
        $this->flash('success', 'Avatar updated.');
        return $this->redirect('account/profile/');
    }

    public function deleteAvatar(): Response
    {
        $user = $this->authorize('account.manage');
        $this->app->media()->removeAvatar((int) $user['id']);
        $this->flash('success', 'Avatar removed.');
        return $this->redirect('account/profile/');
    }
}
