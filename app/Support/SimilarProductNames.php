<?php

namespace App\Support;

use App\Models\Product;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Finds the products whose name is the same as, or a likely misspelling of, a name being typed.
 */
class SimilarProductNames
{
    /** Names shorter than this (without spaces) are only compared for equality, to avoid noise. */
    private const MIN_LENGTH_FOR_TYPOS = 4;

    /** Minimum similar_text percentage to flag a name as a probable misspelling. */
    private const MIN_PERCENT = 85.0;

    private const MAX_RESULTS = 5;

    /**
     * @return Collection<int, Product>
     */
    public static function find(string $name, ?int $ignoreId = null): Collection
    {
        $wanted = self::normalize($name);

        if ($wanted === '') {
            return new Collection;
        }

        return Product::query()
            ->with('category:id,name')
            ->when($ignoreId, fn ($query, int $id) => $query->whereKeyNot($id))
            ->get(['id', 'category_id', 'code', 'name', 'is_active'])
            ->map(fn (Product $product): array => ['product' => $product, 'score' => self::score($wanted, self::normalize($product->name))])
            ->filter(fn (array $row): bool => $row['score'] !== null)
            ->sortByDesc('score')
            ->take(self::MAX_RESULTS)
            ->pluck('product')
            ->values();
    }

    /**
     * Uppercase, no accents or punctuation, single spaces.
     */
    public static function normalize(string $name): string
    {
        $clean = preg_replace('/[^A-Z0-9]+/', ' ', strtoupper(Str::ascii($name)));

        return trim((string) $clean);
    }

    /**
     * Closeness (0-100) when the two normalized names look alike; null when they do not.
     */
    private static function score(string $wanted, string $other): ?float
    {
        if ($other === '') {
            return null;
        }

        $a = str_replace(' ', '', $wanted);
        $b = str_replace(' ', '', $other);

        if ($a === $b || self::sortedWords($wanted) === self::sortedWords($other)) {
            return 100.0;
        }

        if (min(strlen($a), strlen($b)) < self::MIN_LENGTH_FOR_TYPOS) {
            return null;
        }

        similar_text($a, $b, $percent);

        if (levenshtein($a, $b) <= max(1, intdiv(max(strlen($a), strlen($b)), 6)) || $percent >= self::MIN_PERCENT) {
            return $percent;
        }

        return null;
    }

    private static function sortedWords(string $name): string
    {
        $words = explode(' ', $name);
        sort($words);

        return implode(' ', $words);
    }
}
