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

class SasomangePropertySource extends AbstractSource
{
    use NetworkClientTrait;

    const SOURCE_NAME = 'sasomange.rs';

    const INDEX_URL = 'https://sasomange.rs/c/stanovi-iznajmljivanje?productsFacets.facets=location%3Anovi-sad-opstina-novi-sad-podbara%2Clocation%3Anovi-sad-opstina-novi-sad-centar%2Clocation%3Anovi-sad-opstina-novi-sad-liman-1%2Clocation%3Anovi-sad-opstina-novi-sad-liman-2%2Clocation%3Anovi-sad-opstina-novi-sad-liman-3%2Cstatus%3AACTIVE%2Cflat_furnished_to_rent%3AName%25C5%25A1teno%2Cflat_furnished_to_rent%3APoluname%25C5%25A1teno%2CpriceValue%3A%28%2A-900%29%2Cfacility_area_range_flat_rent%3A%2836-%2A%29';

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

        $origin = 'https://sasomange.rs';

        try {
            $dom = $this->parseDom(self::INDEX_URL);
        } catch (Exception $e) {
            $errors[] = $e;
            return [];
        }

        $products = $dom->find('li.product-grid-view');
        if (! $products->count()) {
            $errors[] = new Exception('No products found');
            return [];
        }

        $propertiesCounter = 0;

        foreach ($products as $product) {

            try {

                $property = (new Property())->setSourceName(self::SOURCE_NAME);

                // sku

                $sku = $product->getAttribute('data-sku');
                if (! $sku) {
                    throw new Exception('No sku found');
                }
                $propertyId = trim($sku);

                if (! $this->isHistoryEntryExists(self::SOURCE_NAME, $propertyId)) {

                    $property->setId($propertyId);

                    // link

                    $linkTag = $product->find('a.product-link');
                    if (! $linkTag->count()) {
                        throw new Exception('No link found');
                    }
                    $property->setLink($origin . trim($linkTag[0]->href));

                    // title

                    $titleTag = $product->find('h3.product-title');
                    if (! $titleTag->count()) {
                        throw new Exception('No title found');
                    }
                    $property->setTitle(trim($titleTag[0]->innerHtml));

                    // location

                    $locationTag = $product->find('div.top-section div.pin-item span');

                    if (! $locationTag->count()) {
                        throw new Exception('No location found');
                    }
                    $property->setLocation(trim($locationTag->innerHtml));

                    // attributes

                    $attrsTag = $product->find('ul.highlighted-attributes span');
                    if ($attrsTag[0]) {
                        $property->setRoomsNumber(trim($attrsTag[0]->innerHtml));
                    }
                    if ($attrsTag[1]) {
                        $property->setArea(trim($attrsTag[1]->innerHtml));
                    }
                    if ($attrsTag[2]) {
                        $property->setAgency(trim($attrsTag[2]->innerHtml) === 'Agencija');
                    }
                    if ($attrsTag[3]) {
                        $property->setFurnished(trim($attrsTag[3]->innerHtml) === 'Namešteno');
                    }
                    if ($attrsTag[4]) {
                        $property->setDistrict(trim($attrsTag[4]->innerHtml));
                    }

                    // price

                    $priceTag = $product->find('p.product-price');
                    if (! $priceTag->count()) {
                        throw new Exception('No price found');
                    }
                    if (! preg_match('#^([0-9]+)#', trim($priceTag->innerHtml), $pm)) {
                        throw new Exception('Could not parse price');
                    }
                    $property->setPrice((int) $pm[1]);

                    // description

                    $descriptionTag = $product->find('div.description > p');
                    if (! $descriptionTag->count()) {
                        throw new Exception('No description found');
                    }
                    $property->setDescription(trim($descriptionTag->innerHtml));

                    // date

                    $dateTag = $product->find('div.description > time.pin-item span');
                    if (! $dateTag->count()) {
                        throw new Exception('No date found');
                    }
                    $property->setDate(new DateTime(trim($dateTag->innerHtml)));

                    // images

                    $imgJson = $this->queryJson('https://sasomange.rs/hybris/classified/v1/products/sku/' . $property->getId() . '/images');
                    if (empty($imgJson['images'])) {
                        continue;
                    }
                    $images = [];
                    foreach ($imgJson['images'] as $jsonImage) {
                        if (! empty($jsonImage['url'])) {
                            $images[] = trim($jsonImage['url']);
                        }
                    }
                    $property->setImages($images);

                    // phone numbers

                    $phonesJson = $this->queryJson('https://sasomange.rs/hybris/classified/v1/products/sku/' . $property->getId() . '/phone-numbers');
                    if (empty($phonesJson['phoneNumbers'])) {
                        throw new Exception('No phoneNumbers section inside json');
                    }
                    $property->setPhoneNumbers($phonesJson['phoneNumbers']);

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
