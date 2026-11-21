<?php

namespace App\Twig;

use App\Search\RsdtListQuery;
use App\Support\Pager;
use Twig\Attribute\AsTwigFunction;

/**
 * テンプレートで使う関数
 */
final class AppExtension
{
    /**
     * ページャーに出すページ番号（間が空くところは null）
     *
     * @return list<int|null>
     */
    #[AsTwigFunction('pager_items')]
    public static function pagerItems(int $page, int $totalPages): array
    {
        return Pager::items($page, $totalPages);
    }

    /** @return list<int> */
    #[AsTwigFunction('per_page_options')]
    public static function perPageOptions(): array
    {
        return RsdtListQuery::PER_PAGE_OPTIONS;
    }
}
