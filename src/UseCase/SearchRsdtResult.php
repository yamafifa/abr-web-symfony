<?php

namespace App\UseCase;

use App\Entity\Rsdt;

/**
 * page は実際に表示したページ。totalPages は 0 件でも 1
 */
final readonly class SearchRsdtResult
{
    /** @param list<Rsdt> $rows */
    public function __construct(
        public array $rows,
        public int $total,
        public int $page,
        public int $totalPages,
    ) {
    }
}
