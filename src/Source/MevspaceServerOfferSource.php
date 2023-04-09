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

class MevspaceServerOfferSource extends AbstractSource
{
    use NetworkClientTrait;

    const NOTIFICATION_FREQUENCY = 15;

    const PRICE_OFFSET = 45;

    const SOURCE_NAME = 'mevspace.com';

    const INDEX_URL = 'https://mevspace.com/dedicated';

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
        return $dom->find('li.server-card');
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

        $origin = 'https://mevspace.com';

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

                $priceTags = $product->find('div.price span b');
                if (! $priceTags->count()) {
                    throw new Exception('No mevspace price found');
                }
                if (! preg_match('#([0-9]+\.[0-9]+)#', $priceTags[0]->innerHtml, $priceMatches)) {
                    throw new Exception('Can not parse mevspace price');
                }
                $price = trim($priceMatches[1]);
                if ($price > self::PRICE_OFFSET) {
                    continue;
                }
                $serverOffer->setPrice($price);

                // link

                $linkTag = $product->find('a.btn-success');
                if (! $linkTag->count()) {
                    throw new Exception('No mevspace link found');
                }
                if ($linkTag[0]->hasAttribute('disabled')) {
                    continue;
                }
                $linkValue = trim($linkTag[0]->getAttribute('href'));
                if ($linkValue === '#') {
                    continue;
                }
                $serverOffer->setLink($origin . $linkValue);

                // id

                if (! preg_match('#/([0-9]+)$#', $serverOffer->getLink(), $idMatches)) {
                    throw new Exception('No id found');
                }
                $date = new \DateTime();
                $minute = $date->format('i');
                $minute = $minute - ($minute % self::NOTIFICATION_FREQUENCY);
                $date->setTime($date->format('H'), $minute);
                $propertyId = trim($idMatches[1] . '-' . $date->format('YmdHi'));
                $serverOffer->setId($propertyId);

                $historyEntry = $this->addHistoryEntry(self::SOURCE_NAME, $propertyId);

                if ($historyEntry->isReadyForProcessing()) {

                    // title

                    $titleTag = $product->find('div.cpu');
                    if (! $titleTag->count()) {
                        throw new Exception('No mevspace title found');
                    }
                    if (preg_match('#([^<]+)#', $titleTag[0]->innerHtml, $titleMatches)) {
                        $serverOffer->setTitle(trim($titleMatches[0]));
                    }

                    $serverOffers[] = $serverOffer;
                }

            } catch (Exception $e) {
                $errors[] = $e;
            }

        }

        return $serverOffers;
    }
}
