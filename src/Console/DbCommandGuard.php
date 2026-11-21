<?php

namespace App\Console;

use Symfony\Component\Console\ConsoleEvents;
use Symfony\Component\Console\Event\ConsoleCommandEvent;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

/**
 * DB を作り直す・消すコマンドを止める
 *
 * DB は abr-batch の MySQL を共用しているので、テーブルの作成・変更・削除や、DB の作成・削除をさせない。
 * 例えば doctrine:schema:update は、このアプリの知らないテーブル（Rails・Prisma の管理表）を DROP しようとする。
 * コマンドが始まる前（ConsoleEvents::COMMAND）に名前を見て止める。
 * 名前は省略形（doctrine:sc:up など）を解決したあとのものなので、省略形でも止まる。
 */
#[AsEventListener(event: ConsoleEvents::COMMAND)]
final class DbCommandGuard
{
    /** 止めるコマンド（* はワイルドカード）。doctrine-migrations・fixtures は入れていないが、入れたときのために書いておく */
    public const array BLOCKED = [
        'doctrine:schema:create',
        'doctrine:schema:update',
        'doctrine:schema:drop',
        'doctrine:database:create',
        'doctrine:database:drop',
        'doctrine:migrations:*',
        'doctrine:fixtures:*',
    ];

    public static function blocks(string $command): bool
    {
        foreach (self::BLOCKED as $pattern) {
            if (fnmatch($pattern, $command)) {
                return true;
            }
        }

        return false;
    }

    public function __invoke(ConsoleCommandEvent $event): void
    {
        $name = (string) $event->getCommand()?->getName();
        if (!self::blocks($name)) {
            return;
        }
        $event->getOutput()->writeln(
            "<error>{$name} は実行できません（DB は abr-batch と共用しているため。DB を戻すときは abr-batch で npm run import:v3）</error>"
        );
        // コマンドを動かさずに終わる（終了コードは 113）
        $event->disableCommand();
    }
}
