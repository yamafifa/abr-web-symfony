<?php

namespace App\Tests\Unit;

use App\Support\Pager;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class PagerTest extends TestCase
{
    /** @return iterable<string, array{int, int, list<int|null>}> */
    public static function cases(): iterable
    {
        yield '1 ページだけ' => [1, 1, [1]];
        yield '少ない' => [2, 5, [1, 2, 3, 4, 5]];
        yield '最初' => [1, 100, [1, 2, 3, null, 100]];
        yield '真ん中' => [50, 100, [1, null, 48, 49, 50, 51, 52, null, 100]];
        yield '最後' => [100, 100, [1, null, 98, 99, 100]];
        yield '1 つだけ飛ぶところも null' => [5, 10, [1, null, 3, 4, 5, 6, 7, null, 10]];
        yield 'すき間が無ければ null を入れない' => [4, 10, [1, 2, 3, 4, 5, 6, null, 10]];
    }

    /** @param list<int|null> $expected */
    #[DataProvider('cases')]
    public function testページ番号を今のページの前後2つと最初最後にする(int $page, int $total, array $expected): void
    {
        self::assertSame($expected, Pager::items($page, $total));
    }
}
