<?php
namespace Idealogica\NsCrawler\Source;

use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Exception\ORMException;
use Doctrine\ORM\NonUniqueResultException;
use Doctrine\ORM\OptimisticLockException;
use GuzzleHttp\Psr7\Request;
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
    const USER_AGENT = 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10.15; rv:109.0) Gecko/20100101 Firefox/111.0';

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
        $dom->loadFromUrl(
            $url,
            null,
            null,
            new Request('GET', $url, ['User-Agent' => self::USER_AGENT])
        );
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
        $propertyId = $this->getInstancePropertyId($propertyId);
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
