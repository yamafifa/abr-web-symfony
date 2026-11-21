<?php

namespace App\Tests\Integration;

use App\Elasticsearch\EsClient;
use App\Entity\Rsdt;
use App\Search\RsdtSearchCriteria;
use App\Tests\Support\RsdtFixtures;
use App\UseCase\SearchRsdt;
use App\UseCase\SearchRsdtEs;
use App\UseCase\SearchRsdtEsResult;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HttpClient\HttpClient;

/**
 * 本物の Elasticsearch（abr-batch の npm run es:index で作ったインデックス）を使う
 */
final class SearchRsdtEsTest extends KernelTestCase
{
    use RsdtFixtures;

    /** 世田谷区 上北沢３丁目 の blk_num=1（21 件） */
    private const array KAMIKITAZAWA_3_1 = ['town' => '上北沢', 'chome' => '3', 'blkNum' => '1'];

    /** @param array<string, string> $criteria */
    private static function search(array $criteria, int $perPage, ?string $after = null, ?string $before = null, bool $last = false): SearchRsdtEsResult
    {
        return self::getContainer()->get(SearchRsdtEs::class)->call(new RsdtSearchCriteria(...$criteria), $perPage, $after, $before, $last);
    }

    /**
     * @param list<Rsdt> $rows
     *
     * @return list<string>
     */
    private static function ids(array $rows): array
    {
        return array_map(fn (Rsdt $r) => $r->routeId(), $rows);
    }

    public function testDB検索と同じ件数と同じ順で返す(): void
    {
        $r = self::search(self::KAMIKITAZAWA_3_1, 100);
        $db = self::getContainer()->get(SearchRsdt::class)->call(new RsdtSearchCriteria(...self::KAMIKITAZAWA_3_1), 1, 100);

        self::assertSame(21, $r->total);
        self::assertSame(self::ids($db->rows), self::ids($r->rows));
    }

    public function test画面に出す値はDBと同じ(): void
    {
        $row = self::search(self::KAMIKITAZAWA_3_1, 1)->rows[0];
        $db = self::findRsdt($row->routeId()) ?? self::fail('DB に無い');

        self::assertSame($db->address(), $row->address());
        self::assertSame($db->formValues(), $row->formValues());
    }

    public function test次へと前へで重ならずに行き来できる(): void
    {
        $p1 = self::search(self::KAMIKITAZAWA_3_1, 10);
        $p2 = self::search(self::KAMIKITAZAWA_3_1, 10, after: $p1->nextKey);
        $p3 = self::search(self::KAMIKITAZAWA_3_1, 10, after: $p2->nextKey);

        self::assertSame([1, 11, 21], [$p1->from, $p2->from, $p3->from]);
        self::assertSame([null, null], [$p1->prevKey, $p3->nextKey]);
        self::assertSame(self::ids(self::search(self::KAMIKITAZAWA_3_1, 100)->rows), [...self::ids($p1->rows), ...self::ids($p2->rows), ...self::ids($p3->rows)]);
        self::assertSame(self::ids($p2->rows), self::ids(self::search(self::KAMIKITAZAWA_3_1, 10, before: $p3->prevKey)->rows));
    }

    public function test前へで1ページに満たないときは先頭の1ページを出す(): void
    {
        $p2 = self::search(self::KAMIKITAZAWA_3_1, 10, after: self::search(self::KAMIKITAZAWA_3_1, 5)->nextKey);
        $back = self::search(self::KAMIKITAZAWA_3_1, 10, before: $p2->prevKey);

        self::assertSame([1, 10, null], [$back->from, count($back->rows), $back->prevKey]);
    }

    public function test最後へは最後の1ページ分を出す(): void
    {
        $r = self::search(self::KAMIKITAZAWA_3_1, 10, last: true);

        self::assertSame([12, 10, null], [$r->from, count($r->rows), $r->nextKey]);
    }

    public function testMySQLと同じく全角と半角を同じ文字として探す(): void
    {
        $c = ['town' => '上北沢', 'chome' => '3'];
        $db = self::getContainer()->get(SearchRsdt::class)->call(new RsdtSearchCriteria(...$c), 1, 1);

        self::assertSame($db->total, self::search($c, 1)->total);
    }

    public function testワイルドカードの記号はただの文字として探す(): void
    {
        self::assertSame(0, self::search(['city' => '*'], 1)->total);
        self::assertSame(0, self::search(['town' => '?'], 1)->total);
    }

    public function testインデックスの名前と作成日時を返す(): void
    {
        $r = self::search(['city' => '存在しない市'], 1);

        self::assertMatchesRegularExpression('/\Arsdt_\d{8}-\d{6}\z/', (string) $r->index);
        self::assertMatchesRegularExpression('/\A\d{4}-\d\d-\d\d \d\d:\d\d:\d\d\z/', (string) $r->builtAt());
    }

    public function testElasticsearchに接続できないときはエラーの文を返す(): void
    {
        $r = (new SearchRsdtEs(new EsClient(HttpClient::create(), 'http://127.0.0.1:1')))->call(new RsdtSearchCriteria(...self::KAMIKITAZAWA_3_1), 10);

        self::assertSame([[], 0], [$r->rows, $r->total]);
        self::assertStringContainsString('接続できません', (string) $r->error);
    }
}
