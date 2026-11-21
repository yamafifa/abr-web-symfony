<?php

/**
 * 動いているサーバー（http://127.0.0.1:3000）に、更新・削除を送って結果を見る（ブラウザが無い環境での確認用）
 *
 * 使い方:
 *   php call-action.php update <id> <back> [列=値 ...]   今の値に、指定した列だけを変えて送る
 *   php call-action.php delete <id> <back>
 *
 * - CSRF は Symfony 8 の既定の「ステートレス」方式。トークンは画面に出ている値（csrf-token）のまま送り、
 *   ブラウザと同じように Origin ヘッダーを付ける（送り元が同じサイトなら通る）
 * - update は、編集画面の今の値を全部送る（指定しなかった列が NULL にならないように）
 * - 入力エラーなら 422 で編集画面がそのまま返るので、エラーの文を出す。redirect なら、その画面のお知らせを出す
 * - 本物の DB を書き換える。確認のあとは、DB を元に戻すこと（SKILL.md「テストと確認」）
 */
const BASE = 'http://127.0.0.1:3000';

[$_, $action, $id, $back] = $argv + [null, null, null, null];
if (!in_array($action, ['update', 'delete'], true) || !$id || null === $back) {
    fwrite(STDERR, "使い方: php call-action.php update|delete <id> <back> [列=値 ...]\n");
    exit(2);
}

$jar = (string) tempnam(sys_get_temp_dir(), 'abr-cookie-');
register_shutdown_function(fn () => @unlink($jar));

/** @return array{int, string, string} HTTP コード・redirect 先・本文 */
function request(string $method, string $url, string $jar, ?array $form = null): array
{
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_COOKIEJAR => $jar,
        CURLOPT_COOKIEFILE => $jar,
        CURLOPT_HTTPHEADER => ['Origin: '.BASE],
    ]);
    if (null !== $form) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($form));
    }
    $body = curl_exec($ch);
    if (false === $body) {
        fwrite(STDERR, '接続できません: '.curl_error($ch)."\nサーバーが動いているか確かめてください（php -S 127.0.0.1:3000 -t public）\n");
        exit(1);
    }
    $info = curl_getinfo($ch);

    return [$info['http_code'], (string) $info['redirect_url'], (string) $body];
}

function decode(string $s): string
{
    return html_entity_decode($s, ENT_QUOTES | ENT_HTML5, 'UTF-8');
}

/** 画面のお知らせ（flash）と、入力欄のエラーの文を出す */
function report(string $html): void
{
    if (preg_match_all('/<div data-controller="flash".*?<\/span>\s*(.*?)\s*<\/div>/s', $html, $m)) {
        foreach ($m[1] as $f) {
            echo 'お知らせ: '.decode(trim($f))."\n";
        }
    }
    if (preg_match_all('/name="rsdt\[([a-z0-9_]+)\]"[^>]*aria-invalid="true".*?<span class="mt-1 block text-xs text-rose-400">(.*?)<\/span>/s', $html, $m, PREG_SET_ORDER)) {
        foreach ($m as [, $name, $msg]) {
            echo "エラー: {$name}: ".decode($msg)."\n";
        }
    }
}

$url = BASE.'/rsdt/'.rawurlencode($id).'?back='.rawurlencode($back);
if ('update' === $action) {
    $form = ['_method' => 'PATCH', 'rsdt' => ['_token' => 'csrf-token']];
    // 編集画面の入力欄（rsdt[列名]）の今の値。行が無ければ、指定した列だけを送る
    [$code, , $html] = request('GET', BASE.'/rsdt/'.rawurlencode($id).'/edit', $jar);
    echo "編集画面: HTTP {$code}\n";
    preg_match_all('/<input type="(?:text|date)"[^>]*>/', $html, $inputs);
    foreach ($inputs[0] as $tag) {
        if (preg_match('/name="rsdt\[([a-z0-9_]+)\]"/', $tag, $n)) {
            $form['rsdt'][$n[1]] = preg_match('/ value="([^"]*)"/', $tag, $v) ? decode($v[1]) : '';
        }
    }
    foreach (array_slice($argv, 4) as $pair) {
        [$name, $value] = explode('=', $pair, 2) + [1 => ''];
        $form['rsdt'][$name] = $value;
    }
} else {
    $form = ['_method' => 'DELETE', '_csrf_token' => 'csrf-token'];
}

[$code, $location, $body] = request('POST', $url, $jar, $form);
echo "HTTP {$code}\n";
if ('' !== $location) {
    echo "redirect: {$location}\n";
    [, , $body] = request('GET', $location, $jar);
}
report($body);
