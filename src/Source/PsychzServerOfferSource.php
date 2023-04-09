<?php
namespace Idealogica\NsCrawler\Source;

use Exception;
use GuzzleHttp\Exception\GuzzleException;
use Idealogica\NsCrawler\Item\ServerOffer;
use Idealogica\NsCrawler\NetworkClientTrait;
use PHPHtmlParser\Dom\Node\Collection;
use PHPHtmlParser\Exceptions\ChildNotFoundException;
use PHPHtmlParser\Exceptions\NotLoadedException;
use Psr\Http\Client\ClientExceptionInterface;

class PsychzServerOfferSource extends AbstractSource
{
    use NetworkClientTrait;

    const PRICE_OFFSET = 50;

    const SOURCE_NAME = 'psychz.com';

    const INDEX_URL = 'https://www.psychz.net/dashboard/client/web/order/dedicated-server?filterId=+YiuNTK7dhKovYq/+lBQ4KqmSltPsmcZgiD18j6wgyQ=';

    const ALLOWED_FILTER = 'E3-1230 v3';

    /**
     * @param string $indexUrl
     * @param array $errors
     *
     * @return Collection|null
     * @throws ChildNotFoundException
     * @throws ClientExceptionInterface
     * @throws NotLoadedException
     */
    private function getProducts(string $indexUrl, array &$errors = []): ?Collection
    {
        try {
            $dom = $this->parseDom($indexUrl);
        } catch (Exception $e) {
            $errors[] = $e;
            return null;
        }
        return $dom->find('tr.td-special-offer');
    }

    /**
     * @param array $errors
     *
     * @return array
     * @throws ClientExceptionInterface
     * @throws GuzzleException
     * @throws ChildNotFoundException
     * @throws NotLoadedException
     */
    public function fetchItems(array &$errors = []): array
    {
        $errors = [];
        $serverOffers = [];

        $origin = 'https://www.psychz.com';

        $products = $this->getProducts(self::INDEX_URL, $errors);
        if (! $products instanceof Collection) {
            return [];
        }
        if (! $products->count()) {
            $errors[] = new Exception('No servers found one the page: ' . self::INDEX_URL);
            return [];
        }

        foreach ($products as $product) {

            try {

                $serverOffer = (new ServerOffer())->setSourceName(self::SOURCE_NAME);

                // price

                $priceTags = $product->find('span.price');
                if (! $priceTags->count()) {
                    throw new Exception('No psychz price found');
                }
                $price = trim($priceTags->getAttribute('data-price'));
                if ($price > self::PRICE_OFFSET) {
                    continue;
                }
                $serverOffer->setPrice($price);

                // link

                $linkTag = $product->find('a.test_grid');
                if (! $linkTag->count()) {
                    throw new Exception('No psychz link found');
                }
                $linkValue = trim($linkTag[0]->getAttribute('href'));
                if (preg_match('#contact\.html$#i', $linkValue)) {
                    continue;
                }
                $serverOffer->setLink($origin . $linkValue);

                // id

                if (! preg_match('#/dashboard/client/web/order/plan/([0-9]+)#i', $serverOffer->getLink(), $idMatches)) {
                    throw new Exception('No id found');
                }
                $date = new \DateTime();
                $minute = $date->format('i');
                $minute = $minute - ($minute % 10);
                $date->setTime($date->format('H'), $minute);
                $propertyId = trim($idMatches[1] . '-' . $date->format('YmdHi'));
                $serverOffer->setId($propertyId);

                // title

                $titleTag = $product->find('td');
                if (! $titleTag->count()) {
                    throw new Exception('No psychz title found');
                }
                if (preg_match('#([^<]+)#', $titleTag[0]->innerHtml, $titleMatches)) {
                    $serverOffer->setTitle(trim($titleMatches[0]));
                } else {
                    throw new Exception('Can not parse title');
                }
                if (! preg_match('#' . self::ALLOWED_FILTER . '#i', $serverOffer->getTitle())) {
                    continue;
                }

                $historyEntry = $this->addHistoryEntry(self::SOURCE_NAME, $propertyId);

                if ($historyEntry->isReadyForProcessing()) {
                    $serverOffers[] = $serverOffer;
                }

            } catch (Exception $e) {
                $errors[] = $e;
            }

        }

        return $serverOffers;
    }
}
