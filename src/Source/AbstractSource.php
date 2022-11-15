<?php
namespace Idealogica\NsCrawler\Source;

use Doctrine\ORM\EntityManager;
use Doctrine\ORM\NonUniqueResultException;
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
     * @param string $source
     * @param string $propertyId
     *
     * @return bool
     * @throws NonUniqueResultException
     */
    protected function isHistoryEntryExists(string $source, string $propertyId): bool
    {
        return (bool) $this->entityManager
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
