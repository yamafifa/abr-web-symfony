<?php

namespace App\Tests\Controller;

use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Elasticsearch 検索の画面（本物の Elasticsearch を使う）
 */
final class EsRsdtControllerTest extends WebTestCase
{
    private KernelBrowser $client;

    protected function setUp(): void
    {
        $this->client = self::createClient();
    }

    private function html(): string
    {
        return (string) $this->client->getResponse()->getContent();
    }

    public function test件数と1ページ分と次へと最後へのリンクを出す(): void
    {
        $this->client->request('GET', '/es?town=上北沢&chome=3&blk=1');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextSame('span.font-mono.text-lg', '21');
        self::assertStringContainsString('1–15 件目', $this->html());
        self::assertStringContainsString('after=131121_0001003_001_015_', $this->html());
        self::assertStringContainsString('last=1', $this->html());
        self::assertSame(15, preg_match_all('#/rsdt/[0-9_]+/edit#', $this->html()));
    }

    public function test編集と削除のリンクに今のURLを戻り先として付ける(): void
    {
        $this->client->request('GET', '/es?town=上北沢&chome=3&blk=1&after=131121_0001003_001_015_');

        self::assertStringContainsString('16–21 件目', $this->html());
        self::assertStringContainsString('/rsdt/131121_0001003_001_016_/edit?back=/es?town%3D%25E4%25B8%258A%25E5%258C%2597%25E6%25B2%25A2%26chome%3D3%26blk%3D1%26after%3D131121_0001003_001_015_', $this->html());
    }

    public function test同じ条件のDB検索へのリンクがありDB検索からも戻れる(): void
    {
        $this->client->request('GET', '/es?town=上北沢');
        self::assertStringContainsString('href="/?town=%E4%B8%8A%E5%8C%97%E6%B2%A2"', $this->html());

        $this->client->request('GET', '/?town=上北沢');
        self::assertStringContainsString('href="/es?town=%E4%B8%8A%E5%8C%97%E6%B2%A2"', $this->html());
    }
}
