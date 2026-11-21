<?php

namespace App\Search;

/**
 * 一覧画面の URL が表す状態（検索条件・1 ページの件数・ページ番号）
 */
final readonly class RsdtListQuery
{
    public const PER_PAGE_OPTIONS = [15, 30, 50, 100];

    public const DEFAULT_PER_PAGE = 15;

    /** 検索条件の項目 → URL のクエリ名（URL は短くしたいので、blk・rsdt にしている） */
    public const PARAM_NAMES = ['city' => 'city', 'town' => 'town', 'chome' => 'chome', 'blkNum' => 'blk', 'rsdtNum' => 'rsdt'];

    /** 入力の長さの上限（テーブルの列の長さに合わせる） */
    public const MAX_LENGTH = ['city' => 80, 'town' => 80, 'chome' => 20, 'blkNum' => 20, 'rsdtNum' => 20];

    public function __construct(
        public RsdtSearchCriteria $criteria = new RsdtSearchCriteria(),
        public int $perPage = self::DEFAULT_PER_PAGE,
        public int $page = 1,
    ) {
    }

    /**
     * URL のクエリを一覧の状態にする。おかしな値は既定値にする
     *
     * @param array<string, mixed> $params
     */
    public static function fromParams(array $params): self
    {
        $first = function ($v): string {
            $v = is_array($v) ? (reset($v) ?: '') : $v;

            return is_string($v) || is_int($v) ? trim((string) $v) : '';
        };
        $values = [];
        foreach (self::PARAM_NAMES as $prop => $name) {
            $values[$prop] = mb_substr($first($params[$name] ?? ''), 0, self::MAX_LENGTH[$prop]);
        }
        $per = filter_var($first($params['per'] ?? ''), FILTER_VALIDATE_INT);
        $page = filter_var($first($params['page'] ?? ''), FILTER_VALIDATE_INT);

        return new self(
            criteria: new RsdtSearchCriteria(...$values),
            perPage: in_array($per, self::PER_PAGE_OPTIONS, true) ? $per : self::DEFAULT_PER_PAGE,
            page: is_int($page) && $page > 0 ? $page : 1,
        );
    }

    public function withPage(int $page): self
    {
        return new self($this->criteria, $this->perPage, $page);
    }

    /**
     * URL のクエリ（空の条件・既定値は省く）
     *
     * @return array<string, string|int>
     */
    public function toParams(): array
    {
        $params = [];
        foreach (self::PARAM_NAMES as $prop => $name) {
            if ('' !== $this->criteria->{$prop}) {
                $params[$name] = $this->criteria->{$prop};
            }
        }
        if (self::DEFAULT_PER_PAGE !== $this->perPage) {
            $params['per'] = $this->perPage;
        }
        if ($this->page > 1) {
            $params['page'] = $this->page;
        }

        return $params;
    }
}
