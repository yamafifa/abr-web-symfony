<?php

namespace App\UseCase;

use App\Repository\RsdtRepository;
use App\Search\RsdtSearchCriteria;

/**
 * 住居を検索して、1 ページ分を返す
 */
final readonly class SearchRsdt
{
    public function __construct(private RsdtRepository $rsdts)
    {
    }

    public function call(RsdtSearchCriteria $criteria, int $page, int $perPage): SearchRsdtResult
    {
        if ($perPage <= 0) {
            throw new \InvalidArgumentException("perPage が正しくありません: {$perPage}");
        }

        $total = $this->rsdts->countBy($criteria);
        $totalPages = max(intdiv($total + $perPage - 1, $perPage), 1);
        // 最後のページより後ろを指定されたら、最後のページを出す
        $current = min(max($page, 1), $totalPages);
        $rows = $this->rsdts->searchPage($criteria, ($current - 1) * $perPage, $perPage);

        return new SearchRsdtResult($rows, $total, $current, $totalPages);
    }
}
