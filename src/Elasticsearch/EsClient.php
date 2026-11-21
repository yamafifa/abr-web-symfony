<?php

namespace App\Elasticsearch;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Elasticsearch の REST API を呼ぶ（Symfony の HttpClient を使う。専用のパッケージは入れていない）
 * 接続先は .env の ES_URL（abr-batch の compose.yaml の http://127.0.0.1:9200）
 */
class EsClient
{
    private string $url;

    public function __construct(
        private HttpClientInterface $http,
        #[Autowire(env: 'ES_URL')] string $url,
    ) {
        $this->url = rtrim($url, '/');
    }

    /**
     * @param array<string, mixed> $body
     *
     * @return array<string, mixed>
     */
    public function post(string $path, array $body): array
    {
        return $this->send('POST', $path, ['json' => $body]);
    }

    /** @return array<string, mixed> */
    public function get(string $path): array
    {
        return $this->send('GET', $path);
    }

    /**
     * @param array<string, mixed> $options
     *
     * @return array<string, mixed>
     */
    private function send(string $method, string $path, array $options = []): array
    {
        try {
            $res = $this->http->request($method, $this->url.$path, $options + ['timeout' => 10, 'max_duration' => 10]);
            $status = $res->getStatusCode();
            if (404 === $status) {
                throw new EsException("Elasticsearch が 404 を返しました（{$path}）", notFound: true);
            }
            if ($status >= 300) {
                throw new EsException("Elasticsearch が {$status} を返しました: ".mb_substr($res->getContent(false), 0, 300));
            }

            return $res->toArray();
        } catch (TransportExceptionInterface $e) {
            throw new EsException("Elasticsearch に接続できません（{$this->url}）: {$e->getMessage()}");
        }
    }
}
