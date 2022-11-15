<?php
namespace Idealogica\NsCrawler\Messenger;

use DateTime;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Exception\ORMException;
use Doctrine\ORM\OptimisticLockException;
use Idealogica\NsCrawler\History;

abstract class AbstractMessenger implements MessengerInterface
{
    protected EntityManager $entityManager;

    /**
     * @param EntityManager $entityManager
     */
    public function __construct(EntityManager $entityManager)
    {
        $this->entityManager = $entityManager;
    }

    /**
     * @param string $source
     * @param string $propertyId
     *
     * @throws ORMException
     * @throws OptimisticLockException
     */
    protected function updateHistory(string $source, string $propertyId)
    {
        $test = new History();
        $test
            ->setSource($source)
            ->setSourceId($propertyId)
            ->setInsertedon(new DateTime());
        $this->entityManager->persist($test);
        $this->entityManager->flush();
    }
}
