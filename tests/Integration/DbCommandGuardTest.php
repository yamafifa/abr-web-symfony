<?php

namespace App\Tests\Integration;

use PHPUnit\Framework\Attributes\TestWith;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\ConsoleEvents;
use Symfony\Component\Console\Event\ConsoleCommandEvent;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * 本物のコマンドは実行しない（止める仕組みが壊れていたら DB が消えるため）。
 * 同じ名前の「何もしないコマンド」で「コマンドが始まる」イベントだけを送って、止まるかを見る
 */
final class DbCommandGuardTest extends KernelTestCase
{
    private static function start(string $name, BufferedOutput $output): ConsoleCommandEvent
    {
        $event = new ConsoleCommandEvent(new Command($name), new ArrayInput([]), $output);
        self::getContainer()->get(EventDispatcherInterface::class)->dispatch($event, ConsoleEvents::COMMAND);

        return $event;
    }

    #[TestWith(['doctrine:schema:update'])]
    #[TestWith(['doctrine:schema:drop'])]
    #[TestWith(['doctrine:database:drop'])]
    #[TestWith(['doctrine:migrations:migrate'])]
    public function testDBを作り直すコマンドは始まる前に止める(string $name): void
    {
        $output = new BufferedOutput();

        self::assertFalse(self::start($name, $output)->commandShouldRun());
        self::assertStringContainsString("{$name} は実行できません", $output->fetch());
    }

    public function testほかのコマンドは止めない(): void
    {
        self::assertTrue(self::start('debug:router', new BufferedOutput())->commandShouldRun());
    }
}
