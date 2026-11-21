[← README に戻る](../README.md)

# サーバーの起動と停止

## 起動

### 1. DB を起動する（abr-batch のフォルダで）

```bash
cd ~/work/abr-batch
npm run db:up
```

すでに動いていれば不要です。Elasticsearch と Kibana も一緒に起動します。

Elasticsearch 検索（`/es`）を使うときは、インデックスを作っておきます（約 1 分。DB を変えたあとに、もう一度実行すると反映されます）。

```bash
npm run es:index
```

### 2. アプリを起動する（abr-web-symfony のフォルダで）

```bash
cd ~/work/abr-web-symfony
php bin/console tailwind:build
php -S 127.0.0.1:3000 -t public
```

ブラウザで http://127.0.0.1:3000 を開きます。

- `php -S` は、PHP に付いている簡単な Web サーバーです。`-t public` で、`public/index.php` を入口にします
- PHP・Twig・JS（`assets/`）を保存すると、ブラウザを再読み込みするだけで反映されます
- **画面に新しい Tailwind のクラスを書いたときは**、`php bin/console tailwind:build` をもう一度実行します
  - 別のターミナルで `php bin/console tailwind:build --watch` を動かしておくと、保存するたびにビルドし直します
- 画面の下に出る黒い帯は、Symfony のツールバー（web-profiler）です。開発中だけ出ます
- Symfony CLI（`symfony server:start`）は入れていません

## 停止

**自分のターミナルで起動した場合**：そのターミナルで **Ctrl + C** を押します。

**バックグラウンドで動いているものや、どこで動いているか分からない場合**：

```bash
P=$(ss -ltnp | grep ':3000 ' | grep -o 'pid=[0-9]*' | cut -d= -f2); [ -n "$P" ] && kill $P
```

3000 番を使っている `php -S` を止めます（Laravel 版の `php artisan serve` と違って、親のプロセスはありません）。

止まったかどうかは、次のコマンドで確かめます。何も表示されなければ止まっています。

```bash
ss -ltn | grep :3000
```

**DB も止めたい場合**（abr-batch のフォルダで）：

```bash
npm run db:down
```

DB を止めてもデータは消えません。

## 注意

- TS 版・Rails 版・Laravel 版も 3000 番ポートを使います。ほかの版が動いていると「Failed to listen on 127.0.0.1:3000 (reason: Address already in use)」で起動できないので、先に止めてください
- DB が止まっていると、画面を開いたときに接続エラーになります
