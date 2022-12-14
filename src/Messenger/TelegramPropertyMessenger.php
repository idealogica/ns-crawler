<?php
namespace Idealogica\NsCrawler\Messenger;

use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Exception\ORMException;
use Doctrine\ORM\OptimisticLockException;
use Idealogica\NsCrawler\Item\Property;
use Idealogica\NsCrawler\NetworkClientTrait;
use Longman\TelegramBot\Entities\ServerResponse;
use Longman\TelegramBot\Exception\TelegramException;
use Longman\TelegramBot\Request;
use Longman\TelegramBot\Telegram;

class TelegramPropertyMessenger extends AbstractMessenger
{
    use NetworkClientTrait;

    const PHOTO_LIMIT = 6;

    private string $tgApiToken;

    private string $tgBotName;

    private string $tgChatId;

    /**
     * @param EntityManager $entityManager
     * @param string $tgApiToken
     * @param string $tgBotName
     * @param string $tgChatId
     */
    public function __construct(
        EntityManager $entityManager,
        string $tgApiToken,
        string $tgBotName,
        string $tgChatId
    ) {
        parent::__construct($entityManager);
        $this->tgApiToken = $tgApiToken;
        $this->tgBotName = $tgBotName;
        $this->tgChatId = $tgChatId;
    }

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

            // main message

            $this->sendMessage("\xE2\x9D\x97\xF0\x9F\x99\x8C \xF0\x9F\x98\x8B " . $property);

            // message example

            // $this->sendMessage('Zdravo! Is it still available? ' . $property->getLink());

            // phone number and viber link

            foreach ($property->getPhoneNumbers() as $phoneNumber) {
                $phoneNumber = preg_replace('#[^0-9]+#', '', $phoneNumber);
                $phoneCode = substr($phoneNumber, 3);
                if ($phoneCode === '021') {
                    continue; // local phone number
                }
                if ($phoneNumber[0] === '0') {
                    $phoneNumber = substr($phoneNumber, 1);
                    $phoneNumber = '381' . $phoneNumber;
                }
                $phoneNumber = '+' . $phoneNumber;
                $viberLink = 'viber://chat/?number=' . urlencode($phoneNumber);
                $this->sendMessage('[' . $viberLink . '](' . $viberLink . ')');
            }

            // additional images

            $photoCounter = 0;
            foreach ($property->getImages() as $image) {
                try {
                    $this->sendPhoto($image);
                } catch (\Exception $e) {}
                $photoCounter++;
                if ($photoCounter >= self::PHOTO_LIMIT) {
                    break;
                }
            }

            $this->updateHistory($property->getSourceName(), $property->getId());

            sleep(2); // to avoid TG rate limit
        }

        $this->sendMessage("\xE2\x8F\xB3 Waiting for new properties...");

        return $this;
    }

    /**
     * @param string $message
     *
     * @return $this
     * @throws TelegramException
     * @throws \Exception
     */
    private function sendMessage(string $message): self
    {
        return $this->execute(function () use ($message) {
            return Request::sendMessage([
                'chat_id' => $this->tgChatId,
                'text'    => $message,
                'parse_mode' => 'markdown',
                'disable_web_page_preview' => true,
            ]);
        });
    }

    /**
     * @param string $url
     *
     * @return $this
     * @throws \Exception
     */
    private function sendPhoto(string $url): self
    {
        return $this->execute(function () use ($url) {
            return Request::sendPhoto([
                'chat_id' => $this->tgChatId,
                'photo'   => $url,
            ]);
        });
    }

    /**
     * @param \Closure $sendMessage
     *
     * @return $this
     * @throws \Exception
     */
    private function execute(\Closure $sendMessage): self
    {
        /**
         * @var ServerResponse $res
         */
        $res = $sendMessage();
        if (! $res->isOk()) {
            $retryAfter = $res->getRawData()['parameters']['retry_after'] ?? null;
            if ($retryAfter) {
                sleep(2 * $retryAfter);
                $res = $sendMessage();
                if ($res->isOk()) {
                    return $this;
                }
            }
            throw new \Exception('TG: ' . $res->getDescription());
        }
        return $this;
    }
}
