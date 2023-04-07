<?php
/**
 * @var Exception[] $sasomangeErrors;
 * @var EntityManager $entityManager
 */

use Doctrine\ORM\EntityManager;
use Idealogica\NsCrawler\Messenger\TelegramServerOfferMessenger;
use Idealogica\NsCrawler\Source\MevspaceServerOfferSource;

require_once 'bootstrap.php';

$silent = $argv[1] ?? null;

$running = exec("ps aux | grep " . basename(__FILE__) . " | grep -v grep | wc -l");
if ($running > 1) {
    if (! $silent) {
        echo PHP_EOL . "ALREADY RUNNING" . PHP_EOL;
    }
    exit(0);
}

$mevspaceErrors = [];

// parsing

$mevspaceSource = new MevspaceServerOfferSource($entityManager);
$mevspaceServerOffers = $mevspaceSource->fetchItems($mevspaceErrors);

$serverOffers = array_merge($mevspaceServerOffers, []);

if (! $serverOffers) {
    if (! $silent) {
        echo PHP_EOL . "NO NEW PROPERTIES :]" . PHP_EOL;
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

foreach ($mevspaceErrors as $error) {
    echo PHP_EOL . MevspaceServerOfferSource::SOURCE_NAME . ' > ' . $error->getMessage();
}

if (! $silent) {
    echo PHP_EOL . "OK" . PHP_EOL;
}
exit (0);
