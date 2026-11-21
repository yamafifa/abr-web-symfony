<?php

namespace App\UseCase;

use App\Repository\RsdtRepository;

/**
 * 住居を削除する。すでに無ければ、消せたものとして扱う（同じ削除を 2 回送られても失敗にしない）
 */
final readonly class DeleteRsdt
{
    public function __construct(private RsdtRepository $rsdts)
    {
    }

    public function call(string $id): void
    {
        $this->rsdts->deleteByRouteId($id);
    }
}
