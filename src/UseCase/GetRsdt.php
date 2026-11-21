<?php

namespace App\UseCase;

use App\Entity\Rsdt;
use App\Repository\RsdtRepository;

/**
 * 住居を 1 件取る。無ければ null
 */
final readonly class GetRsdt
{
    public function __construct(private RsdtRepository $rsdts)
    {
    }

    public function call(string $id): ?Rsdt
    {
        return $this->rsdts->findByRouteId($id);
    }
}
