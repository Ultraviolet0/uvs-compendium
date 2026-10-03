<?php

declare(strict_types=1);

namespace Uvs\Guides;

/**
 * Line-based diff for reviewing guide revisions. Common leading and trailing
 * lines are trimmed before an LCS table is built, and very large changes fall
 * back to a whole-block replacement to bound memory use.
 */
final class LineDiff
{
    private const MAX_CELLS = 1_500_000;

    /**
     * @return list<array{0: string, 1: string}> operations: [' '|'-'|'+', line]
     */
    public static function compare(string $old, string $new): array
    {
        $a = $old === '' ? [] : explode("\n", str_replace("\r\n", "\n", $old));
        $b = $new === '' ? [] : explode("\n", str_replace("\r\n", "\n", $new));
        $prefix = [];
        while ($a !== [] && $b !== [] && $a[0] === $b[0]) {
            $prefix[] = [' ', array_shift($a)];
            array_shift($b);
        }
        $suffix = [];
        while ($a !== [] && $b !== [] && end($a) === end($b)) {
            array_unshift($suffix, [' ', array_pop($a)]);
            array_pop($b);
        }
        $n = count($a);
        $m = count($b);
        if ($n * $m > self::MAX_CELLS) {
            $middle = array_merge(array_map(static fn ($line) => ['-', $line], $a), array_map(static fn ($line) => ['+', $line], $b));
            return array_merge($prefix, $middle, $suffix);
        }
        $lcs = array_fill(0, $n + 1, array_fill(0, $m + 1, 0));
        for ($i = $n - 1; $i >= 0; $i--) {
            for ($j = $m - 1; $j >= 0; $j--) {
                $lcs[$i][$j] = $a[$i] === $b[$j] ? $lcs[$i + 1][$j + 1] + 1 : max($lcs[$i + 1][$j], $lcs[$i][$j + 1]);
            }
        }
        $middle = [];
        $i = 0;
        $j = 0;
        while ($i < $n && $j < $m) {
            if ($a[$i] === $b[$j]) {
                $middle[] = [' ', $a[$i]];
                $i++;
                $j++;
            } elseif ($lcs[$i + 1][$j] >= $lcs[$i][$j + 1]) {
                $middle[] = ['-', $a[$i++]];
            } else {
                $middle[] = ['+', $b[$j++]];
            }
        }
        while ($i < $n) {
            $middle[] = ['-', $a[$i++]];
        }
        while ($j < $m) {
            $middle[] = ['+', $b[$j++]];
        }
        return array_merge($prefix, $middle, $suffix);
    }

    /**
     * Collapses long unchanged runs, keeping a few lines of context.
     *
     * @param list<array{0: string, 1: string}> $operations
     * @return list<array{0: string, 1: string}> with '…' marker rows for gaps
     */
    public static function withContext(array $operations, int $context = 3): array
    {
        $keep = [];
        foreach ($operations as $index => [$op]) {
            if ($op !== ' ') {
                for ($k = max(0, $index - $context); $k <= min(count($operations) - 1, $index + $context); $k++) {
                    $keep[$k] = true;
                }
            }
        }
        $result = [];
        $skipped = false;
        foreach ($operations as $index => $operation) {
            if (isset($keep[$index])) {
                $result[] = $operation;
                $skipped = false;
            } elseif (!$skipped) {
                $result[] = ['…', ''];
                $skipped = true;
            }
        }
        return $result;
    }
}
