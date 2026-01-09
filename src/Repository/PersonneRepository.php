<?php

namespace App\Repository;

use App\Entity\Personne;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Personne>
 *
 * @method Personne|null find($id, $lockMode = null, $lockVersion = null)
 * @method Personne|null findOneBy(array $criteria, array $orderBy = null)
 * @method Personne[]    findAll()
 * @method Personne[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class PersonneRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Personne::class);
    }

    public function add(Personne $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(Personne $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * Retourne les générations distinctes (numéro + nom) présentes en base.
     *
     * @return array<int, array{generationNumber: int, generationName: ?string}>
     */
    public function findDistinctGenerations(): array
    {
        // Récupère les générations existantes depuis la table generation
        // ou à défaut depuis les personnes (pour compatibilité rétro)
        $qb = $this->createQueryBuilder('p')
            ->select('DISTINCT p.generationNumber AS generationNumber, p.generationName AS generationName')
            ->where('p.generationNumber IS NOT NULL')
            ->orderBy('p.generationNumber', 'ASC');

        return $qb->getQuery()->getArrayResult();
    }

    /**
     * Retourne le numéro de génération maximum présent en base (ou 0 si aucune).
     */
    public function findMaxGenerationNumber(): int
    {
        $qb = $this->createQueryBuilder('p')
            ->select('MAX(p.generationNumber) as maxGen');

        $res = $qb->getQuery()->getSingleScalarResult();

        return $res !== null ? (int) $res : 0;
    }

//    /**
//     * @return Personne[] Returns an array of Personne objects
//     */
//    public function findByExampleField($value): array
//    {
//        return $this->createQueryBuilder('p')
//            ->andWhere('p.exampleField = :val')
//            ->setParameter('val', $value)
//            ->orderBy('p.id', 'ASC')
//            ->setMaxResults(10)
//            ->getQuery()
//            ->getResult()
//        ;
//    }

//    public function findOneBySomeField($value): ?Personne
//    {
//        return $this->createQueryBuilder('p')
//            ->andWhere('p.exampleField = :val')
//            ->setParameter('val', $value)
//            ->getQuery()
//            ->getOneOrNullResult()
//        ;
//    }
}
