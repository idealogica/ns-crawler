<?php
/**
 * @var Exception[] $sasomangeErrors;
 * @var EntityManager $entityManager
 */

use Doctrine\ORM\EntityManager;
use Idealogica\NsCrawler\Item\Property;
use Idealogica\NsCrawler\Messenger\TelegramPropertyMessenger;
use Idealogica\NsCrawler\Source\KpPropertySource;
use Idealogica\NsCrawler\Source\OglasiPropertySource;

require_once 'bootstrap.php';

$silent = false;
$instanceName = null;
$command = $argv[0] ?? null;
if (! empty($argv[1])) {
    if ($argv[1] === 'silent') {
        $silent = true;
        $instanceName = $argv[2] ?? null;
    } else {
        $instanceName = $argv[1];
    }
}

$procName = implode(' ', $argv);
$running = exec('ps aux | grep "' . $procName . '" | grep -v grep | wc -l');
if ($running > 1) {
    if (! $silent) {
        echo PHP_EOL . "ALREADY RUNNING" . PHP_EOL;
    }
    exit(0);
}

require_once  'config-ns.' . ($instanceName ? $instanceName . '.' : '') . 'php';

$kpErrors = [];
$oglasiErrors = [];
// $sasomangeErrors = [];

// parsing

$kpProperties = [];
if (KP_INDEX_URL) {
    if (! $silent) {
        echo PHP_EOL . "PARSING KP: " . json_encode(KP_INDEX_URL) . PHP_EOL;
    }
    $kpSource = new KpPropertySource($entityManager, $instanceName);
    $kpProperties = $kpSource->fetchItems($kpErrors);
}

$oglasiProperties = [];
if (OGLASI_INDEX_URL) {
    if (! $silent) {
        echo PHP_EOL . "PARSING OGLASI: " . json_encode(OGLASI_INDEX_URL) . PHP_EOL;
    }
    $oglasiSource = new OglasiPropertySource($entityManager, $instanceName);
    $oglasiProperties = $oglasiSource->fetchItems($oglasiErrors);
}

// $sasomangeProperties = [];
// $sasomangeSource = new SasomangePropertySource($entityManager);
// $sasomangeProperties = $sasomangeSource->fetchItems($sasomangeErrors);

/**
 * @var Property[] $properties
 */
$properties = array_merge($kpProperties, $oglasiProperties);

if (SQM_PRICE_OFFSET) {
    foreach ($properties as $idx => $property) {
        if (! $property->getAreaNumeric() ||
            ! $property->getPriceNumeric() ||
            $property->getPriceNumeric() / $property->getAreaNumeric() > SQM_PRICE_OFFSET
        ) {
            unset($properties[$idx]);
        }
        if ($property->isAgency()) {
            unset($properties[$idx]);
        }
    }
}

if (! $properties) {
    if (! $silent) {
        echo PHP_EOL . "NO NEW PROPERTIES :]" . PHP_EOL;
    }
    exit (0);
}

// messaging

if (! $silent) {
    echo PHP_EOL . "SENDING TO TG" . PHP_EOL;
}
$telegramPropertyMessenger = new TelegramPropertyMessenger(
    $entityManager,
    TG_API_TOKEN,
    TG_BOT_NAME,
    TG_CHAT_ID,
    $instanceName
);
$telegramPropertyMessenger->sendItems($properties);

// error handling

$errorText = '';

foreach ($kpErrors as $error) {
    $errorText .= PHP_EOL . KpPropertySource::SOURCE_NAME . ' > ' . $error->getMessage();
}
foreach ($oglasiErrors as $error) {
    $errorText .= PHP_EOL . OglasiPropertySource::SOURCE_NAME . ' > ' . $error->getMessage();
}
// foreach ($sasomangeErrors as $error) {
//     $errorText .= PHP_EOL . SasomangePropertySource::SOURCE_NAME . ' > ' . $error->getMessage();
// }

if ($errorText) {
    echo $errorText;
    file_put_contents('ns-crawler.log', $errorText);
}

if (! $silent) {
    echo PHP_EOL . "OK" . PHP_EOL;
}
exit (0);
