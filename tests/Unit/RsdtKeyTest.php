<?php

namespace App\Tests\Unit;

use App\Console\DbCommandGuard;
use App\Entity\Rsdt;
use App\Repository\RsdtRepository;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;

final class RsdtKeyTest extends TestCase
{
    public function testURLのidを主キーの5列にする(): void
    {
        // rsdt2_id が空なら末尾が _。キーはプロパティ名（Doctrine の find() に渡す形）
        self::assertSame(
            ['lgCode' => '131016', 'machiazaId' => '0001001', 'blkId' => '006', 'rsdtId' => '001', 'rsdt2Id' => ''],
            Rsdt::keyFromParam('131016_0001001_006_001_'),
        );
        self::assertSame('99999', Rsdt::keyFromParam('131016_0001001_006_999_99999')['rsdt2Id'] ?? null);
    }

    #[TestWith([null])]
    #[TestWith([''])]
    #[TestWith(['bad'])]
    #[TestWith(['131016_0001001_006_001'])]
    #[TestWith(['131016_0001001_006_001__x'])]
    #[TestWith(['131016__006_001_'])]
    public function test正しくないidはnull(?string $id): void
    {
        self::assertNull(Rsdt::keyFromParam($id));
    }

    public function testLIKEの特別な文字をエスケープする(): void
    {
        self::assertSame('50\\%\\_a\\\\b', RsdtRepository::escapeLike('50%_a\\b'));
    }

    /** @return iterable<array{string, bool}> */
    public static function commands(): iterable
    {
        foreach (['doctrine:schema:create', 'doctrine:schema:update', 'doctrine:schema:drop', 'doctrine:database:create',
            'doctrine:database:drop', 'doctrine:migrations:migrate', 'doctrine:migrations:diff', 'doctrine:fixtures:load'] as $c) {
            yield $c => [$c, true];
        }
        foreach (['cache:clear', 'debug:router', 'doctrine:schema:validate', 'doctrine:mapping:info', 'dbal:run-sql'] as $c) {
            yield $c => [$c, false];
        }
    }

    #[DataProvider('commands')]
    public function testDBを作り直すコマンドの名前を見分ける(string $command, bool $blocked): void
    {
        self::assertSame($blocked, DbCommandGuard::blocks($command));
    }
}
