<?php

declare(strict_types=1);

namespace Uvs\Guides;

/**
 * Human-readable guide states.
 *
 * A guide has two independent dimensions:
 *  - review_status: where the author's working copy is in moderation
 *    (draft, in_review, needs_changes, approved, rejected);
 *  - visibility: whether an approved snapshot is public (private, published, hidden).
 *
 * The public page always renders the published revision snapshot, never the
 * working copy, so saving a draft can never change what readers see.
 */
final class GuideStatus
{
    public const REVIEW = ['draft', 'in_review', 'needs_changes', 'approved', 'rejected'];
    public const VISIBILITY = ['private', 'published', 'hidden'];

    /**
     * @param array<string, mixed> $guide
     * @return array{label: string, tone: string, detail: string}
     */
    public static function describe(array $guide): array
    {
        if (!empty($guide['deleted_at'])) {
            return ['label' => 'Deleted', 'tone' => 'danger', 'detail' => 'Removed by an administrator. It can be recovered until it is permanently deleted.'];
        }
        $review = (string) $guide['review_status'];
        $visibility = (string) $guide['visibility'];
        if ($visibility === 'published') {
            return match ($review) {
                'approved' => ['label' => 'Published', 'tone' => 'success', 'detail' => 'Live on the site.'],
                'draft' => ['label' => 'Published · editing', 'tone' => 'success', 'detail' => 'The published version stays live while you edit. Submit your changes for review to update it.'],
                'in_review' => ['label' => 'Published · update in review', 'tone' => 'info', 'detail' => 'The published version stays live while your update is reviewed.'],
                'needs_changes' => ['label' => 'Published · update needs changes', 'tone' => 'warning', 'detail' => 'An administrator asked for changes to your update. The published version is unchanged.'],
                'rejected' => ['label' => 'Published · update rejected', 'tone' => 'danger', 'detail' => 'Your proposed update was not accepted. The published version is unchanged.'],
                default => ['label' => 'Published', 'tone' => 'success', 'detail' => ''],
            };
        }
        if ($visibility === 'hidden') {
            return ['label' => 'Hidden', 'tone' => 'muted', 'detail' => 'Unpublished by an administrator. It is not visible to readers.'];
        }
        return match ($review) {
            'draft' => ['label' => 'Draft', 'tone' => 'muted', 'detail' => 'Private to you until you submit it for review.'],
            'in_review' => ['label' => 'In review', 'tone' => 'info', 'detail' => 'Waiting for an administrator. Withdraw it if you need to make changes.'],
            'needs_changes' => ['label' => 'Needs changes', 'tone' => 'warning', 'detail' => 'An administrator asked for changes. Revise it and submit again.'],
            'approved' => ['label' => 'Approved', 'tone' => 'success', 'detail' => 'Approved and waiting to be published.'],
            'rejected' => ['label' => 'Rejected', 'tone' => 'danger', 'detail' => 'This submission was not accepted.'],
            default => ['label' => ucfirst($review), 'tone' => 'muted', 'detail' => ''],
        };
    }

    /**
     * Whether the author may change the working copy right now.
     *
     * @param array<string, mixed> $guide
     */
    public static function authorCanEdit(array $guide): bool
    {
        if (!empty($guide['deleted_at'])) {
            return false;
        }
        $review = (string) $guide['review_status'];
        if (in_array($review, ['draft', 'needs_changes'], true)) {
            return true;
        }
        // Editing a live guide (or a hidden one) starts a new update draft; rejected updates may be revised.
        return $guide['visibility'] !== 'private' && in_array($review, ['approved', 'rejected'], true);
    }

    /**
     * @param array<string, mixed> $guide
     */
    public static function authorCanSubmit(array $guide): bool
    {
        return empty($guide['deleted_at']) && in_array($guide['review_status'], ['draft', 'needs_changes'], true);
    }

    /**
     * @param array<string, mixed> $guide
     */
    public static function authorCanWithdraw(array $guide): bool
    {
        return empty($guide['deleted_at']) && in_array($guide['review_status'], ['in_review', 'approved'], true)
            && !($guide['review_status'] === 'approved' && $guide['visibility'] === 'published');
    }

    /**
     * Authors may delete work that never reached readers.
     *
     * @param array<string, mixed> $guide
     */
    public static function authorCanDelete(array $guide): bool
    {
        return empty($guide['deleted_at']) && $guide['first_published_at'] === null
            && in_array($guide['review_status'], ['draft', 'needs_changes', 'rejected'], true);
    }
}
