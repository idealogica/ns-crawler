<?php
namespace Idealogica\NsCrawler\Source;

use Exception;
use GuzzleHttp\Psr7\Uri;
use Idealogica\NsCrawler\Item\Property;
use Idealogica\NsCrawler\NetworkClientTrait;
use PHPHtmlParser\Dom;
use PHPHtmlParser\Dom\Node\Collection;
use PHPHtmlParser\Exceptions\ChildNotFoundException;
use PHPHtmlParser\Exceptions\NotLoadedException;

class KpPropertySource extends AbstractSource
{
    use NetworkClientTrait;

    const SOURCE_NAME = 'kupujemprodajem.com';

    const INDEX_URL = [
        'https://novi.kupujemprodajem.com/nekretnine-kupoprodaja/stanovi-trosobni-i-veci/pretraga?categoryId=26&groupId=236&locationId=16&priceTo=220000&currency=eur',
        'https://novi.kupujemprodajem.com/nekretnine-kupoprodaja/stanovi-dvoiposobni/pretraga?categoryId=26&groupId=764&locationId=16&priceTo=220000&currency=eur'
    ];

    const PROPERTIES_LIMIT = 30;

    /**
     * @param string $url
     *
     * @return string
     */
    private function request(string $url): string
    {
        exec('curl -x ' . PROXY_ADDRESS . ' "' . $url . '" 2> /dev/null', $output);
        return implode(' ', $output);
    }

    /**
     * @param string $indexUrl
     * @param array $errors
     *
     * @return Collection|null
     * @throws ChildNotFoundException
     * @throws NotLoadedException
     */
    private function getProducts(string $indexUrl, array &$errors = []): ?Collection
    {
        try {
            $dom = new Dom();
            $dom->loadStr($this->request($indexUrl));
        } catch (Exception $e) {
            $errors[] = $e;
            return null;
        }
        return $dom->find('section[id]');
    }

    /**
     * @param array $errors
     *
     * @return array
     * @throws ChildNotFoundException
     * @throws NotLoadedException
     */
    public function fetchItems(array &$errors = []): array
    {
        $errors = [];
        $properties = [];

        $origin = 'https://novi.kupujemprodajem.com';

        foreach (self::INDEX_URL as $indexUrl) {

            $products = $this->getProducts($indexUrl, $errors);
            if (! $products instanceof Collection) {
                return [];
            }
            if (! $products->count()) {
                $errors[] = new Exception('KP: no products found one the page: ' . $indexUrl);
                return [];
            }

            $propertiesCounter = 0;

            foreach ($products as $product) {

                try {

                    $property = (new Property())->setSourceName(self::SOURCE_NAME);

                    // link

                    $linkTag = $product->find('a[role=button]');
                    if (! $linkTag->count()) {
                        throw new Exception('No link found');
                    }
                    $property->setLink($origin . trim($linkTag[0]->getAttribute('href')));

                    // id

                    $uri = new Uri($property->getLink());
                    if (! preg_match('#/([0-9]+)$#', $uri->getPath(), $idMatches)) {
                        throw new Exception('No KP id found for link: ' . $property->getLink());
                    }
                    $propertyId = trim($idMatches[1]);

                    $historyEntry = $this->addHistoryEntry(self::SOURCE_NAME, $propertyId);

                    if ($historyEntry->isReadyForProcessing()) {

                        $property->setId($propertyId);

                        try {
                            $html = $this->request($property->getLink());
                            $productDom = new Dom();
                            $productDom->loadStr($html);
                        } catch (Exception $e) {
                            $errors[] = $e;
                            continue;
                        }

                        // date

                        $property->setDate(new \DateTime());

                        // title

                        $titleTag = $productDom->find('section h1');
                        if (! $titleTag->count()) {
                            throw new Exception('No title found');
                        }
                        $property->setTitle(trim($titleTag[0]->innerHtml));

                        // description

                        $descriptionContainerTags = $productDom->find('section[class]');
                        if (! $descriptionContainerTags->count()) {
                            throw new Exception('No description found: ' . $property->getLink());
                        }
                        $description = '';
                        foreach ($descriptionContainerTags as $descriptionContainerTag) {
                            if (preg_match('#^AdViewDescription_descriptionHolder#i', $descriptionContainerTag->getAttribute('class'))) {
                                $description .= ' ' . trim(strip_tags($descriptionContainerTag->innerHtml));
                            }
                        }
                        if (! $description) {
                            throw new Exception('KP: no description found: ' . $property->getLink());
                        } else {
                            $property->setDescription(trim($description));
                        }

                        // is agency

                        $property->setAgency((bool) preg_match('#registr[a-z]*\s+posrednika#i', $property->getDescription()));

                        // property text

                        $text = $property->getTitle() . $property->getDescription();

                        // floor area

                        if (preg_match('#([0-9]+)m#i', $text, $floorAreaMatches)) {
                            $property->setArea($floorAreaMatches[1]);
                        }

                        // district

                        if (preg_match('#telep|телеп#iu', $text)) {
                            $property->setDistrict('Telep');
                        } elseif (preg_match('#centar|center|star[a-z]*\s+grad|центар|центер|стар[а-я]\s+град#iu', $text)) {
                            $property->setDistrict('Center');
                        } elseif (preg_match('#podbara|подбара#iu', $text)) {
                            $property->setDistrict('Podbara');
                        } elseif (preg_match('#grbavica|грбавица#iu', $text)) {
                            $property->setDistrict('Grbavica');
                        } elseif (preg_match('#adamovićevo|адамовићево#iu', $text)) {
                            $property->setDistrict('Adamovićevo');
                        } elseif (preg_match('#bulevar|булевар#iu', $text)) {
                            $property->setDistrict('Bulevar');
                        } elseif (preg_match('#petrovaradin|петроварадин#iu', $text)) {
                            $property->setDistrict('Petrovaradin');
                        } elseif (preg_match('#spens|спенс#iu', $text)) {
                            $property->setDistrict('Spens');
                        } elseif (preg_match('#sajm|саjм#iu', $text)) {
                            $property->setDistrict('Sajm');
                        }
                        if (! $property->getDistrict()) {
                            $property->setDistrict('-');
                            // continue;
                        } else {
                            $property->setLocation($property->getDistrict());
                        }

                        // price

                        $priceTag = $productDom->find('div[class] > h2');
                        if (! $priceTag->count()) {
                            continue;
                        }
                        preg_match('#([0-9]+\.[0-9]+)#', $priceTag[0]->innerHtml, $priceMatches);
                        if (! empty($priceMatches[1])) {
                            $property->setPrice($priceMatches[1]);
                        }

                        // images

                        $imageTags = $productDom->find('img[alt=slika]');
                        if (! $imageTags->count()) {
                            continue;
                        }
                        $images = [];
                        foreach ($imageTags as $imageTag) {
                            $url = $imageTag->getAttribute('src');
                            if ($url) {
                                $images[] = trim($url);
                            }
                        }
                        $property->setImages($images);

                        // phone number: "ownerPhone":"0648222168"

                        if (preg_match('#"ownerPhone"\s*:\s*"([^"]+)"#i', $html, $phoneMatches)) {
                            $property->setPhoneNumbers([trim($phoneMatches[1])]);
                        }

                        $properties[] = $property;
                    }

                    $propertiesCounter++;
                    if ($propertiesCounter >= self::PROPERTIES_LIMIT) {
                        break;
                    }

                } catch (Exception $e) {
                    $errors[] = $e;
                }

            }

        }

        return $properties;
    }
}
