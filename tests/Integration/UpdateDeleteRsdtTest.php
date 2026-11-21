<?php

namespace App\Tests\Integration;

use App\Tests\Support\RsdtFixtures;
use App\UseCase\DeleteRsdt;
use App\UseCase\GetRsdt;
use App\UseCase\UpdateRsdt;
use App\UseCase\UpdateRsdtStatus;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class UpdateDeleteRsdtTest extends KernelTestCase
{
    use RsdtFixtures;

    public function testGetRsdtはidで1件取る(): void
    {
        self::assertSame('千代田区 内幸町一丁目 6-1', self::getContainer()->get(GetRsdt::class)->call(self::EXISTING_ID)?->address());
    }

    public function testGetRsdtは無い行と正しくないidならnull(): void
    {
        self::assertNull(self::getContainer()->get(GetRsdt::class)->call(self::MISSING_ID));
        self::assertNull(self::getContainer()->get(GetRsdt::class)->call('bad'));
    }

    public function testUpdateRsdtは主キー以外の列を更新する(): void
    {
        $input = self::inputOf(self::EXISTING_ID, ['remarks' => 'テスト', 'efct_date' => '2026-01-02']);

        self::assertSame(UpdateRsdtStatus::Updated, self::getContainer()->get(UpdateRsdt::class)->call(self::EXISTING_ID, $input));
        $after = self::findRsdt(self::EXISTING_ID);
        self::assertSame('テスト', $after?->remarks);
        self::assertSame('2026-01-02', $after->efctDate?->format('Y-m-d'));
        self::assertSame('千代田区', $after->city);
    }

    public function testUpdateRsdtは入力に無い列とnullと空文字の列をNULLにする(): void
    {
        self::getContainer()->get(UpdateRsdt::class)->call(self::EXISTING_ID, ['city' => '千代田区', 'blk_num' => null, 'rsdt_num' => '', 'ablt_date' => ' ']);
        $after = self::findRsdt(self::EXISTING_ID);

        self::assertSame([null, null, null, null, null], [$after?->blkNum, $after?->rsdtNum, $after?->abltDate, $after?->oazaCho, $after?->efctDate]);
    }

    public function testUpdateRsdtは無い行と正しくないidならNotFound(): void
    {
        self::assertSame(UpdateRsdtStatus::NotFound, self::getContainer()->get(UpdateRsdt::class)->call(self::MISSING_ID, ['city' => 'x']));
        self::assertSame(UpdateRsdtStatus::NotFound, self::getContainer()->get(UpdateRsdt::class)->call('bad', ['city' => 'x']));
        self::assertSame(self::TOTAL_ROWS, self::countRows());
    }

    public function testUpdateRsdtは正しくない値なら例外にしてDBを変えない(): void
    {
        // 画面からは Form のチェックで来ないが、ほかから呼ばれたときのため
        foreach ([['city' => ''], ['city' => 'x', 'efct_date' => '2026-02-30']] as $input) {
            try {
                self::getContainer()->get(UpdateRsdt::class)->call(self::EXISTING_ID, $input);
                self::fail('例外にならなかった');
            } catch (\InvalidArgumentException) {
            }
        }
        self::assertSame('千代田区', self::findRsdt(self::EXISTING_ID)?->city);
    }

    public function testUpdateRsdtはほかの行を変えない(): void
    {
        $neighbor = self::findRsdt('131016_0001001_006_002_')?->formValues();
        self::getContainer()->get(UpdateRsdt::class)->call(self::EXISTING_ID, self::inputOf(self::EXISTING_ID, ['remarks' => 'テスト']));

        self::assertSame($neighbor, self::findRsdt('131016_0001001_006_002_')?->formValues());
    }

    public function testDeleteRsdtは1行だけ削除する(): void
    {
        self::getContainer()->get(DeleteRsdt::class)->call(self::EXISTING_ID);

        self::assertNull(self::findRsdt(self::EXISTING_ID));
        self::assertSame(self::TOTAL_ROWS - 1, self::countRows());
    }

    public function testDeleteRsdtは無い行と正しくないidでも失敗しない(): void
    {
        self::getContainer()->get(DeleteRsdt::class)->call(self::MISSING_ID);
        self::getContainer()->get(DeleteRsdt::class)->call('bad');
        self::getContainer()->get(DeleteRsdt::class)->call('');

        self::assertSame(self::TOTAL_ROWS, self::countRows());
    }
}
