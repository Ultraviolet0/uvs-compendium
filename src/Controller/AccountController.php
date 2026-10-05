<?php

declare(strict_types=1);

namespace Uvs\Controller;

use Uvs\Database;
use Uvs\Guides\GuideStatus;
use Uvs\Http\Response;

final class AccountController extends Controller
{
    public function dashboard(): Response
    {
        $user = $this->authorize('account.manage');
        $guides = $this->app->guides()->listForAuthor((int) $user['id']);
        $groups = ['attention' => [], 'drafts' => [], 'review' => [], 'published' => [], 'closed' => []];
        foreach ($guides as $guide) {
            $groups[self::group($guide)][] = $guide;
        }
        $characters = (int) $this->app->db()->value('SELECT COUNT(*) FROM user_characters WHERE user_id = :id', ['id' => (int) $user['id']]);
        return $this->render('account/dashboard', [
            'user' => $user,
            'groups' => $groups,
            'guideCount' => count($guides),
            'characterCount' => $characters,
            'mfaEnabled' => $user['mfa_enabled_at'] !== null,
            'submissionsOpen' => $this->app->settings()->bool('guide_submissions_enabled'),
        ], ['title' => 'Your dashboard', 'current' => 'account']);
    }

    /**
     * @param array<string, mixed> $guide
     */
    public static function group(array $guide): string
    {
        $review = $guide['review_status'];
        if ($review === 'needs_changes') {
            return 'attention';
        }
        if ($guide['visibility'] !== 'private') {
            return 'published';
        }
        return match ($review) {
            'draft' => 'drafts',
            'in_review', 'approved' => 'review',
            default => 'closed',
        };
    }

    public function theme(): Response
    {
        $user = $this->authorize('account.manage');
        $theme = $this->request->input('theme');
        if (!in_array($theme, ['dark', 'light'], true)) {
            return Response::json(['error' => 'Unknown theme.'], 422);
        }
        $this->app->db()->execute('UPDATE users SET theme = :theme, updated_at = :now WHERE id = :id',
            ['theme' => $theme, 'now' => Database::now(), 'id' => (int) $user['id']]);
        if ($this->request->wantsJson()) {
            return Response::json(['theme' => $theme]);
        }
        return $this->redirect('account/');
    }

    /**
     * @param array<string, mixed> $guide
     * @return array{label: string, tone: string, detail: string}
     */
    public static function status(array $guide): array
    {
        return GuideStatus::describe($guide);
    }
}
