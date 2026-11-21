<?php

namespace App\Tests\Unit;

use App\Search\RsdtEsListQuery;
use PHPUnit\Framework\TestCase;

final class RsdtEsListQueryTest extends TestCase
{
    private const string KEY = '131016_0001001_006_001_';

    public function test検索条件と件数とafterをURLから読みURLに戻せる(): void
    {
        $q = RsdtEsListQuery::fromParams(['town' => '上北沢', 'per' => '30', 'after' => self::KEY]);

        self::assertSame(['上北沢', 30, self::KEY, null, false], [$q->criteria->town, $q->perPage, $q->after, $q->before, $q->last]);
        self::assertSame(['town' => '上北沢', 'per' => 30, 'after' => self::KEY], $q->toParams());
    }

    public function testキーの形でないafterとbeforeは無視する(): void
    {
        $q = RsdtEsListQuery::fromParams(['after' => '1 OR 1=1', 'before' => ['x']]);

        self::assertSame([null, null], [$q->after, $q->before]);
    }

    public function testFirstPageはページの位置だけを外す(): void
    {
        $q = RsdtEsListQuery::fromParams(['chome' => '3', 'before' => self::KEY, 'last' => '1']);

        self::assertSame(['chome' => '3'], $q->firstPage()->toParams());
    }
}
