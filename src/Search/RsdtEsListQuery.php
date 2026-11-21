<?php

namespace App\Search;

/**
 * Elasticsearch 検索の画面の URL が表す状態（検索条件・1 ページの件数・どこから表示するか）
 * ページ番号の代わりに、主キーの文字列（キー）でページを送る（search_after）
 * - after：このキーの次から 1 ページ分（「次へ」）
 * - before：このキーの手前の 1 ページ分（「前へ」）
 * - last：最後の 1 ページ分（「最後へ」）
 * - どれも無ければ、先頭から 1 ページ分
 */
final readonly class RsdtEsListQuery
{
    /** キーの形（例：131016_0001001_006_001_）。おかしな値は無視する */
    public const KEY_FORMAT = '/\A\d{6}_\d{7}_\d{3}_\d{3}_(\d{5})?\z/';

    public function __construct(
        public RsdtSearchCriteria $criteria = new RsdtSearchCriteria(),
        public int $perPage = RsdtListQuery::DEFAULT_PER_PAGE,
        public ?string $after = null,
        public ?string $before = null,
        public bool $last = false,
    ) {
    }

    /** @param array<string, mixed> $params */
    public static function fromParams(array $params): self
    {
        $base = RsdtListQuery::fromParams($params); // 検索条件と件数の読み方は DB 検索と同じ
        $key = fn (string $name) => is_string($params[$name] ?? null) && preg_match(self::KEY_FORMAT, $params[$name]) ? $params[$name] : null;

        return new self($base->criteria, $base->perPage, $key('after'), $key('before'), ($params['last'] ?? null) === '1');
    }

    /** ページの位置を外したもの（検索条件と件数だけ） */
    public function firstPage(): self
    {
        return new self($this->criteria, $this->perPage);
    }

    public function withAfter(string $key): self
    {
        return new self($this->criteria, $this->perPage, after: $key);
    }

    public function withBefore(string $key): self
    {
        return new self($this->criteria, $this->perPage, before: $key);
    }

    public function withLast(): self
    {
        return new self($this->criteria, $this->perPage, last: true);
    }

    /** 同じ条件・件数の DB 検索（1 ページ目） */
    public function toDbQuery(): RsdtListQuery
    {
        return new RsdtListQuery($this->criteria, $this->perPage);
    }

    /**
     * URL のクエリ（空の条件・既定値は省く）
     *
     * @return array<string, string|int>
     */
    public function toParams(): array
    {
        $params = $this->toDbQuery()->toParams();
        if (null !== $this->after) {
            $params['after'] = $this->after;
        }
        if (null !== $this->before) {
            $params['before'] = $this->before;
        }
        if ($this->last) {
            $params['last'] = 1;
        }

        return $params;
    }
}
