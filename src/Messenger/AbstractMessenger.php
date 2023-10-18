<?php
namespace Idealogica\NsCrawler\Messenger;

use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Exception\ORMException;
use Doctrine\ORM\NonUniqueResultException;
use Doctrine\ORM\OptimisticLockException;
use Idealogica\NsCrawler\History;

abstract class AbstractMessenger implements MessengerInterface
{
    protected EntityManager $entityManager;

    private ?string $instanceName;

    /**
     * @param EntityManager $entityManager
     * @param string|null $instanceName
     */
    public function __construct(EntityManager $entityManager, ?string $instanceName = null)
    {
        $this->entityManager = $entityManager;
        $this->instanceName = $instanceName;
    }

    /**
     * @param string $source
     * @param string $propertyId
     *
     * @return AbstractMessenger
     * @throws NonUniqueResultException
     * @throws ORMException
     * @throws OptimisticLockException
     */
    protected function updateHistory(string $source, string $propertyId): self
    {
        $propertyId = $this->getInstancePropertyId($propertyId);
        $historyEntry = $this->isHistoryEntryExisting($source, $propertyId);
        if ($historyEntry) {
            $historyEntry->setSentOn(new \DateTime());
            $this->entityManager->persist($historyEntry);
            $this->entityManager->flush();
        }
        return $this;
    }

    /**
     * @param string $source
     * @param string $propertyId
     *
     * @return null|History
     * @throws NonUniqueResultException
     */
    private function isHistoryEntryExisting(string $source, string $propertyId): ?History
    {
        return $this->entityManager
            ->createQueryBuilder()
            ->select('h')
            ->from(History::class, 'h')
            ->where('h.source = ?0')
            ->andWhere('h.sourceId = ?1')
            ->setParameters([$source, $propertyId])
            ->getQuery()
            ->setMaxResults(1)
            ->getOneOrNullResult()
        ;
    }

    /**
     * @param string $propertyId
     *
     * @return string
     */
    private function getInstancePropertyId(string $propertyId): string
    {
        return $this->instanceName ? $this->instanceName . '-' . $propertyId : $propertyId;
    }
}
