<?php

namespace App\Tests\Unit;

use App\Support\RsdtFold;
use PHPUnit\Framework\TestCase;

final class RsdtFoldTest extends TestCase
{
    public function test全角と半角と大文字と小文字をそろえる(): void
    {
        self::assertSame('3丁目abc', RsdtFold::call('３丁目ＡＢｃ'));
    }

    public function testカタカナをひらがなにして濁点と半濁点を消す(): void
    {
        self::assertSame('はかあ', RsdtFold::call('パガあ'));
    }

    public function test小さい仮名を大きくする(): void
    {
        self::assertSame('けかつ', RsdtFold::call('ヶヵっ'));
    }

    public function testCJK互換漢字はそのまま残す(): void
    {
        $compat = mb_chr(0xFA10); // 塚（互換漢字）。MySQL は U+585A の「塚」と別の文字として扱う
        self::assertSame($compat, RsdtFold::call($compat));
        self::assertSame('塚', RsdtFold::call('塚'));
    }
}
