<?php
/**
 * @var Exception[] $sasomangeErrors;
 * @var EntityManager $entityManager
 */

use Doctrine\ORM\EntityManager;
use Idealogica\NsCrawler\Messenger\TelegramPropertyMessenger;
use Idealogica\NsCrawler\Source\KpPropertySource;
use Idealogica\NsCrawler\Source\OglasiPropertySource;
use Idealogica\NsCrawler\Source\SasomangePropertySource;

require_once 'bootstrap.php';
require_once 'config-ns.php';

$silent = $argv[1] ?? null;

$running = exec("ps aux | grep " . basename(__FILE__) . " | grep -v grep | wc -l");
if ($running > 1) {
    if (! $silent) {
        echo PHP_EOL . "ALREADY RUNNING" . PHP_EOL;
    }
    exit(0);
}

$kpErrors = [];
$oglasiErrors = [];
$sasomangeErrors = [];

// parsing

$kpSource = new KpPropertySource($entityManager);
$kpProperties = $kpSource->fetchItems($kpErrors);

$oglasiSource = new OglasiPropertySource($entityManager);
$oglasiProperties = $oglasiSource->fetchItems($oglasiErrors);

$sasomangeProperties = [];
// $sasomangeSource = new SasomangePropertySource($entityManager);
// $sasomangeProperties = $sasomangeSource->fetchItems($sasomangeErrors);

$properties = array_merge($kpProperties, $oglasiProperties, $sasomangeProperties);

if (! $properties) {
    if (! $silent) {
        echo PHP_EOL . "NO NEW PROPERTIES :]" . PHP_EOL;
    }
    exit (0);
}

// messaging

$telegramPropertyMessenger = new TelegramPropertyMessenger(
    $entityManager,
    TG_API_TOKEN,
    TG_BOT_NAME,
    TG_CHAT_ID
);
$telegramPropertyMessenger->sendItems($properties);

// error handling

foreach ($kpErrors as $error) {
    echo PHP_EOL . KpPropertySource::SOURCE_NAME . ' > ' . $error->getMessage();
}
foreach ($oglasiErrors as $error) {
    echo PHP_EOL . OglasiPropertySource::SOURCE_NAME . ' > ' . $error->getMessage();
}
foreach ($sasomangeErrors as $error) {
    echo PHP_EOL . SasomangePropertySource::SOURCE_NAME . ' > ' . $error->getMessage();
}

if (! $silent) {
    echo PHP_EOL . "OK" . PHP_EOL;
}
exit (0);
