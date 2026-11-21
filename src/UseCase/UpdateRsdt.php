<?php

namespace App\UseCase;

use App\Repository\RsdtRepository;
use Doctrine\ORM\EntityManagerInterface;

/**
 * 住居の主キー以外の列を更新する
 *
 * 入力のチェックは、ここに来る前に Symfony Form（RsdtType）で済ませてある。
 * Laravel 版と同じく、ユースケースは「入力エラー」を返さない。
 */
final readonly class UpdateRsdt
{
    public function __construct(
        private EntityManagerInterface $em,
        private RsdtRepository $rsdts,
    ) {
    }

    /**
     * @param array<string, string|null> $input 列名 → 値。無い列・空文字は NULL として扱う
     */
    public function call(string $id, array $input): UpdateRsdtStatus
    {
        return $this->em->wrapInTransaction(function () use ($id, $input) {
            // 読んでから更新するまでの間に、ほかの画面で消されないように行をロックする（SELECT ... FOR UPDATE）
            $rsdt = $this->rsdts->findByRouteId($id, lock: true);
            if (null === $rsdt) {
                return UpdateRsdtStatus::NotFound;
            }
            $rsdt->update($input);
            // flush は wrapInTransaction が、コミットの前にする

            return UpdateRsdtStatus::Updated;
        });
    }
}
