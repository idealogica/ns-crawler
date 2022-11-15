<?php
namespace Idealogica\NsCrawler;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;

trait NetworkClientTrait
{
    /**
     * @param string $url
     * @param string $method
     *
     * @return string
     * @throws GuzzleException
     */
    protected function query(string $url, string $method = 'GET')
    {
        $client = new Client();
        $response = $client->request($method, $url);
        return $response->getBody()->getContents();
    }

    /**
     * @param string $url
     * @param string $method
     *
     * @return mixed|null
     * @throws GuzzleException
     */
    protected function queryJson(string $url, string $method = 'GET')
    {
        $content = $this->query($url, $method);
        if ($content) {
            $contentJson = json_decode($content, true);
            if ($contentJson) {
                return $contentJson;
            }
        }
        return null;
    }
}
