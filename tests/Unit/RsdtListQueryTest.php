<?php

namespace App\Tests\Unit;

use App\Search\RsdtListQuery;
use App\Search\RsdtSearchCriteria;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class RsdtListQueryTest extends TestCase
{
    public function testURLのクエリを検索条件と件数とページにする(): void
    {
        // 前後の空白は取る
        $q = RsdtListQuery::fromParams(['city' => ' 世田谷 ', 'town' => '上北沢', 'chome' => '3', 'blk' => '1', 'rsdt' => '2', 'per' => '50', 'page' => '3']);

        self::assertEquals(new RsdtSearchCriteria('世田谷', '上北沢', '3', '1', '2'), $q->criteria);
        self::assertSame(50, $q->perPage);
        self::assertSame(3, $q->page);
    }

    /** @return iterable<string, array{array<string, string>}> */
    public static function badParams(): iterable
    {
        yield '空' => [[]];
        yield '選べない件数' => [['per' => '20', 'page' => '0']];
        yield '数字でない' => [['per' => 'abc', 'page' => 'x']];
        yield '負のページ' => [['page' => '-1']];
        yield '小数' => [['per' => '15.5', 'page' => '1.5']];
    }

    /** @param array<string, string> $params */
    #[DataProvider('badParams')]
    public function testおかしな値は既定値にする(array $params): void
    {
        $q = RsdtListQuery::fromParams($params);

        self::assertSame(15, $q->perPage);
        self::assertSame(1, $q->page);
    }

    public function test配列で来たら最初の値を使う(): void
    {
        $q = RsdtListQuery::fromParams(['city' => ['千代田', '港'], 'per' => ['30']]);

        self::assertSame('千代田', $q->criteria->city);
        self::assertSame(30, $q->perPage);
    }

    public function test長すぎる条件は上限の文字数で切る(): void
    {
        // 文字数はマルチバイトでも 1 文字
        $q = RsdtListQuery::fromParams(['city' => str_repeat('あ', 100), 'blk' => str_repeat('1', 30)]);

        self::assertSame(80, mb_strlen($q->criteria->city));
        self::assertSame(20, strlen($q->criteria->blkNum));
    }

    public function test半角カナは全角カナにする(): void
    {
        // 濁点・半濁点は 1 文字にまとめる
        $q = RsdtListQuery::fromParams(['city' => 'ｶﾞｰﾃﾞﾝ', 'town' => 'ﾊﾟｰｸ', 'chome' => 'ｳﾞ']);

        self::assertSame(['ガーデン', 'パーク', 'ヴ'], [$q->criteria->city, $q->criteria->town, $q->criteria->chome]);
    }

    public function test半角カナのほかは変えない(): void
    {
        // 全角英数字・丸数字・互換漢字
        self::assertSame("３Ａ①\u{FA10}パ", RsdtListQuery::fromParams(['town' => "３Ａ①\u{FA10}ﾊﾟ"])->criteria->town);
    }

    public function test半角カナを全角にしてから長さで切る(): void
    {
        self::assertSame(str_repeat('パ', 20), RsdtListQuery::fromParams(['chome' => str_repeat('ﾊﾟ', 20)])->criteria->chome);
    }

    public function testURLのクエリに戻すとき空の条件と既定値は省く(): void
    {
        self::assertSame([], RsdtListQuery::fromParams([])->toParams());
        self::assertSame(
            ['town' => '上北沢', 'blk' => '1', 'per' => 30, 'page' => 2],
            RsdtListQuery::fromParams(['town' => '上北沢', 'blk' => '1', 'per' => '30', 'page' => '2'])->toParams(),
        );
    }

    public function testWithPageはページだけ変える(): void
    {
        self::assertSame(['city' => '港'], RsdtListQuery::fromParams(['city' => '港', 'page' => '5'])->withPage(1)->toParams());
    }
}
