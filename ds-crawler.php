<?php
/**
 * @var Exception[] $sasomangeErrors;
 * @var EntityManager $entityManager
 */

use Doctrine\ORM\EntityManager;
use Idealogica\NsCrawler\Messenger\TelegramServerOfferMessenger;
use Idealogica\NsCrawler\Source\MevspaceServerOfferSource;
use Idealogica\NsCrawler\Source\PsychzServerOfferSource;
use Idealogica\NsCrawler\Source\ScalewayServerOfferSource;

require_once 'bootstrap.php';
require_once 'config-ds.php';

$silent = $argv[1] ?? null;

$running = exec("ps aux | grep " . basename(__FILE__) . " | grep -v grep | wc -l");
if ($running > 1) {
    if (! $silent) {
        echo PHP_EOL . "ALREADY RUNNING" . PHP_EOL;
    }
    exit(0);
}

$mevspaceErrors = [];
$scalewayErrors = [];
$psychzErrors = [];

// parsing

$scalewaySource = new ScalewayServerOfferSource($entityManager);
$scalewayServerOffers = $scalewaySource->fetchItems($scalewayErrors);

$mevspaceServerOffers = [];
//$mevspaceSource = new MevspaceServerOfferSource($entityManager);
//$mevspaceServerOffers = $mevspaceSource->fetchItems($mevspaceErrors);

$psychzServerOffers = [];
//$psychzSource = new PsychzServerOfferSource($entityManager);
//$psychzServerOffers = $psychzSource->fetchItems($psychzErrors);

$serverOffers = array_merge($scalewayServerOffers, $mevspaceServerOffers, $psychzServerOffers);

if (! $serverOffers) {
    if (! $silent) {
        echo PHP_EOL . "NO NEW SERVER OFFERS :]" . PHP_EOL;
    }
    exit (0);
}

// messaging

$telegramServerOfferMessenger = new TelegramServerOfferMessenger(
    $entityManager,
    TG_API_TOKEN,
    TG_BOT_NAME,
    TG_CHAT_ID
);
$telegramServerOfferMessenger->sendItems($serverOffers);

// error handling

$errorText = '';

foreach ($scalewayErrors as $error) {
    $errorText .= PHP_EOL . ScalewayServerOfferSource::SOURCE_NAME . ' > ' . $error->getMessage();
}
foreach ($mevspaceErrors as $error) {
    $errorText .= PHP_EOL . MevspaceServerOfferSource::SOURCE_NAME . ' > ' . $error->getMessage();
}
foreach ($psychzErrors as $error) {
    $errorText .= PHP_EOL . PsychzServerOfferSource::SOURCE_NAME . ' > ' . $error->getMessage();
}

if ($errorText) {
    echo $errorText;
    file_put_contents('ds-crawler.log', $errorText);
}

if (! $silent) {
    echo PHP_EOL . "OK" . PHP_EOL;
}
exit (0);
