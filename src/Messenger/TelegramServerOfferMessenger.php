<?php
namespace Idealogica\NsCrawler\Messenger;

use Doctrine\ORM\Exception\ORMException;
use Doctrine\ORM\OptimisticLockException;
use Idealogica\NsCrawler\Item\Property;
use Longman\TelegramBot\Exception\TelegramException;
use Longman\TelegramBot\Request;
use Longman\TelegramBot\Telegram;

class TelegramServerOfferMessenger extends AbstractTelegramMessenger
{
    /**
     * @param Property[] $items
     *
     * @return $this
     * @throws ORMException
     * @throws OptimisticLockException
     * @throws TelegramException
     */
    public function sendItems(array $items): self
    {
        Request::initialize(new Telegram($this->tgApiToken, $this->tgBotName));

        foreach ($items as $property) {

            $this->sendMessage("\xE2\x9D\x97\xF0\x9F\x99\x8C \xF0\x9F\x98\x8B " . $property);

            $this->updateHistory($property->getSourceName(), $property->getId());

            sleep(2); // to avoid TG rate limit
        }

        $this->sendMessage("\xE2\x8F\xB3 Waiting for new server offer...");

        return $this;
    }
}
