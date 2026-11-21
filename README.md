# abr-web-symfony

ABR（アドレス・ベース・レジストリ）の住居表示-住居マスター（`mt_rsdtdsp_rsdt`、東京都 1,752,573 件）を検索して、一覧表示・編集・削除する Web アプリです。
勉強用に、同じものを TypeScript・Ruby on Rails・PHP Laravel・PHP Symfony で作るうちの Symfony 版です。

- PHP 8.5 ＋ Symfony 8.1.11 ＋ Doctrine ORM 3.7 ＋ Twig ＋ Symfony Form ＋ Tailwind CSS 4 ＋ Stimulus（AssetMapper）＋ PHPUnit 13
- 構成は「Controller → ユースケース → Repository → Entity」（`src/UseCase/`）。ユースケースの名前は、ほかの版とそろえています
- DB は [abr-batch](https://github.com/yamafifa/abr-batch) の MySQL を共用します（テーブルも abr-batch で作ったもの）
- JS と CSS は AssetMapper（Symfony の仕組み）で配るので、Node.js でのビルドは要りません

## できること

TS 版・Rails 版・Laravel 版と同じです。

- **検索**：市区町村（`city`・`ward`）、町字（`oaza_cho`・`koaza`）、丁目（`chome`）の部分一致と、`blk_num`・`rsdt_num` の完全一致。条件は URL に残ります
- **一覧**：主キー順。1 ページ 15 件（30・50・100 件も選べます）。ページャー付き
- **編集**：主キー以外の 17 列。「更新」を押すと、変わった項目をモーダルで見せてから保存します
- **削除**：一覧と編集画面から。モーダルで確認してから削除します

## 動かし方

1. abr-batch で DB を起動して、データを入れておく（abr-batch の README「動かし方」を参照）
2. このリポジトリで：

```bash
composer install                  # 最後に importmap:install も動き、Stimulus を assets/vendor/ に落とす
php bin/console tailwind:build    # 初回は Tailwind の単体プログラム（v4.3.4）を var/tailwind/ に落とす
php -S 127.0.0.1:3000 -t public   # http://127.0.0.1:3000
```

- 起動と停止の詳しいやり方は [docs/start-stop.md](docs/start-stop.md) にあります
- 編集・削除したデータは、abr-batch で `npm run import:v3`（約 15 秒）を実行すると元に戻せます
- **`doctrine:schema:update` などの、DB を作り直すコマンドは使えません。** DB を abr-batch と共用しているので、[src/Console/DbCommandGuard.php](src/Console/DbCommandGuard.php) で止めています。例えば `doctrine:schema:update` は、このアプリの知らないテーブル（Rails・Prisma の管理表）を DROP しようとします（`--dump-sql` で SQL だけ出して確かめた）
- 開発中は、画面の下に Symfony のツールバー（web-profiler）が出ます。実行した SQL や時間を見られます

## 構成

| 場所 | 内容 |
|---|---|
| `src/Entity/Rsdt.php` | Doctrine のエンティティ。複合主キー（`#[ORM\Id]` を 5 つ）、17 列の決まり、住所の表示、値の書き換え（`update()`） |
| `src/Repository/RsdtRepository.php` | SQL を組み立てる所：検索・件数・1 件取る（ロック付きも）・削除 |
| `src/Search/` | 検索条件（`RsdtSearchCriteria`）と、一覧の URL ⇔ 検索条件・件数・ページ（`RsdtListQuery`） |
| `src/Form/RsdtType.php` | 編集フォームと入力チェック（Symfony Form ＋ Validator） |
| `src/UseCase/` | ユースケース：`SearchRsdt`（ページ番号の補正もここ）・`GetRsdt`・`UpdateRsdt`・`DeleteRsdt` |
| `src/Controller/RsdtController.php` | パラメーターを読む → ユースケースを呼ぶ → 表示・redirect。ルートも属性（`#[Route]`）でここに書く |
| `src/Support/Pager.php`・`src/Twig/AppExtension.php` | ページャーに出すページ番号と、それをテンプレートで使う関数 |
| `templates/` | Twig：レイアウト・お知らせ・確認モーダル・一覧・編集・検索フォーム・削除ボタン・ページャー・404 |
| `assets/controllers/` | Stimulus：確認モーダル（`confirm`）・お知らせを消す（`flash`）・件数を変えたら送る（`auto-submit`）。Rails 版と同じもの |
| `src/Console/DbCommandGuard.php` | DB を作り直すコマンドを止める |

Laravel 版との違い：

- **入力チェックに通らないとき、redirect せずに編集画面をそのまま出します**（HTTP 422）。Laravel 版は、編集画面に redirect して、入力値とエラーをセッションで渡します。Symfony Form はこの形がふつうです
- **フォームの項目名に、フォームの名前が付きます**（`city` ではなく `rsdt[city]`）
- 前後の空白を取る・空文字を NULL にするのは、Symfony Form の TextType が最初からやります（Laravel はミドルウェア）
- **CSRF 対策は Symfony 8 の既定の「ステートレス」方式です。** セッションにトークンを置かず、送り元（`Origin` ヘッダー）が同じサイトかを見ます。JS が動くときは、`csrf_protection_controller.js` が乱数のトークンを作り、フォームとクッキーの両方に入れて送ります
- エンティティの列は `public private(set)`（PHP 8.4 の書き方）にしました。外からは読めますが、書き換えはエンティティの中の `update()` だけです
- DB を作り直すコマンドの止め方：Laravel 版は例外を投げますが、こちらは Symfony の `disableCommand()` でコマンドを動かさずに終わらせます（終了コード 113）。名前は省略形（`doctrine:sc:up` など）を解決したあとのものを見るので、省略形でも止まります

## テスト

```bash
php bin/phpunit                        # 78 件、約 20 秒
vendor/bin/phpstan analyse             # 型の検査（レベル 8）
vendor/bin/php-cs-fixer fix --dry-run  # 書き方のチェック（直すときは --dry-run を外す）
```

- `tests/Unit` は Symfony を起動しないテスト、`tests/Integration` はユースケースを本物の DB で試すテスト、`tests/Controller` は画面を通したテスト（WebTestCase）です
- DB を使うテストは、1 件ごとに dama/doctrine-test-bundle がトランザクションで取り消すので、DB の中身は変わりません
- テストも開発も同じ DB（`abr`）を使います

## 作ってみて分かったこと

- **Doctrine は複合主キーに対応している**：`#[ORM\Id]` を 5 つ付けるだけで、`find()` に「プロパティ名 → 値」の配列を渡して取れます。Laravel 版のように保存の WHERE を自分で作る必要はありませんでした
- **`public private(set)` のプロパティも Doctrine で読み書きできた**：Doctrine 3.7.6 で、テストで確かめました
- **雛形は、共用 DB に向かない設定になっている**：DB は PostgreSQL、テストは DB 名に `_test` を付けた別の DB を使う設定でした。MySQL の `abr` に変えて、テストも `abr` を使うようにしました。Docker の PostgreSQL を立てる `compose.yaml` も作られたので、消しました（`AGENTS.md`・Stimulus の見本も消した）
- **`doctrine:schema:update --dump-sql` を流すと、ほかのアプリの表を消す SQL が出る**：表の定義に合わせて、CHAR 型（`fixed`）と既定値（`rsdt2_id` の `''`）をエンティティに書きました。それでも、Rails・Prisma の管理表を消す SQL は出るので、コマンドごと止めています
- **ステートレスの CSRF は、テストで `Origin` ヘッダーを付けないと通らない**：ブラウザは自動で付けますが、テストのリクエストには無いので、自分で付けました。別のサイトの `Origin` で送ると更新・削除されないことも、テストで確かめています
- **composer は、依存しているパッケージに新しい版を選ぶ**：指定した版は 2 週間以上前のものでも、`doctrine/dbal` などが数日前の版になりました。`composer update --with パッケージ:版` で古い版に下げました
- **Tailwind の設定を足したあと、キャッシュを消すまで読まれなかった**：`tailwind:build` が「版が無い」と言い続けたので、`cache:clear` をしました。`tailwind:init` は対話でしか動かないので、`config/packages/symfonycasts_tailwind.yaml` と `assets/styles/app.css` を手で書きました
- **Tailwind はビルドした時点のテンプレートしか見ない**：テンプレートを書く前にビルドしたら、クラスが CSS に入っていませんでした。テンプレートを変えたら、ビルドし直します（`--watch` を付けると自動）
- **PHP CS Fixer は、説明の最後に「.」を足す**：`@Symfony` の決まり（`phpdoc_summary`）が日本語の文にも「.」を付けるので、この決まりだけ外しました
- **`php -S` だけで JS・CSS も配れた**：開発中の AssetMapper は、ファイルが無い URL を Symfony が受けて返すので、ルーター用のスクリプトは要りませんでした
- **Twig の `?:` と三項演算子を1つの式にまとめたら、お知らせが出なかった**：`{% if %}` に分けて書き直したら出ました。式がどう解釈されたのかは、調べていません
- **データの性質**（ほかの版と同じ）：丁目は「一丁目」と「３丁目」の書き方が混ざっていて、「3」で「３丁目」は見つかりますが、「三丁目」は見つかりません。画面の項目名は CSV の列名のままです
