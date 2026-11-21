<?php

namespace App\Tests\Integration;

use App\Entity\Rsdt;
use App\Search\RsdtSearchCriteria;
use App\Tests\Support\RsdtFixtures;
use App\UseCase\SearchRsdt;
use App\UseCase\SearchRsdtResult;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class SearchRsdtTest extends KernelTestCase
{
    use RsdtFixtures;

    /** @param array<string, string> $criteria */
    private static function search(array $criteria = [], int $page = 1, int $perPage = 15): SearchRsdtResult
    {
        return self::getContainer()->get(SearchRsdt::class)->call(new RsdtSearchCriteria(...$criteria), $page, $perPage);
    }

    public function test条件が無ければ全件を主キーの順に1ページ分返す(): void
    {
        $r = self::search();

        self::assertSame(self::TOTAL_ROWS, $r->total);
        self::assertCount(15, $r->rows);
        self::assertSame(intdiv(self::TOTAL_ROWS + 14, 15), $r->totalPages);
        self::assertSame(self::EXISTING_ID, $r->rows[0]->routeId()); // 元のデータの先頭の行
        $ids = array_map(fn (Rsdt $x) => $x->routeId(), $r->rows);
        $sorted = $ids;
        sort($sorted);
        self::assertSame($sorted, $ids);
    }

    public function test町字と丁目とblkNumで絞り込む(): void
    {
        // 丁目は全角の「３丁目」も半角の 3 で見つかる（MySQL の照合順序 utf8mb4_unicode_ci のため）
        $r = self::search(['town' => '上北沢', 'chome' => '3', 'blkNum' => '1'], perPage: 100);

        self::assertSame(21, $r->total);
        foreach ($r->rows as $x) {
            self::assertSame(['上北沢', '３丁目', '1'], [$x->oazaCho, $x->chome, $x->blkNum]);
        }
    }

    public function test丁目の漢数字と数字は別の文字(): void
    {
        // 「三丁目」では「３丁目」が出ない
        self::assertSame(0, self::search(['town' => '上北沢', 'chome' => '三丁目'])->total);
    }

    public function test市区町村は部分一致(): void
    {
        $r = self::search(['city' => '世田谷']);

        self::assertGreaterThan(0, $r->total);
        foreach ($r->rows as $x) {
            self::assertStringContainsString('世田谷', $x->city);
        }
    }

    public function testBlkNumとrsdtNumは完全一致(): void
    {
        // 「1」で 10・21 は出ない
        $r = self::search(['town' => '上北沢', 'chome' => '3', 'blkNum' => '1', 'rsdtNum' => '1']);

        self::assertSame(['1'], array_values(array_unique(array_map(fn (Rsdt $x) => $x->rsdtNum, $r->rows))));
    }

    public function testパーセントとアンダースコアはただの文字として探す(): void
    {
        self::assertSame(0, self::search(['city' => '%'])->total);
        self::assertSame(0, self::search(['town' => '_'])->total);
    }

    public function test最後のページより後ろを指定されたら最後のページを返す(): void
    {
        $r = self::search(['town' => '上北沢', 'chome' => '3', 'blkNum' => '1'], page: 99);

        self::assertSame([2, 2, 6], [$r->page, $r->totalPages, count($r->rows)]);
    }

    public function test0件でもtotalPagesは1(): void
    {
        $r = self::search(['city' => '存在しない市']);

        self::assertSame([0, 1, 1, []], [$r->total, $r->totalPages, $r->page, $r->rows]);
    }

    public function testPerPageが0以下なら例外(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        self::search(perPage: 0);
    }
}
