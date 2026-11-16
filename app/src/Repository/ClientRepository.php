<?php

namespace App\Repository;

use App\Entity\Client;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Client>
 */
class ClientRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Client::class);
    }

    public function findBySearchTerm(string $search): array
    {
        return $this->createQueryBuilder('c')
            ->where('c.name LIKE :search')
            ->orWhere('c.surname LIKE :search')
            ->orWhere('c.id = :id')
            ->setParameter('search', '%' . $search . '%')
            ->setParameter('id', (int) $search)
            ->getQuery()
            ->getResult();
    }

    /**
     * Busca un cliente por su código de tarjeta, DNI/NIF o ID numérico.
     * Como card_code está cifrado en BD, cargamos todos y comparamos en PHP.
     */
    public function findOneByCardCode(string $cardCode): ?Client
    {
        $clean = strtoupper(trim($cardCode));
        foreach ($this->findAll() as $client) {
            $clientCard = strtoupper(trim((string) $client->getCardCode()));
            $clientIdNumber = strtoupper(trim((string) $client->getIdNumber()));
            $clientId = (string) $client->getId();

            if ($clientCard === $clean || $clientIdNumber === $clean || $clientId === $clean) {
                return $client;
            }
        }
        return null;
    }

    //    /**
    //     * @return Client[] Returns an array of Client objects
    //     */
    //    public function findByExampleField($value): array
    //    {
    //        return $this->createQueryBuilder('c')
    //            ->andWhere('c.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->orderBy('c.id', 'ASC')
    //            ->setMaxResults(10)
    //            ->getQuery()
    //            ->getResult()
    //        ;
    //    }

    //    public function findOneBySomeField($value): ?Client
    //    {
    //        return $this->createQueryBuilder('c')
    //            ->andWhere('c.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->getQuery()
    //            ->getOneOrNullResult()
    //        ;
    //    }
}
