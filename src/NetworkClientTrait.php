<?php
namespace Idealogica\NsCrawler;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;

trait NetworkClientTrait
{
    /**
     * @param string $url
     * @param string $method
     * @param array $options
     *
     * @return string
     * @throws GuzzleException
     */
    protected function query(string $url, string $method = 'GET', array $options = [])
    {
        $client = new Client();
        $response = $client->request($method, $url, $options);
        return $response->getBody()->getContents();
    }

    /**
     * @param string $url
     * @param string $method
     * @param array $options
     *
     * @return mixed|null
     * @throws GuzzleException
     */
    protected function queryJson(string $url, string $method = 'GET', array $options = [])
    {
        $content = $this->query($url, $method, $options);
        if ($content) {
            $contentJson = json_decode($content, true);
            if ($contentJson) {
                return $contentJson;
            }
        }
        return null;
    }
}
