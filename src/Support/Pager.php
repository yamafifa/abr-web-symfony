<?php

namespace App\Support;

/**
 * ページャーに出すページ番号
 */
class Pager
{
    /**
     * 今のページの前後 2 つと、最初と最後。間が空くところには null（「…」）を入れる
     *
     * @return list<int|null>
     */
    public static function items(int $page, int $totalPages): array
    {
        $pages = array_merge([1, $totalPages], range($page - 2, $page + 2));
        $pages = array_values(array_unique(array_filter($pages, fn ($p) => $p >= 1 && $p <= $totalPages)));
        sort($pages);

        $items = [];
        foreach ($pages as $i => $p) {
            if ($i > 0 && $p - $pages[$i - 1] > 1) {
                $items[] = null;
            }
            $items[] = $p;
        }

        return $items;
    }
}
