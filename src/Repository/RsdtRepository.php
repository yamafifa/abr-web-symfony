<?php

namespace App\Repository;

use App\Entity\Rsdt;
use App\Search\RsdtSearchCriteria;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

/**
 * 住居の読み書き（SQL を組み立てるのはここだけ）
 *
 * @extends ServiceEntityRepository<Rsdt>
 */
class RsdtRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Rsdt::class);
    }

    /** URL の id で 1 件取る。無い・正しくない id なら null。$lock が true なら、行をロックする（トランザクションの中で使う） */
    public function findByRouteId(string $id, bool $lock = false): ?Rsdt
    {
        $key = Rsdt::keyFromParam($id);
        if (null === $key) {
            return null;
        }

        return $this->getEntityManager()->find(Rsdt::class, $key, $lock ? LockMode::PESSIMISTIC_WRITE : LockMode::NONE);
    }

    /** 検索条件に合う件数 */
    public function countBy(RsdtSearchCriteria $criteria): int
    {
        // 複合主キーなので COUNT(r) ではなく、NULL の無い列を数える
        return (int) $this->searchQuery($criteria)->select('COUNT(r.lgCode)')->getQuery()->getSingleScalarResult();
    }

    /**
     * 検索条件に合う行を、主キーの順に $offset 件目から $limit 件
     *
     * @return list<Rsdt>
     */
    public function searchPage(RsdtSearchCriteria $criteria, int $offset, int $limit): array
    {
        $qb = $this->searchQuery($criteria);
        foreach (Rsdt::KEY_COLUMNS as $property) {
            $qb->addOrderBy("r.{$property}");
        }

        /* @var list<Rsdt> */
        return $qb->setFirstResult($offset)->setMaxResults($limit)->getQuery()->getResult();
    }

    /** URL の id の行を消す。消した件数（0 か 1）を返す */
    public function deleteByRouteId(string $id): int
    {
        $key = Rsdt::keyFromParam($id);
        if (null === $key) {
            return 0;
        }
        $qb = $this->createQueryBuilder('r')->delete();
        foreach ($key as $property => $value) {
            $qb->andWhere("r.{$property} = :{$property}")->setParameter($property, $value);
        }

        return (int) $qb->getQuery()->execute();
    }

    /**
     * 検索条件で絞り込む。空の条件は使わない
     * 部分一致はインデックスが効かないので、全件を読む（全件 175 万件）
     */
    private function searchQuery(RsdtSearchCriteria $c): QueryBuilder
    {
        $qb = $this->createQueryBuilder('r');
        $like = fn (string $s) => '%'.self::escapeLike($s).'%';
        if ('' !== $c->city) {
            $qb->andWhere('r.city LIKE :city OR r.ward LIKE :city')->setParameter('city', $like($c->city));
        }
        if ('' !== $c->town) {
            $qb->andWhere('r.oazaCho LIKE :town OR r.koaza LIKE :town')->setParameter('town', $like($c->town));
        }
        if ('' !== $c->chome) {
            $qb->andWhere('r.chome LIKE :chome')->setParameter('chome', $like($c->chome));
        }
        // blk_num・rsdt_num は完全一致（値はすべて数字なので、部分一致だと「1」で 10・21 なども出てしまうため）
        if ('' !== $c->blkNum) {
            $qb->andWhere('r.blkNum = :blkNum')->setParameter('blkNum', $c->blkNum);
        }
        if ('' !== $c->rsdtNum) {
            $qb->andWhere('r.rsdtNum = :rsdtNum')->setParameter('rsdtNum', $c->rsdtNum);
        }

        return $qb;
    }

    /** LIKE の特別な文字（% _ \）を、ただの文字として探せるようにする（MySQL の既定のエスケープ文字は \） */
    public static function escapeLike(string $s): string
    {
        return addcslashes($s, '\\%_');
    }
}
