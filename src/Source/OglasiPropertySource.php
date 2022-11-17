<?php
namespace Idealogica\NsCrawler\Source;

use DateTime;
use Exception;
use GuzzleHttp\Exception\GuzzleException;
use Idealogica\NsCrawler\Item\Property;
use Idealogica\NsCrawler\NetworkClientTrait;
use PHPHtmlParser\Exceptions\ChildNotFoundException;
use PHPHtmlParser\Exceptions\NotLoadedException;
use Psr\Http\Client\ClientExceptionInterface;

class OglasiPropertySource extends AbstractSource
{
    use NetworkClientTrait;

    const SOURCE_NAME = 'oglasi.rs';

    const INDEX_URL = 'https://www.oglasi.rs/nekretnine/izdavanje-stanova/novi-sad/liman-3+liman-2+liman-1+centar-spens+centar-stari-grad+centar+podbara?pr%5Be%5D=900&pr%5Bc%5D=EUR&d%5BOpremljenost%5D%5B0%5D=Delimi%C4%8Dno+name%C5%A1ten&d%5BOpremljenost%5D%5B1%5D=Name%C5%A1ten&d%5BKvadratura%5D%5B0%5D=30&d%5BKvadratura%5D%5B1%5D=40&d%5BKvadratura%5D%5B2%5D=50&d%5BKvadratura%5D%5B3%5D=60&d%5BKvadratura%5D%5B4%5D=70&d%5BKvadratura%5D%5B5%5D=80&d%5BKvadratura%5D%5B6%5D=90&d%5BKvadratura%5D%5B7%5D=100';

    const PROPERTIES_LIMIT = 20;

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
        $properties = [];

        $origin = 'https://oglasi.rs';

        try {
            $dom = $this->parseDom(self::INDEX_URL);
        } catch (Exception $e) {
            $errors[] = $e;
            return [];
        }

        $products = $dom->find('div.advert_list_item_normalan');
        if (! $products->count()) {
            $errors[] = new Exception('No products found');
            return [];
        }

        $propertiesCounter = 0;

        foreach ($products as $product) {

            try {

                $property = (new Property())->setSourceName(self::SOURCE_NAME);

                // link

                $linkTag = $product->find('a.fpogl-list-title');
                if (! $linkTag->count()) {
                    throw new Exception('No link found');
                }
                $property->setLink($origin . trim($linkTag[0]->getAttribute('href')));

                // id

                if (! preg_match('#/([0-9]+-[0-9]+)/#', $property->getLink(), $idMatches)) {
                    throw new Exception('No id found');
                }
                $propertyId = trim($idMatches[1]);


                if (! $this->isHistoryEntryExists(self::SOURCE_NAME, $propertyId)) {

                    $property->setId($propertyId);

                    // date

                    $dateTag = $product->find('time');
                    if (! $dateTag->count() || ! $dateTag[0]->getAttribute('datetime')) {
                        throw new Exception('No date found');
                    }
                    $property->setDate(new \DateTime($dateTag[0]->getAttribute('datetime')));

                    // title

                    $titleTag = $product->find('a.fpogl-list-title h2');
                    if (! $titleTag->count()) {
                        throw new Exception('No title found');
                    }
                    $property->setTitle(trim($titleTag[0]->innerHtml));

                    // district

                    $categoryTags = $product->find('a[itemprop=category]');
                    if ($categoryTags->count() === 4) {
                        $property->setDistrict(trim($categoryTags[3]->innerHtml));
                    }

                    // description

                    $descriptionTag = $product->find('p[itemprop=description]');
                    if (! $descriptionTag->count()) {
                        throw new Exception('No description found');
                    }
                    $property->setDescription(trim($descriptionTag[0]->innerHtml));

                    // price

                    $priceTag = $product->find('span[itemprop=price]');
                    if (! $priceTag->count()) {
                        throw new Exception('No description found');
                    }
                    $property->setPrice(trim($priceTag[0]->getAttribute('content')));

                    // agency

                    $authorTag = $product->find('div.visible-sm small');
                    if (! $authorTag->count()) {
                        throw new Exception('No description found');
                    }
                    $property->setAgency((bool) preg_match('#nekretnine#i', $authorTag[0]->innerHtml));

                    // properties

                    $propertiesTag = $product->find('div.row > div.col-sm-6 strong');
                    if (! $propertiesTag->count()) {
                        throw new Exception('No properties found');
                    }
                    if ($propertiesTag[0]) {
                        $property->setRoomsNumber(trim($propertiesTag[0]->innerHtml));
                    }
                    if ($propertiesTag[1]) {
                        $property->setFurnished(trim($propertiesTag[1]->innerHtml) === 'Namešten');
                    }
                    if ($propertiesTag[2]) {
                        $property->setArea(trim($propertiesTag[2]->innerHtml));
                    }

                    // property page

                    $propertyHtml = $this->query($property->getLink());
                    $dom = $this->parseHtml($propertyHtml);

                    // images

                    $imageTags = $dom->find('figure[data-advert-image-gallery] img');
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

                    // phone number

                    $phoneTag = $dom->find('div.panel-body > div > a');
                    if (! $phoneTag->count()) {
                        throw new Exception('No phone found ' . $property->getLink());
                    }
                    $property->setPhoneNumbers([trim($phoneTag[0]->innerHtml)]);

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

        return $properties;
    }
}
