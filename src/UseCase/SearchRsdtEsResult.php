<?php

namespace App\UseCase;

use App\Entity\Rsdt;

/**
 * from：このページの先頭が何件目か（0 件なら 0）
 * prevKey・nextKey：「前へ」「次へ」のリンクに使うキー。null ならそちらにページは無い
 * index：検索したインデックスの名前（rsdt_日時）。error：失敗したときの文
 */
final readonly class SearchRsdtEsResult
{
    /** @param list<Rsdt> $rows */
    public function __construct(
        public array $rows = [],
        public int $total = 0,
        public int $from = 0,
        public ?string $prevKey = null,
        public ?string $nextKey = null,
        public ?string $index = null,
        public ?string $error = null,
    ) {
    }

    /** インデックスを作った日時（名前の rsdt_20261121-215617 から読む） */
    public function builtAt(): ?string
    {
        return preg_match('/_(\d{4})(\d\d)(\d\d)-(\d\d)(\d\d)(\d\d)\z/', (string) $this->index, $m)
            ? "{$m[1]}-{$m[2]}-{$m[3]} {$m[4]}:{$m[5]}:{$m[6]}"
            : null;
    }
}
