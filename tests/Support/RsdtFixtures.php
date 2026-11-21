<?php

namespace App\Tests\Support;

use App\Entity\Rsdt;
use App\Repository\RsdtRepository;
use Doctrine\ORM\EntityManagerInterface;

/**
 * DB を使うテストで共通に使うもの
 *
 * テストは本物の DB（abr-batch と共用）を使い、1 件ごとに dama/doctrine-test-bundle がトランザクションで取り消す。
 * テーブルを作り直すことはしない。
 */
trait RsdtFixtures
{
    /** 元のデータにある行（千代田区 内幸町一丁目 6-1） */
    public const string EXISTING_ID = '131016_0001001_006_001_';

    /** 無い行 */
    public const string MISSING_ID = '131016_0001001_006_999_99999';

    /** 元のデータの全件数 */
    public const int TOTAL_ROWS = 1_752_573;

    /** DB から読み直す（Doctrine が覚えている前の値を使わないように、先に忘れさせる） */
    protected static function findRsdt(string $id): ?Rsdt
    {
        $container = static::getContainer();
        $container->get(EntityManagerInterface::class)->clear();

        return $container->get(RsdtRepository::class)->findByRouteId($id);
    }

    protected static function countRows(): int
    {
        return static::getContainer()->get(RsdtRepository::class)->count();
    }

    /**
     * 主キー以外の 17 列を、DB の今の値で埋めた入力（$overrides で一部を変える）
     *
     * @param array<string, string|null> $overrides
     *
     * @return array<string, string|null>
     */
    protected static function inputOf(string $id, array $overrides = []): array
    {
        $rsdt = self::findRsdt($id) ?? self::fail("{$id} が DB に無い（abr-batch で npm run import:v3 をして元に戻す）");

        return array_merge($rsdt->formValues(), $overrides);
    }
}
