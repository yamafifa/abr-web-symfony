<?php

namespace App\UseCase;

use App\Elasticsearch\EsClient;
use App\Elasticsearch\EsException;
use App\Entity\Rsdt;
use App\Search\RsdtSearchCriteria;
use App\Support\RsdtFold;

/**
 * Elasticsearch（abr-batch の es:index で作った別名 rsdt）で住居を検索して、1 ページ分を返す
 * - 条件の意味は DB 検索（RsdtRepository）と同じ。部分一致は wildcard、完全一致は term で探す
 * - 文字は RsdtFold でそろえてから探す（MySQL の utf8mb4_unicode_ci と同じように見つけるため）
 * - 並びは主キー順。ページは search_after で送る（Elasticsearch は先頭から 10,000 件より先へ from で進めないため）
 */
final readonly class SearchRsdtEs
{
    public const string ALIAS = 'rsdt';

    /** 検索条件の項目 → 部分一致で探す列（どれかに当たればよい） */
    private const array WILDCARD_FIELDS = ['city' => ['city', 'ward'], 'town' => ['oaza_cho', 'koaza'], 'chome' => ['chome']];

    /** 検索条件の項目 → 完全一致で探す列 */
    private const array EXACT_FIELDS = ['blkNum' => 'blk_num', 'rsdtNum' => 'rsdt_num'];

    public function __construct(private EsClient $client)
    {
    }

    public function call(RsdtSearchCriteria $criteria, int $perPage, ?string $after = null, ?string $before = null, bool $last = false): SearchRsdtEsResult
    {
        if ($perPage <= 0) {
            throw new \InvalidArgumentException("perPage が正しくありません: {$perPage}");
        }

        try {
            $filters = $this->filters($criteria);
            [$hits, $total, $index] = $this->fetch($filters, $perPage, $after, $before, $last);
            // 「前へ」で 1 ページに満たなかったら、先頭まで来ているので、先頭の 1 ページを出す
            if (null !== $before && count($hits) < $perPage) {
                [$hits, $total, $index] = $this->fetch($filters, $perPage);
            }

            $firstKey = $hits[0]['_source']['key'] ?? null;
            // このページより前に何件あるか（何件目からかを出すため）
            $beforeCount = null !== $firstKey ? $this->count([...$filters, ['range' => ['key' => ['lt' => $firstKey]]]]) : 0;
            $lastHit = [] === $hits ? null : $hits[count($hits) - 1];

            return new SearchRsdtEsResult(
                rows: array_map(fn (array $h) => Rsdt::fromColumns($h['_source']), $hits),
                total: $total,
                from: [] === $hits ? 0 : $beforeCount + 1,
                prevKey: $beforeCount > 0 ? $firstKey : null,
                nextKey: null !== $lastHit && $beforeCount + count($hits) < $total ? $lastHit['_source']['key'] : null,
                index: $index ?? $this->aliasTarget(),
            );
        } catch (EsException $e) {
            return new SearchRsdtEsResult(error: $e->notFound
                ? 'Elasticsearch にインデックス '.self::ALIAS.' がありません。abr-batch で npm run es:index を実行してください'
                : $e->getMessage());
        }
    }

    /** @return list<array<string, mixed>> */
    private function filters(RsdtSearchCriteria $c): array
    {
        $filters = [];
        foreach (self::WILDCARD_FIELDS as $prop => $cols) {
            if ('' === $c->{$prop}) {
                continue;
            }
            $pattern = '*'.addcslashes(RsdtFold::call($c->{$prop}), '\\*?').'*'; // * ? \ は、ただの文字として探す
            $filters[] = ['bool' => [
                'should' => array_map(fn ($col) => ['wildcard' => ["fold.{$col}" => ['value' => $pattern]]], $cols),
                'minimum_should_match' => 1,
            ]];
        }
        foreach (self::EXACT_FIELDS as $prop => $col) {
            if ('' !== $c->{$prop}) {
                $filters[] = ['term' => ["fold.{$col}" => RsdtFold::call($c->{$prop})]];
            }
        }

        return $filters;
    }

    /**
     * 1 ページ分を取る。逆順で取ったときは、主キー順に並べ直して返す
     *
     * @param list<array<string, mixed>> $filters
     *
     * @return array{0: list<array{_index: string, _source: array<string, string|null>}>, 1: int, 2: string|null}
     */
    private function fetch(array $filters, int $perPage, ?string $after = null, ?string $before = null, bool $last = false): array
    {
        $desc = null !== $before || $last;
        $body = [
            'track_total_hits' => true,
            'size' => $perPage,
            'sort' => [['key' => $desc ? 'desc' : 'asc']],
            'query' => ['bool' => ['filter' => $filters]],
        ];
        $cursor = $after ?? $before;
        if (null !== $cursor) {
            $body['search_after'] = [$cursor];
        }
        /** @var array{hits: array{total: array{value: int}, hits: list<array{_index: string, _source: array<string, string|null>}>}} $res */
        $res = $this->client->post('/'.self::ALIAS.'/_search', $body);
        $hits = $res['hits']['hits'];

        return [$desc ? array_reverse($hits) : $hits, $res['hits']['total']['value'], $hits[0]['_index'] ?? null];
    }

    /** @param list<array<string, mixed>> $filters */
    private function count(array $filters): int
    {
        /** @var array{count: int} $res */
        $res = $this->client->post('/'.self::ALIAS.'/_count', ['query' => ['bool' => ['filter' => $filters]]]);

        return $res['count'];
    }

    private function aliasTarget(): ?string
    {
        $key = array_key_first($this->client->get('/_alias/'.self::ALIAS));

        return null === $key ? null : (string) $key;
    }
}
