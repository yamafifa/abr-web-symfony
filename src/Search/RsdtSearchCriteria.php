<?php

namespace App\Search;

/**
 * 住居の検索条件。空文字の条件は使わない
 * - city: city・ward の部分一致
 * - town: oaza_cho・koaza の部分一致
 * - chome: chome の部分一致
 * - blkNum・rsdtNum: 完全一致
 */
final readonly class RsdtSearchCriteria
{
    public function __construct(
        public string $city = '',
        public string $town = '',
        public string $chome = '',
        public string $blkNum = '',
        public string $rsdtNum = '',
    ) {
    }
}
