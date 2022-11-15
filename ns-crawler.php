<?php
/**
 * @var Exception[] $sasomangeErrors;
 * @var EntityManager $entityManager
 */

use Doctrine\ORM\EntityManager;
use Idealogica\NsCrawler\Messenger\TelegramPropertyMessenger;
use Idealogica\NsCrawler\Source\SasomangePropertySource;

include 'bootstrap.php';

$silent = $argv[1] ?? null;

$sasomangeErrors = [];
$sasomangeSource = new SasomangePropertySource($entityManager);
$properties = $sasomangeSource->fetchItems($sasomangeErrors);

if (! $properties) {
    if (! $silent) {
        echo PHP_EOL . "NO NEW PROPERTIES :]" . PHP_EOL;
    }
    exit (0);
}

$telegramPropertyMessenger = new TelegramPropertyMessenger(
    $entityManager,
    TG_API_TOKEN,
    TG_BOT_NAME,
    TG_CHAT_ID
);
$telegramPropertyMessenger->sendItems($properties);

foreach ($sasomangeErrors as $error) {
    echo PHP_EOL . SasomangePropertySource::SOURCE_NAME . ' > ' . $error->getMessage();
}

if (! $silent) {
    echo PHP_EOL . "OK" . PHP_EOL;
}
exit (0);
