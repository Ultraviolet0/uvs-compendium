<?php

declare(strict_types=1);

namespace Uvs\Controller;

use Uvs\Http\HttpException;
use Uvs\Http\Response;
use Uvs\Users\UsernamePolicy;

/**
 * Public member pages. Only active accounts are listed; emails, notes, and
 * security data are never selected for these pages.
 */
final class MemberController extends Controller
{
    private const PER_PAGE = 30;
    private const PUBLIC_COLUMNS = 'u.id, u.username, u.role, u.status, u.created_at, p.bio, p.preferred_game, p.website_url,
        p.discord_handle, m.public_id AS avatar_public_id, m.extension AS avatar_extension';

    public function directory(): Response
    {
        $db = $this->app->db();
        $query = mb_substr(trim($this->request->query('q')), 0, 24);
        $page = $this->request->queryInt('page');
        $where = "u.status = 'active'";
        $params = [];
        if ($query !== '') {
            $where .= ' AND u.username_key LIKE :q';
            $params['q'] = '%' . addcslashes(mb_strtolower($query), '%_\\') . '%';
        }
        $total = (int) $db->value("SELECT COUNT(*) FROM users u WHERE {$where}", $params);
        $members = $db->all(
            'SELECT ' . self::PUBLIC_COLUMNS . ",
                    (SELECT COUNT(*) FROM guides g WHERE g.author_id = u.id AND g.visibility = 'published' AND g.deleted_at IS NULL) AS guide_count
             FROM users u LEFT JOIN user_profiles p ON p.user_id = u.id LEFT JOIN media m ON m.id = p.avatar_media_id
             WHERE {$where} ORDER BY guide_count DESC, u.username_key ASC LIMIT :limit OFFSET :offset",
            $params + ['limit' => self::PER_PAGE, 'offset' => ($page - 1) * self::PER_PAGE],
        );
        return $this->render('members/directory', [
            'members' => $members, 'query' => $query, 'page' => $page,
            'pages' => max(1, (int) ceil($total / self::PER_PAGE)), 'total' => $total,
        ], [
            'title' => 'Members', 'current' => 'members', 'robots' => 'index',
            'description' => "Members of the UV's Compendium Diablo I and Hellfire community.",
        ]);
    }

    public function profile(string $username): Response
    {
        $db = $this->app->db();
        $member = $db->one(
            'SELECT ' . self::PUBLIC_COLUMNS . ' FROM users u LEFT JOIN user_profiles p ON p.user_id = u.id
             LEFT JOIN media m ON m.id = p.avatar_media_id WHERE u.username_key = :key AND u.status = \'active\'',
            ['key' => UsernamePolicy::normalize($username)],
        );
        if ($member === null) {
            throw HttpException::notFound();
        }
        if ($member['username'] !== $username) {
            return Response::redirect($this->request->url('members/' . rawurlencode((string) $member['username']) . '/'), 301);
        }
        $characters = $db->all(
            'SELECT name, class, game, play_mode, platform, level, notes FROM user_characters WHERE user_id = :id ORDER BY sort_order, id',
            ['id' => (int) $member['id']],
        );
        $guides = $this->app->guides()->listPublished(100, 0, (int) $member['id']);
        return $this->render('members/profile', [
            'member' => $member,
            'characters' => $characters,
            'guides' => $guides,
            'options' => CharacterController::options(),
            'games' => ProfileController::GAMES,
            'isSelf' => $this->user() !== null && (int) $this->user()['id'] === (int) $member['id'],
        ], [
            'title' => (string) $member['username'], 'current' => 'members', 'robots' => 'index',
            'description' => $member['username'] . "'s profile on UV's Compendium.",
        ]);
    }
}
