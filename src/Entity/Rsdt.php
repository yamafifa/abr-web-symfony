<?php

namespace App\Entity;

use App\Repository\RsdtRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * 住居表示-住居マスター（mt_rsdtdsp_rsdt）の 1 行
 *
 * テーブルは abr-batch で作ったものを共用する。主キーは 5 列の複合キーで、Doctrine は #[ORM\Id] を 5 つ付ければ扱える。
 * 列は「外からは読めるが、書き換えはこのクラスの中だけ」にしている（PHP 8.4 の public private(set)）。
 * 値を変えるのは update() だけ。
 */
#[ORM\Entity(repositoryClass: RsdtRepository::class)]
#[ORM\Table(name: 'mt_rsdtdsp_rsdt')]
class Rsdt
{
    /** 主キーの 5 列（列名 → プロパティ名） */
    public const array KEY_COLUMNS = [
        'lg_code' => 'lgCode',
        'machiaza_id' => 'machiazaId',
        'blk_id' => 'blkId',
        'rsdt_id' => 'rsdtId',
        'rsdt2_id' => 'rsdt2Id',
    ];

    /**
     * 主キー以外の 17 列の決まり（最大文字数はテーブル定義に合わせる）。画面の項目名は列名のまま
     *
     * @var list<array{name: string, max?: int, required?: bool, type?: 'date'}>
     */
    public const array ATTRIBUTE_RULES = [
        ['name' => 'city', 'max' => 20, 'required' => true],
        ['name' => 'ward', 'max' => 20],
        ['name' => 'oaza_cho', 'max' => 80],
        ['name' => 'chome', 'max' => 20],
        ['name' => 'koaza', 'max' => 80],
        ['name' => 'machiaza_dist', 'max' => 10],
        ['name' => 'blk_num', 'max' => 20],
        ['name' => 'rsdt_num', 'max' => 20],
        ['name' => 'rsdt_num2', 'max' => 20],
        ['name' => 'basic_rsdt_div', 'max' => 1],
        ['name' => 'rsdt_addr_flg', 'max' => 1],
        ['name' => 'rsdt_addr_mtd_code', 'max' => 1],
        ['name' => 'status_flg', 'max' => 1],
        ['name' => 'efct_date', 'type' => 'date'],
        ['name' => 'ablt_date', 'type' => 'date'],
        ['name' => 'src_code', 'max' => 2],
        ['name' => 'remarks', 'max' => 255],
    ];

    #[ORM\Id]
    #[ORM\Column(length: 6, options: ['fixed' => true])]
    public private(set) string $lgCode;

    #[ORM\Id]
    #[ORM\Column(length: 7, options: ['fixed' => true])]
    public private(set) string $machiazaId;

    #[ORM\Id]
    #[ORM\Column(length: 3)]
    public private(set) string $blkId;

    #[ORM\Id]
    #[ORM\Column(length: 3)]
    public private(set) string $rsdtId;

    /** 空文字が多い（NULL は無い。テーブルの既定値が ''） */
    #[ORM\Id]
    #[ORM\Column(length: 5, options: ['default' => ''])]
    public private(set) string $rsdt2Id = '';

    #[ORM\Column(length: 20)]
    public private(set) string $city;

    #[ORM\Column(length: 20, nullable: true)]
    public private(set) ?string $ward = null;

    #[ORM\Column(length: 80, nullable: true)]
    public private(set) ?string $oazaCho = null;

    #[ORM\Column(length: 20, nullable: true)]
    public private(set) ?string $chome = null;

    #[ORM\Column(length: 80, nullable: true)]
    public private(set) ?string $koaza = null;

    #[ORM\Column(length: 10, nullable: true)]
    public private(set) ?string $machiazaDist = null;

    #[ORM\Column(length: 20, nullable: true)]
    public private(set) ?string $blkNum = null;

    #[ORM\Column(length: 20, nullable: true)]
    public private(set) ?string $rsdtNum = null;

    #[ORM\Column(length: 20, nullable: true)]
    public private(set) ?string $rsdtNum2 = null;

    #[ORM\Column(length: 1, nullable: true)]
    public private(set) ?string $basicRsdtDiv = null;

    #[ORM\Column(length: 1, nullable: true)]
    public private(set) ?string $rsdtAddrFlg = null;

    #[ORM\Column(length: 1, nullable: true)]
    public private(set) ?string $rsdtAddrMtdCode = null;

    #[ORM\Column(length: 1, nullable: true)]
    public private(set) ?string $statusFlg = null;

    #[ORM\Column(type: Types::DATE_IMMUTABLE, nullable: true)]
    public private(set) ?\DateTimeImmutable $efctDate = null;

    #[ORM\Column(type: Types::DATE_IMMUTABLE, nullable: true)]
    public private(set) ?\DateTimeImmutable $abltDate = null;

    #[ORM\Column(length: 2, nullable: true)]
    public private(set) ?string $srcCode = null;

    #[ORM\Column(length: 255, nullable: true)]
    public private(set) ?string $remarks = null;

    /**
     * 編集できる列（主キー以外の 17 列）
     *
     * @return list<string>
     */
    public static function editableColumns(): array
    {
        return array_column(self::ATTRIBUTE_RULES, 'name');
    }

    /**
     * URL の id（主キーを "_" でつないだもの。例：131016_0001001_006_001_）を、プロパティ名 → 値にする
     * （Doctrine の find() に、そのまま渡せる形）
     * rsdt2_id は空文字が多いので、末尾が "_" で終わることがある。正しくない形なら null
     *
     * @return array<string, string>|null
     */
    public static function keyFromParam(?string $param): ?array
    {
        $parts = explode('_', (string) $param);
        if (count($parts) !== count(self::KEY_COLUMNS)) {
            return null;
        }
        foreach (array_slice($parts, 0, 4) as $p) {
            if ('' === $p) {
                return null;
            }
        }

        return array_combine(array_values(self::KEY_COLUMNS), $parts);
    }

    /** URL の id。Rails 版の to_param と同じ形 */
    public function routeId(): string
    {
        return implode('_', [$this->lgCode, $this->machiazaId, $this->blkId, $this->rsdtId, $this->rsdt2Id]);
    }

    /** 画面に出す住所（例：千代田区 内幸町一丁目 6-1） */
    public function address(): string
    {
        $town = implode(' ', array_filter(
            [$this->city, $this->ward, $this->oazaCho.$this->chome, $this->koaza],
            fn ($v) => '' !== (string) $v,
        ));
        $num = implode('-', array_filter(
            [$this->blkNum, $this->rsdtNum, $this->rsdtNum2],
            fn ($v) => '' !== (string) $v,
        ));

        return '' !== $num ? "{$town} {$num}" : $town;
    }

    /**
     * 主キーの 5 列（列名 → 値）。画面に出すため
     *
     * @return array<string, string>
     */
    public function keyValues(): array
    {
        return array_combine(array_keys(self::KEY_COLUMNS), explode('_', $this->routeId()));
    }

    /**
     * 編集できる 17 列を、フォームに出す値にする（列名 → 値。日付は YYYY-MM-DD、NULL は空文字）
     *
     * @return array<string, string>
     */
    public function formValues(): array
    {
        return array_map(fn ($v) => (string) $v, [
            'city' => $this->city,
            'ward' => $this->ward,
            'oaza_cho' => $this->oazaCho,
            'chome' => $this->chome,
            'koaza' => $this->koaza,
            'machiaza_dist' => $this->machiazaDist,
            'blk_num' => $this->blkNum,
            'rsdt_num' => $this->rsdtNum,
            'rsdt_num2' => $this->rsdtNum2,
            'basic_rsdt_div' => $this->basicRsdtDiv,
            'rsdt_addr_flg' => $this->rsdtAddrFlg,
            'rsdt_addr_mtd_code' => $this->rsdtAddrMtdCode,
            'status_flg' => $this->statusFlg,
            'efct_date' => $this->efctDate?->format('Y-m-d'),
            'ablt_date' => $this->abltDate?->format('Y-m-d'),
            'src_code' => $this->srcCode,
            'remarks' => $this->remarks,
        ]);
    }

    /**
     * 編集できる 17 列を書き換える。無い列・null・空白だけの値は NULL にする（前後の空白は取る）
     *
     * @param array<string, string|null> $values 列名 → 値。日付は YYYY-MM-DD
     *
     * @throws \InvalidArgumentException city が空のとき・日付の形が正しくないとき
     */
    public function update(array $values): void
    {
        $v = function (string $column) use ($values): ?string {
            $s = trim((string) ($values[$column] ?? ''));

            return '' === $s ? null : $s;
        };

        $this->city = $v('city') ?? throw new \InvalidArgumentException('city は必須です');
        $this->ward = $v('ward');
        $this->oazaCho = $v('oaza_cho');
        $this->chome = $v('chome');
        $this->koaza = $v('koaza');
        $this->machiazaDist = $v('machiaza_dist');
        $this->blkNum = $v('blk_num');
        $this->rsdtNum = $v('rsdt_num');
        $this->rsdtNum2 = $v('rsdt_num2');
        $this->basicRsdtDiv = $v('basic_rsdt_div');
        $this->rsdtAddrFlg = $v('rsdt_addr_flg');
        $this->rsdtAddrMtdCode = $v('rsdt_addr_mtd_code');
        $this->statusFlg = $v('status_flg');
        $this->efctDate = self::parseDate($v('efct_date'));
        $this->abltDate = self::parseDate($v('ablt_date'));
        $this->srcCode = $v('src_code');
        $this->remarks = $v('remarks');
    }

    /** YYYY-MM-DD を日付にする。「2026-02-30」のような無い日付も受け付けない */
    private static function parseDate(?string $s): ?\DateTimeImmutable
    {
        if (null === $s) {
            return null;
        }
        $d = \DateTimeImmutable::createFromFormat('!Y-m-d', $s);
        if (false === $d || $d->format('Y-m-d') !== $s) {
            throw new \InvalidArgumentException("日付の形式が正しくありません: {$s}");
        }

        return $d;
    }
}
