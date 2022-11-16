<?php
/**
 * @var Exception[] $sasomangeErrors;
 * @var EntityManager $entityManager
 */

use Doctrine\ORM\EntityManager;
use Idealogica\NsCrawler\Messenger\TelegramPropertyMessenger;
use Idealogica\NsCrawler\Source\OglasiPropertySource;
use Idealogica\NsCrawler\Source\SasomangePropertySource;

require_once 'bootstrap.php';

$silent = $argv[1] ?? null;

$running = exec("ps aux | grep " . basename(__FILE__) . " | grep -v grep | wc -l");
if ($running > 1) {
    if (! $silent) {
        echo PHP_EOL . "ALREADY RUNNING" . PHP_EOL;
    }
    exit(0);
}

$oglasiErrors = [];
$sasomangeErrors = [];

// parsing

$oglasiSource = new OglasiPropertySource($entityManager);
$oglasiProperties = $oglasiSource->fetchItems($oglasiErrors);

$sasomangeSource = new SasomangePropertySource($entityManager);
$sasomangeProperties = $sasomangeSource->fetchItems($sasomangeErrors);

$properties = array_merge($oglasiProperties, $sasomangeProperties);

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
