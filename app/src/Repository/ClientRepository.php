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
            ->orWhere('c.client_number = :clientNumber')
            ->setParameter('search', '%' . $search . '%')
            ->setParameter('id', (int) $search)
            ->setParameter('clientNumber', (int) $search)
            ->getQuery()
            ->getResult();
    }

    /**
     * Busca un cliente por su código de tarjeta, DNI/NIF, ID numérico de BD o número de socio.
     * Como card_code está cifrado en BD, cargamos todos y comparamos en PHP.
     */
    public function findOneByCardCode(string $cardCode): ?Client
    {
        $clean = strtoupper(trim($cardCode));
        foreach ($this->findAll() as $client) {
            $clientCard = strtoupper(trim((string) $client->getCardCode()));
            $clientIdNumber = strtoupper(trim((string) $client->getIdNumber()));
            $clientId = (string) $client->getId();
            $clientNumber = (string) $client->getEffectiveClientNumber();

            if ($clientCard === $clean || $clientIdNumber === $clean || $clientId === $clean || $clientNumber === $clean) {
                return $client;
            }
        }
        return null;
    }

    /**
     * Calcula el siguiente número de socio disponible tomando el mayor número actual
     * entre client_number e id, garantizando que sea estrictamente correlativo.
     */
    public function getNextClientNumber(): int
    {
        $conn = $this->getEntityManager()->getConnection();
        $maxNum = $conn->fetchOne('SELECT GREATEST(COALESCE(MAX(client_number), 0), COALESCE(MAX(id), 0)) FROM client');
        return ((int) $maxNum) + 1;
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
