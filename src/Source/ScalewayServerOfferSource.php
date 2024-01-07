<?php
namespace Idealogica\NsCrawler\Source;

use Exception;
use GuzzleHttp\Exception\GuzzleException;
use Idealogica\NsCrawler\Item\ServerOffer;
use Idealogica\NsCrawler\NetworkClientTrait;
use Idealogica\NsCrawler\Settings;

class ScalewayServerOfferSource extends AbstractSource
{
    use NetworkClientTrait;

    const NOTIFICATION_FREQUENCY = 60;

    const PRICE_OFFSET = 50;

    const SOURCE_NAME = 'scaleway.com';

    const SESSION_RENEW_URL = 'https://api.scaleway.com/iam/v1alpha1/jwts/1111d2f9-091a-4c56-b9ce-97abba945581/renew';

    const URLS = [
        'fr-par-2' => 'https://api.scaleway.com/baremetal/v1/zones/fr-par-2/offers?subscription_period=monthly',
        'fr-par-1' => 'https://api.scaleway.com/baremetal/v1/zones/fr-par-1/offers?subscription_period=monthly',
        'nl-ams-1' => 'https://api.scaleway.com/baremetal/v1/zones/nl-ams-1/offers?subscription_period=monthly',
    ];

    const ALLOWED_FILTER = ['EM-A210R-HDD'];

    const PERSISTENT_HEADERS = [
        'user-agent' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/111.0.0.0 Safari/537.36',
        'authority' => 'api.scaleway.com',
        'accept' => 'application/json',
        'accept-language' => 'en-US,en;q=0.9,ru-RU;q=0.8,ru;q=0.7',
        'cache-control' => 'no-cache',
        'origin' => 'https://console.scaleway.com',
        'pragma' => 'no-cache',
        'referer' => 'https://console.scaleway.com/',
        'content-type' => 'application/json; charset=utf-8',
    ];

    /**
     * @param array $errors
     *
     * @return array
     * @throws GuzzleException
     * @throws \Doctrine\ORM\Exception\ORMException
     * @throws \Doctrine\ORM\NonUniqueResultException
     * @throws \Doctrine\ORM\OptimisticLockException
     */
    public function fetchItems(array &$errors = []): array
    {
        $errors = [];
        $serverOffers = [];

        $token = $this->getToken($errors);

        if ($token) {
            foreach (self::URLS as $zoneName => $zoneUrl) {
                $serverOffers = array_merge(
                    $serverOffers,
                    $this->fetchZone($token, $zoneName, $zoneUrl, $errors)
                );
            }
        }

        return $serverOffers;
    }

    /**
     * @return ?Settings
     * @throws \Doctrine\ORM\NonUniqueResultException
     */
    private function getScalewaySetting(): ?Settings
    {
        return $this->entityManager
            ->createQueryBuilder()
            ->select('s')
            ->from(Settings::class, 's')
            ->where('s.name = ?0')
            ->setParameters(['scaleway_token'])
            ->getQuery()
            ->setMaxResults(1)
            ->getOneOrNullResult()
        ;
    }

    /**
     * @param array $errors
     *
     * @return string|null
     * @throws GuzzleException
     * @throws \Doctrine\ORM\Exception\ORMException
     * @throws \Doctrine\ORM\NonUniqueResultException
     * @throws \Doctrine\ORM\OptimisticLockException
     */
    private function getToken(array &$errors = []): ?string
    {
        $setting = $this->getScalewaySetting();
        if (! $setting) {
            $errors[] = new \Exception('No scaleway setting found');;
            return null;
        }
        try {
            $json = $this->queryJson(
                self::SESSION_RENEW_URL,
                'POST',
                [
                    'headers' => self::PERSISTENT_HEADERS,
                    'body' => '{"renew_token":"' . $setting->getValue() . '"}',
                ]
            );
        } catch (Exception $e) {
            $errors[] = new \Exception('Could not renew scaleway token: ' . $e->getMessage());;
            return null;
        }
        $renewToken = $json['renew_token'] ?? null;
        $token = $json['token'] ?? null;
        if (! $renewToken) {
            $errors[] = new \Exception('No renew token found in the scaleway token response: ' . $json);;
            return null;
        }
        if (! $token) {
            $errors[] = new \Exception('No token found in the scaleway token response: ' . $json);;
            return null;
        }
        $this->entityManager->persist($setting->setValue($renewToken));
        $this->entityManager->flush();
        return $token;
    }

    /**
     * @param string $token
     * @param string $url
     * @param array $errors
     *
     * @return array
     * @throws GuzzleException
     */
    private function getProducts(string $token, string $url, array &$errors = []): array
    {
        try {
            $json = $this->queryJson(
                $url,
                'GET',
                [
                    'headers' => array_merge(
                        self::PERSISTENT_HEADERS,
                        [
                            'x-scw-console' => 'v2',
                            'x-session-token' => $token,
                        ]
                    )
                ]
            );
        } catch (Exception $e) {
            $errors[] = $e;
            return [];
        }
        return $json;
    }

    /**
     * @param string $token
     * @param string $zoneName
     * @param string $zoneUrl
     * @param array $errors
     *
     * @return array
     * @throws GuzzleException
     */
    public function fetchZone(string $token, string $zoneName, string $zoneUrl, array &$errors = []): array
    {
        $serverOffers = [];

        $products = $this->getProducts($token, $zoneUrl, $errors);

        if (empty($products['offers'])) {
            $errors[] = new Exception('No servers found: ' . $zoneUrl);
            return [];
        }

        foreach ($products['offers'] as $product) {

            try {

                $serverOffer = (new ServerOffer())->setSourceName(self::SOURCE_NAME);

                // stock

                if (empty($product['stock']) || $product['stock'] === 'empty') {
                    continue;
                }

                // price

                if (empty($product['price_per_month']) || empty($product['price_per_month']['units'])) {
                    throw new Exception('No scaleway price found');
                }
                $price = trim($product['price_per_month']['units'] . '.' . $product['price_per_month']['nanos']);
                $price = (float) $price;
                if ($price > self::PRICE_OFFSET) {
                    continue;
                }
                $serverOffer->setPrice($price);

                // title

                if (empty($product['name'])) {
                    throw new Exception('No scaleway title found');
                }
                $serverOffer->setTitle(trim($product['name']));
                if (! in_array($serverOffer->getTitle(), self::ALLOWED_FILTER)) {
                    continue;
                }

                // link

                $link = sprintf(
                    'https://console.scaleway.com/elastic-metal/servers/create?name=em-happy-bose&offerName=%s&osId=none&pricing=monthly&zone=%s',
                    $serverOffer->getTitle(),
                    $zoneName
                );
                $serverOffer->setLink($link);

                // id

                if (empty($product['id'])) {
                    throw new Exception('No scaleway id found');
                }
                $date = new \DateTime();
                $minute = $date->format('i');
                $minute = $minute - ($minute % self::NOTIFICATION_FREQUENCY);
                $date->setTime($date->format('H'), $minute);
                $propertyId = trim($product['id'] . '-' . $date->format('YmdHi'));
                $serverOffer->setId($propertyId);

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
