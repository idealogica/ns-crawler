<?php
namespace Idealogica\NsCrawler\Source;

use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Exception\ORMException;
use Doctrine\ORM\NonUniqueResultException;
use Doctrine\ORM\OptimisticLockException;
use Idealogica\NsCrawler\History;
use PHPHtmlParser\Dom;
use PHPHtmlParser\Exceptions\ChildNotFoundException;
use PHPHtmlParser\Exceptions\CircularException;
use PHPHtmlParser\Exceptions\ContentLengthException;
use PHPHtmlParser\Exceptions\LogicalException;
use PHPHtmlParser\Exceptions\StrictException;
use Psr\Http\Client\ClientExceptionInterface;

abstract class AbstractSource implements SourceInterface
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
     * @param string $url
     *
     * @return Dom
     * @throws ChildNotFoundException
     * @throws CircularException
     * @throws ContentLengthException
     * @throws LogicalException
     * @throws StrictException
     * @throws ClientExceptionInterface
     */
    protected function parseDom(string $url): Dom
    {
        $dom = new Dom();
        $dom->loadFromUrl($url);
        return $dom;
    }

    /**
     * @param string $html
     *
     * @return Dom
     * @throws ChildNotFoundException
     * @throws CircularException
     * @throws ContentLengthException
     * @throws LogicalException
     * @throws StrictException
     */
    protected function parseHtml(string $html): Dom
    {
        $dom = new Dom();
        $dom->loadStr($html);
        return $dom;
    }

    /**
     * @param string $source
     * @param string $propertyId
     *
     * @return History
     * @throws ORMException
     * @throws OptimisticLockException
     */
    protected function addHistoryEntry(string $source, string $propertyId): History
    {
        $entry = $this->isHistoryEntryExisting($source, $propertyId);
        if (! $entry) {
            $entry = new History();
            $entry
                ->setSource($source)
                ->setSourceId($propertyId)
                ->setInsertedon(new \DateTime());
            $this->entityManager->persist($entry);
            $this->entityManager->flush();
        }
        return $entry;
    }

    /**
     * @param string $source
     * @param string $propertyId
     *
     * @return null|History
     * @throws NonUniqueResultException
     */
    protected function isHistoryEntryExisting(string $source, string $propertyId): ?History
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
}
