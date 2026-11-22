---
name: abr-web-symfony
description: abr-web-symfony（住居表示-住居マスターを検索・一覧・編集・削除する PHP Symfony のアプリ）で、機能の追加・修正・テスト・動作確認・ドキュメント更新をするときの手順と、これまでに分かった落とし穴。検索条件や画面の追加、Entity・Repository・Form・ユースケース・コントローラーのどこに書くかの判断、composer のパッケージや importmap の追加、DB に関わる bin/console コマンドを使うときに使う。
---

# abr-web-symfony の作業手順と知見

## 前提

- PHP 8.5.13（phpenv の global）＋ Composer 2.11.2 ＋ Symfony 8.1.11 ＋ Doctrine ORM 3.7.6（MySQL）＋ Twig ＋ Symfony Form ＋ Tailwind CSS 4.3.4（symfonycasts/tailwind-bundle）＋ Stimulus 3.2.2（AssetMapper）＋ PHPUnit 13.4.6
- DB は **abr-batch の MySQL を共用**（ポート 3307、テーブル `mt_rsdtdsp_rsdt`、1,752,573 件）。開発とテストは同じ DB（`abr`）
- 勉強用に、同じものを TS（abr-web-ts）・Rails（abr-web-rails）・Laravel（abr-web-laravel）でも作った。機能・画面・デザイン・ユースケースの名前（`SearchRsdt`・`GetRsdt`・`UpdateRsdt`・`DeleteRsdt`）をそろえる
- サーバーは `php -S 127.0.0.1:3000 -t public`（ほかの版と同じ 3000 番）。Node.js は使わない
- 時刻は日本時間（`src/Kernel.php` の `boot()` で `date_default_timezone_set('Asia/Tokyo')`）。php.ini の `date.timezone` は UTC のまま（PC 全体の設定なので変えない）

## してはいけないこと（DB を共用しているため）

- **DB を作り直すコマンドを実行しない。** `doctrine:schema:create`・`doctrine:schema:update`・`doctrine:schema:drop`・`doctrine:database:create`・`doctrine:database:drop` など
  - `doctrine:schema:update` は、このアプリの知らないテーブル（`_prisma_migrations`・`ar_internal_metadata`・`schema_migrations`）を DROP しようとする
  - [src/Console/DbCommandGuard.php](../../../src/Console/DbCommandGuard.php) が、コマンドが始まる前に名前を見て止める（終了コード 113）。省略形も止まる
  - 止まるかを確かめるために、本物を実行してはいけない（止める仕組みが壊れていれば DB が消える）。確かめるときは `php bin/phpunit --filter DbCommandGuard`（「コマンドが始まる」イベントだけを送る）
- **doctrine/doctrine-migrations-bundle・doctrine/doctrine-fixtures-bundle を入れない。** 入れるならユーザーの了解を取る（止める名前には入れてある）
- `config/packages/doctrine.yaml` の `when@test` に `dbname_suffix` を戻さない（テストが `abr_test` を探す）
- エンティティの対応づけを直したら、`php bin/console doctrine:schema:validate --skip-sync` と、SQL を出すだけの `doctrine:schema:update --dump-sql` で差を見たくなるが、後者は止めてある。差を見るときは、表の定義（`SHOW CREATE TABLE`）とエンティティを見比べる

## 書く場所の決まり

| 場所 | 書くもの |
|---|---|
| `src/Entity/Rsdt.php` | 列の対応づけ（`#[ORM\Column]`）、複合主キー（`KEY_COLUMNS`・`keyFromParam`・`routeId`）、17 列の決まり（`ATTRIBUTE_RULES`）、住所の表示（`address()`）、フォームに出す値（`formValues()`）、書き換え（`update()`） |
| `src/Repository/RsdtRepository.php` | SQL（QueryBuilder）を組み立てる所。検索・件数・1 件（ロック付き）・削除 |
| `src/Search/` | 検索条件と、一覧の URL ⇔ 状態の変換（readonly class）。Laravel 版と同じもの |
| `src/Form/RsdtType.php` | 編集フォームと入力チェック（`ATTRIBUTE_RULES` から作る）とエラーの文 |
| `src/UseCase/` | ユースケース（`call` を持つクラス）。Repository と EntityManager を引数で受け取る（autowiring） |
| `src/Controller/RsdtController.php` | ルート（`#[Route]`）、パラメーターを読む → ユースケースを呼ぶ → 表示・redirect だけ |
| `templates/`・`assets/controllers/` | Twig と Stimulus |

- エンティティの列は `public private(set)`。テンプレートからは `rsdt.city` のように読める。書き換えは `update()` だけ
- フォームのデータは配列で、エンティティに直接つながない（入力チェックに通ったときだけ `UpdateRsdt` が書き換える）
- TextType は最初から、前後の空白を取り、空文字を null にする。`Rsdt::update()` でも同じことをしている（ユースケースを直接呼ぶときのため）
- 入力エラーのときは、redirect せずに編集画面を 422 で出す（Symfony のふつうの形）

### 検索条件を足すとき

1. `src/Search/RsdtSearchCriteria.php` の項目
2. `src/Repository/RsdtRepository.php` の `searchQuery`（部分一致は `self::escapeLike` でエスケープ）
3. `src/Search/RsdtListQuery.php` の `PARAM_NAMES`（URL の名前）と `MAX_LENGTH`
4. `templates/rsdt/_search_form.html.twig` の `fields`
5. テスト（`tests/Unit/RsdtListQueryTest.php`・`tests/Integration/SearchRsdtTest.php`・`tests/Controller/RsdtControllerTest.php`）と README

部分一致か完全一致かは、値を見てから決める（`blk_num`・`rsdt_num` は全件が半角数字なので完全一致）。

## テストと確認

```bash
php bin/phpunit                        # 78 件、約 20 秒
vendor/bin/phpstan analyse             # レベル 8、エラー 0 件を保つ
vendor/bin/php-cs-fixer fix --dry-run  # 指摘 0 件を保つ。直すときは --dry-run を外す
php bin/console lint:twig templates    # Twig の文法
```

- DB を使うテストは、1 件ごとに dama/doctrine-test-bundle がトランザクションで取り消す（`phpunit.dist.xml` の extension）
- テストで使う行と関数は [tests/Support/RsdtFixtures.php](../../../tests/Support/RsdtFixtures.php)（`EXISTING_ID`・`MISSING_ID`・`TOTAL_ROWS`・`findRsdt`・`inputOf`）
- `findRsdt` は Doctrine が覚えている値を使わないように、`clear()` してから読む
- 画面のテストで更新・削除を送るときは、`Origin` ヘッダーを付ける（ステートレスの CSRF のため）。トークンは画面に出ている値（`csrf-token`）のまま送ればよい
- テストは「全件 1,752,573 件」を前提にしている。件数が違うと落ちるので、先に DB を元に戻す
- 確認のあとは、DB が元のままか必ず確かめる。変わっていたら abr-batch で `npm run import:v3`（約 15 秒）で戻す

```bash
docker compose -f ../abr-batch/compose.yaml exec -T db mysql -uabr -pabr abr -e 'select count(*) from mt_rsdtdsp_rsdt; checksum table mt_rsdtdsp_rsdt'
# 元のデータ：1752573 件、チェックサム 967527444
```

### 画面の確認（ブラウザが無い環境）

- 一覧・編集画面は `curl` で HTML を取って、件数や値を `grep` する
- 更新・削除は [scripts/call-action.php](scripts/call-action.php) で送る。編集画面から今の値を取り、指定した列だけを変えて送る。お知らせと入力エラーの文も出す

```bash
php .claude/skills/abr-web-symfony/scripts/call-action.php update 131016_0001001_006_002_ '/?page=2' 'city=' 'efct_date=2026-02-30'
# → HTTP 422、「入力内容を確認してください」と各列のエラー（DB は変わらない）
php .claude/skills/abr-web-symfony/scripts/call-action.php update 131016_0001001_006_999_99999 '/?page=3' 'city=千代田区'
# → 無い行なので「この行はすでに削除されています」（DB は変わらない）
```

- 成功する更新・削除は本物の DB を書き換える。送ったら DB を元に戻す
- 見た目・モーダルの動きは確かめられないので、「確かめていない」とユーザーに伝える

### サーバーの起動と停止

```bash
php bin/console tailwind:build                  # Tailwind のクラスを足したら、実行し直す
php -S 127.0.0.1:3000 -t public
P=$(ss -ltnp | grep ':3000 ' | grep -o 'pid=[0-9]*' | cut -d= -f2); [ -n "$P" ] && kill $P   # 止める
```

- `pkill -f "php -S"` は使わない。そのコマンドを実行しているシェル自身にも当たることがある（TS 版で、シェルごと止まった）
- 3000 番がほかの版に使われていると「Failed to listen on 127.0.0.1:3000 (reason: Address already in use)」で起動できない。先に `ss -ltn | grep :3000` で確かめる
- PHP・Twig・JS の変更は、サーバーを起動し直さなくても反映される。設定（`config/`）を変えて動きがおかしいときは `php bin/console cache:clear`

## 分かっている落とし穴

| 症状 | 原因と対処 |
|---|---|
| `tailwind:build` が「binary_version を書け」と言い続ける | 設定を足す前のキャッシュが残っていた。`php bin/console cache:clear` |
| `tailwind:init` が動かない | 対話でしか動かない。設定（`config/packages/symfonycasts_tailwind.yaml`）と `assets/styles/app.css` は手で書いた |
| 新しく書いた Tailwind のクラスが効かない | ビルドした時点のテンプレートしか見ない。`tailwind:build` をやり直す |
| テストの更新・削除が 422 やエラーのお知らせになる | ステートレスの CSRF。`Origin` ヘッダーが無いと通らない |
| `find()` に null のロックを渡すと非推奨の警告 | Doctrine 3.7 から。`LockMode::NONE` を渡す |
| `COUNT(r)` が使いにくい | 複合主キーなので、NULL の無い列（`COUNT(r.lgCode)`）を数える |
| PHP CS Fixer が日本語の説明の最後に「.」を足す | `phpdoc_summary` を `.php-cs-fixer.dist.php` で外してある |
| テストのメソッド名の `_` が直される | `php_unit_method_casing` が camelCase にする。名前に `_` を使わない（`testBlkNumと…`） |
| Twig の `?:` と三項演算子を1つの式にまとめたら、お知らせが出なかった | 原因は調べていない。`{% if %}` に分けて書く |
| composer で入れると、依存パッケージが数日前の版になる | `composer update --with 名前:版 名前` で下げる。入れたあと composer.lock の `time` を見る |
| 戻り先の URL のテストが合わない | Symfony は、クエリの中の `/` と `?` をエンコードしない。期待値もルーター（`UrlGeneratorInterface`）で作る |
| 削除ボタンを編集フォームの中に置けない | 削除ボタンは自分のフォームを持つので、入れ子になる。編集フォームの外に置いた |

## データの性質

- 主キーの URL の形は、5 列を `_` でつないだもの（例：`131016_0001001_006_001_`）。`rsdt2_id` が空（NULL ではなく `''`）なので末尾が `_`
- 主キー順で全件の先頭の行は `131016_0001001_006_001_`（`EXISTING_ID`）
- `chome` は「一丁目」と「３丁目」が混ざっている。照合順序で全角と半角は同じになるが、漢数字は別
- 部分一致はインデックスが効かず、件数を数えるのに全件を読む

## 書いてはいけないこと

- **列の日本語名を書かない。** 公式の仕様書で確かめられていないので、画面の項目名は CSV の列名のまま。ユーザーが言った呼び方（「市区町村」「町字」「丁目」）は使ってよい
- 実際に測っていない数字や、試していない動きを、確かめたこととして書かない

## パッケージの追加

- 何を入れるか伝えて、了解を取ってから入れる
- composer は `composer require <pkg>:<版>` で版を固定する。JS は `php bin/console importmap:require <pkg>@<版>`
- できれば出てから 2 週間以上たった版にする
- Flex のレシピ（パッケージを入れると設定ファイルを足す仕組み）が `compose.yaml` などを足すことがある。何が増えたか `git status` で見る
- contrib のレシピは動かない設定（`allow-contrib: false`）。dama/doctrine-test-bundle は、バンドルの登録と PHPUnit の extension を手で書いた

## ドキュメントとコミット

- README は入口（できること・動かし方・構成・テスト・作ってみて分かったこと）。起動と停止は `docs/start-stop.md`
- 仕様を変えたら、README の「できること」「作ってみて分かったこと」も直す。ほかの版とそろえる機能なら、違いも書く
- コミットと push は、ユーザーの了解を取ってから。メッセージは日本語で、最後に `Co-Authored-By` の行を付ける
- GitHub は非公開リポジトリにする（ほかの版と同じ）
- `vendor/`・`var/`・`assets/vendor/`・`public/assets/`・`.env.local` は Git に入れない（`.gitignore` 済み）。コミットの前に、個人情報（本名・メールアドレスなど）が入っていないか確かめる
