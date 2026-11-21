<?php

namespace App\Support;

/**
 * Elasticsearch で検索するときに、文字をそろえる
 *
 * MySQL のテーブルは utf8mb4_unicode_ci なので、LIKE や = は次の違いを同じ文字として扱う。
 * Elasticsearch の wildcard 型は文字をそのまま比べるので、入れる側（abr-batch）と検索する側（ここ）の両方でそろえる。
 * - 全角と半角（「3」で「３丁目」が見つかる）、大文字と小文字 → NFKC ＋ 小文字
 * - 濁点・半濁点（「は」と「パ」）→ NFD に分けて、濁点・半濁点の記号を消す
 * - カタカナとひらがな（「あ」と「ア」）→ ひらがなにする
 * - 小さい仮名（「ヶ」と「ケ」）→ 大きい仮名にする
 * CJK 互換漢字（例：U+FA10 の「塚」）はそのまま残す（MySQL は U+585A の「塚」と別の文字として扱うため）
 *
 * abr-batch の src/lib/fold.ts、Rails 版・Laravel 版・Java 版の RsdtFold、TS 版の fold.ts と同じ処理。直すときはすべてを直す
 */
final class RsdtFold
{
    private const SMALL_TO_LARGE = [
        'ぁ' => 'あ', 'ぃ' => 'い', 'ぅ' => 'う', 'ぇ' => 'え', 'ぉ' => 'お', 'っ' => 'つ',
        'ゃ' => 'や', 'ゅ' => 'ゆ', 'ょ' => 'よ', 'ゎ' => 'わ', 'ゕ' => 'か', 'ゖ' => 'け',
    ];

    public static function call(string $s): string
    {
        // CJK 互換漢字（U+F900〜U+FAFF、U+2F800〜U+2FA1F）以外の並びだけをそろえる
        return (string) preg_replace_callback(
            '/[^\x{F900}-\x{FAFF}\x{2F800}-\x{2FA1F}]+/u',
            fn (array $m) => self::foldPart($m[0]),
            $s,
        );
    }

    private static function foldPart(string $s): string
    {
        $s = mb_strtolower((string) \Normalizer::normalize($s, \Normalizer::FORM_KC));
        $s = (string) preg_replace('/[\x{3099}\x{309A}]/u', '', (string) \Normalizer::normalize($s, \Normalizer::FORM_D));
        $s = (string) preg_replace_callback('/[\x{30A1}-\x{30F6}]/u', fn (array $m) => (string) mb_chr((int) mb_ord($m[0]) - 0x60), $s);

        return strtr($s, self::SMALL_TO_LARGE);
    }
}
