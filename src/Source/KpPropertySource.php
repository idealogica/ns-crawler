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

    const PROPERTIES_LIMIT = 30;

    /**
     * @param string $url
     *
     * @return string
     * @throws Exception
     */
    private function request(string $url): string
    {
        $command = 'curl --compressed --cookie /dev/null -L -A "' . self::USER_AGENT . '" -x ' . PROXY_ADDRESS . ' "' . $url . '" 2> /dev/null';
        $output = [];
        exec($command, $output);
        $res = trim(implode(' ', $output));
        if (! $res) {
            $output = [];
            exec($command, $output);
            $res = trim(implode(' ', $output));
            if (! $res) {
                $output = [];
                exec($command, $output);
                $res = trim(implode(' ', $output));
            }
        }
        if (! $res) {
            throw new \Exception('Empty response: ' . $url);
        }
        if (preg_match('#ErrorUnderConstruction_mainText#', $res)) {
            throw new \Exception('KP returned an error. Probably proxy is bad. ' . $url);
        }

        return $res;
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

        foreach (KP_INDEX_URL as $indexUrl) {

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

                    // filter by link to save proxy traffic

                    if ($this->isFiltered($property->getLink())) {
                        continue;
                    }

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
                            throw new Exception('No title found: ' . $property->getLink());
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

                        // author

                        $author = '';
                        $authorContainerTags = $productDom->find('div[class]');
                        foreach ($authorContainerTags as $authorContainerTag) {
                            if (preg_match('#^UserSummary_userNameHolder#i', $authorContainerTag->getAttribute('class'))) {
                                if (count($authorContainerTag->getChildren()) && $authorContainerTag->getChildren()[1]) {
                                    $author = $authorContainerTag->getChildren()[1]->innerHtml;
                                    break;
                                }
                            }
                        }
                        if (! $author) {
                            throw new Exception('No author found: ' . $property->getLink());
                        }
                        $property->setAuthor($author);

                        // is agency

                        $isAgencyDescription = (bool) preg_match('#registr[a-z]*\s+posrednika#i', $property->getDescription());
                        $isAgencyAuthor = (bool) preg_match('#(trg)|(n\.)|(nekretnine)|(group)|(ns[.\s]*group)#i', $author);
                        $property->setAgency($isAgencyDescription || $isAgencyAuthor);

                        // property text

                        $text = $property->getTitle() . ' ' . $property->getDescription();

                        // floor area

                        if (preg_match('#([0-9]+)\s*m\s#i', $text, $floorAreaMatches) ||
                            preg_match('#([0-9]+)\s*m\s*2#i', $text, $floorAreaMatches) ||
                            preg_match('#([0-9]+)\s*kvadrat#i', $text, $floorAreaMatches) ||
                            preg_match('#([0-9]+)\s*kvm#i', $text, $floorAreaMatches) ||
                            preg_match('#([0-9]+)\s*sqm#i', $text, $floorAreaMatches)
                        ) {
                            $property->setArea($floorAreaMatches[1]);
                        }

                        // district - blacklist

                        if ($this->isFiltered($text)) {
                            continue;
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
                            $property->setPrice(str_replace('.', '', $priceMatches[1]));
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

    /**
     * @param string $text
     *
     * @return bool
     */
    private function isFiltered(string $text): bool
    {
        if (! preg_match('#stan#iu', $text)) {
            return true;
        }
        if (preg_match('#(duplex)|(dupleks)#iu', $text)) {
            return true;
        }
        if (preg_match('#novo[a-z]*[\s\-]+nasel#iu', $text)) {
            return true;
        }
        if (preg_match('#n\.?\s*nasel#iu', $text)) {
            return true;
        }
        if (preg_match('#detelinar[a-z]*#iu', $text)) {
            return true;
        }
        if (preg_match('#veternik#iu', $text)) {
            return true;
        }
        if (preg_match('#(adica)|(adice)#iu', $text)) {
            return true;
        }
        if (preg_match('#rumenack[a-z]*#iu', $text)) {
            return true;
        }
        if (preg_match('#salaj[ck][a-z]*#iu', $text)) {
            return true;
        }
        if (preg_match('#petrovaradin#iu', $text)) {
            return true;
        }
        if (preg_match('#star[a-z]*[\s\-]+majur#iu', $text)) {
            return true;
        }
        if (preg_match('#avijacij[a-z]*#iu', $text)) {
            return true;
        }
        if (preg_match('#(klisi)|(klisa)#iu', $text)) {
            return true;
        }
        if (preg_match('#patrijarh[a-z]*[\s\-]+pavl#iu', $text)) {
            return true;
        }
        if (preg_match('#satelit[a-z]*#iu', $text)) {
            return true;
        }
        if (preg_match('#veterničk[a-z]*[\s\-]+ramp#iu', $text)) {
            return true;
        }
        if (preg_match('#telep[a-z]*#iu', $text)) {
            return true;
        }
        if (preg_match('#somborsk[a-z]*#iu', $text)) {
            return true;
        }
        if (preg_match('#bulevar[a-z]*[\s\-]+evrop#iu', $text)) {
            return true;
        }
        if (preg_match('#tatarsko[a-z]*#iu', $text)) {
            return true;
        }
        if (preg_match('#sremsk[a-z]*#iu', $text)) {
            return true;
        }
        if (preg_match('#du[šs]an[a-z]*#iu', $text)) {
            return true;
        }
        //if (preg_match('#socijalno[a-z]*#iu', $text)) {
        //    return true;
        //}
        if (preg_match('#(sajm)|(sajam)#iu', $text)) {
            return true;
        }
        //if (preg_match('#podbar[a-z]*#iu', $text)) {
        //    return true;
        //}
        if (preg_match('#[zž]elezni[cč]k[a-z]*#iu', $text)) {
            return true;
        }
        if (preg_match('#[zž]\.?\s*stanic[a-z]*#iu', $text)) {
            return true;
        }
        if (preg_match('#futog[a-z]*#iu', $text)) {
            return true;
        }
        if (preg_match('#jugovi[ćc]ev[a-z]*#iu', $text)) {
            return true;
        }
        if (preg_match('#vrdnik#iu', $text)) {
            return true;
        }
        if (preg_match('#ruma#iu', $text)) {
            return true;
        }
        if (preg_match('#temerin#iu', $text)) {
            return true;
        }
        if (preg_match('#sajlov#iu', $text)) {
            return true;
        }
        if (preg_match('#karaga[čc]#iu', $text)) {
            return true;
        }
        if (preg_match('#star[a-z]*\s+pazov#iu', $text)) {
            return true;
        }
        if (preg_match('#mi[šs]eluk#iu', $text)) {
            return true;
        }
        if (preg_match('#avijati[čc]arsko#iu', $text)) {
            return true;
        }
        if (preg_match('#lipov[a-z]*\s+gaj#iu', $text)) {
            return true;
        }
        if (preg_match('#vrbas#iu', $text)) {
            return true;
        }
        if (preg_match('#ba[čc]k[a-z]*\s+palank#iu', $text)) {
            return true;
        }
        if (preg_match('#obla[čc]ic[a-z]*\s+rad#iu', $text)) {
            return true;
        }
        if (preg_match('#futo[šs]k[a-z]*\s+put#iu', $text)) {
            return true;
        }
        if (preg_match('#kopaonik#iu', $text)) {
            return true;
        }
        if (preg_match('#[šs]arengrad#iu', $text)) {
            return true;
        }
        if (preg_match('#sremsk[a-z]*\s+kamenic#iu', $text)) {
            return true;
        }
        if (preg_match('#s\.?\s*kamenic#iu', $text)) {
            return true;
        }

        return false;
    }
}
