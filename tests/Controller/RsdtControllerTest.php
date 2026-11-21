<?php

namespace App\Tests\Controller;

use App\Tests\Support\RsdtFixtures;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * 画面を通したテスト（ブラウザの代わりに KernelBrowser でリクエストを送る。JS は動かない）
 *
 * CSRF は Symfony 8 の既定の「ステートレス」方式で、送り元（Origin ヘッダー）が同じサイトかを見る。
 * ブラウザは自動で付けるが、テストでは自分で付ける。
 */
final class RsdtControllerTest extends WebTestCase
{
    use RsdtFixtures;

    private const array SAME_ORIGIN = ['HTTP_ORIGIN' => 'http://localhost'];

    private KernelBrowser $client;

    protected function setUp(): void
    {
        $this->client = self::createClient();
    }

    private function html(): string
    {
        return (string) $this->client->getResponse()->getContent();
    }

    /**
     * 編集フォームを送る（列名 → 値。CSRF トークンも付ける）
     *
     * @param array<string, string|null> $input
     * @param array<string, string>      $server
     */
    private function patch(string $id, string $back, array $input, array $server = self::SAME_ORIGIN): void
    {
        $this->client->request('PATCH', "/rsdt/{$id}?back=".urlencode($back), ['rsdt' => $input + ['_token' => 'csrf-token']], server: $server);
    }

    /** @param array<string, string> $server */
    private function delete(string $id, string $back, array $server = self::SAME_ORIGIN): void
    {
        $this->client->request('DELETE', "/rsdt/{$id}?back=".urlencode($back), ['_csrf_token' => 'csrf-token'], server: $server);
    }

    // ---- 一覧

    public function test一覧は検索結果と件数を出す(): void
    {
        $this->client->request('GET', '/?town=上北沢&chome=3&blk=1');

        self::assertResponseIsSuccessful();
        self::assertStringContainsString('上北沢３丁目 1-1', $this->html());
        self::assertSelectorTextSame('span.font-mono.text-lg', '21');
    }

    public function test一覧の編集と削除のリンクに今の検索条件とページを付ける(): void
    {
        $crawler = $this->client->request('GET', '/?town=上北沢&chome=3&blk=1&page=2');
        // 戻り先の URL（/?town=%E4%B8%8A...）を、さらに URL のクエリに入れるので、2 回エンコードされる
        $router = self::getContainer()->get(UrlGeneratorInterface::class);
        $back = $router->generate('rsdts_index', ['town' => '上北沢', 'chome' => '3', 'blk' => '1', 'page' => 2]);
        $id = '131121_0001003_001_016_';

        self::assertStringContainsString('back=/?town%3D%25E4%25B8%258A', $router->generate('rsdts_edit', ['id' => $id, 'back' => $back]));
        self::assertSame($router->generate('rsdts_edit', ['id' => $id, 'back' => $back]), $crawler->filter('a:contains("編集")')->first()->attr('href'));
        self::assertSame($router->generate('rsdts_delete', ['id' => $id, 'back' => $back]), $crawler->filter('form:has(input[value="DELETE"])')->first()->attr('action'));
    }

    public function test一覧は0件のときそう出す(): void
    {
        $this->client->request('GET', '/?city=存在しない市');

        self::assertSelectorTextContains('main', '該当する住居がありません');
    }

    public function test入力値はHTMLとしてではなく文字として出す(): void
    {
        $this->client->request('GET', '/?city=<script>x</script>');

        self::assertResponseIsSuccessful();
        self::assertStringNotContainsString('<script>x</script>', $this->html());
        self::assertStringContainsString('&lt;script&gt;x&lt;/script&gt;', $this->html());
    }

    // ---- 編集画面

    public function test編集画面は今の値と戻り先を出す(): void
    {
        $crawler = $this->client->request('GET', '/rsdt/'.self::EXISTING_ID.'/edit?back=/?page=2');

        self::assertResponseIsSuccessful();
        self::assertSame('千代田区', $crawler->filter('input[name="rsdt[city]"]')->attr('value'));
        self::assertSame('date', $crawler->filter('input[name="rsdt[efct_date]"]')->attr('type'));
        self::assertCount(1, $crawler->filter('a[href="/?page=2"]:contains("一覧に戻る")'));
    }

    public function test編集画面は外のサイトへの戻り先を使わない(): void
    {
        foreach (['//evil.example', '/\\evil.example', 'https://evil.example'] as $back) {
            $this->client->request('GET', '/rsdt/'.self::EXISTING_ID.'/edit?back='.urlencode($back));

            self::assertResponseIsSuccessful();
            self::assertStringNotContainsString('evil.example', $this->html());
        }
    }

    public function test編集画面は無い行と正しくないidなら404(): void
    {
        $this->client->request('GET', '/rsdt/'.self::MISSING_ID.'/edit');
        self::assertResponseStatusCodeSame(404);
        self::assertStringContainsString('この住居は見つかりません', $this->html());

        $this->client->request('GET', '/rsdt/bad/edit');
        self::assertResponseStatusCodeSame(404);
    }

    // ---- 更新

    public function test更新して戻り先へお知らせ付きでredirectする(): void
    {
        // 前後の空白は取り、空文字は NULL
        $this->patch(self::EXISTING_ID, '/?page=2', self::inputOf(self::EXISTING_ID, ['remarks' => '  テスト  ', 'koaza' => '', 'efct_date' => '2026-01-02']));

        self::assertResponseRedirects('/?page=2');
        $after = self::findRsdt(self::EXISTING_ID);
        self::assertSame(['テスト', null, '2026-01-02'], [$after?->remarks, $after?->koaza, $after?->efctDate?->format('Y-m-d')]);
        $this->client->followRedirect();
        self::assertSelectorTextContains('[data-controller="flash"]', '更新しました');
    }

    /** @return iterable<string, array{array<string, string>, string, string}> */
    public static function invalidInputs(): iterable
    {
        yield 'city が空' => [['city' => '  '], 'city', '必須です'];
        yield 'city が長すぎる（21 文字）' => [['city' => str_repeat('あ', 21)], 'city', '20 文字以内で入力してください'];
        yield '無い日付' => [['efct_date' => '2026-02-30'], 'efct_date', '日付の形式が正しくありません（YYYY-MM-DD）'];
        yield '別の書き方の日付' => [['ablt_date' => '2026/01/01'], 'ablt_date', '日付の形式が正しくありません（YYYY-MM-DD）'];
    }

    /** @param array<string, string> $overrides */
    #[DataProvider('invalidInputs')]
    public function test入力エラーなら編集画面をエラー付きで出してDBは変えない(array $overrides, string $column, string $message): void
    {
        $before = self::findRsdt(self::EXISTING_ID)?->formValues();

        $this->patch(self::EXISTING_ID, '/', self::inputOf(self::EXISTING_ID, $overrides + ['remarks' => '入力した値']));

        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains("label:has(input[name=\"rsdt[{$column}]\"]) span.mt-1.text-rose-400", $message);
        self::assertSelectorTextContains('[data-controller="flash"]', '入力内容を確認してください');
        // 入力した値をそのまま出す
        self::assertSelectorExists('input[name="rsdt[remarks]"][value="入力した値"]');
        self::assertSame($before, self::findRsdt(self::EXISTING_ID)?->formValues());
    }

    public function test削除された行の更新はお知らせを出して戻り先に戻す(): void
    {
        $this->patch(self::MISSING_ID, '/?page=3', ['city' => '千代田区']);

        self::assertResponseRedirects('/?page=3');
        $this->client->followRedirect();
        self::assertSelectorTextContains('[data-controller="flash"]', 'この行はすでに削除されています');
        self::assertSame(self::TOTAL_ROWS, self::countRows());
    }

    public function test別のサイトから送られた更新は受け付けない(): void
    {
        $this->patch(self::EXISTING_ID, '/', self::inputOf(self::EXISTING_ID, ['remarks' => 'なりすまし']), ['HTTP_ORIGIN' => 'https://evil.example']);

        self::assertResponseStatusCodeSame(422);
        self::assertNull(self::findRsdt(self::EXISTING_ID)?->remarks);
    }

    // ---- 削除

    public function test削除して戻り先へお知らせ付きでredirectする(): void
    {
        $this->delete(self::EXISTING_ID, '/?page=2');

        self::assertResponseRedirects('/?page=2');
        self::assertNull(self::findRsdt(self::EXISTING_ID));
        $this->client->followRedirect();
        self::assertSelectorTextContains('[data-controller="flash"]', '削除しました');
    }

    public function test削除の外のサイトへの戻り先は一覧に変える(): void
    {
        $this->delete(self::MISSING_ID, '//evil.example');

        self::assertResponseRedirects('/');
    }

    public function test別のサイトから送られた削除は受け付けない(): void
    {
        $this->delete(self::EXISTING_ID, '/', ['HTTP_ORIGIN' => 'https://evil.example']);

        self::assertResponseRedirects('/');
        self::assertNotNull(self::findRsdt(self::EXISTING_ID));
    }
}
