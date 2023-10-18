<?php
namespace Idealogica\NsCrawler\Messenger;

use Doctrine\ORM\EntityManager;
use Longman\TelegramBot\Entities\ServerResponse;
use Longman\TelegramBot\Exception\TelegramException;
use Longman\TelegramBot\Request;

/**
 * In order to get the group chat id, do as follows:
 * Add the Telegram BOT to the group.
 * Get the list of updates for your BOT:
 * https://api.telegram.org/bot<YourBOTToken>/getUpdates
 * Ex:
 * https://api.telegram.org/bot123456789:jbd78sadvbdy63d37gda37bd8/getUpdates
 * Look for the "chat" object:
 * {"update_id":8393,"message":{"message_id":3,"from":{"id":7474,"first_name":"AAA"},"chat":{"id":<group_ID>,"title":""},"date":25497,"new_chat_participant":{"id":71,"first_name":"NAME","username":"YOUR_BOT_NAME"}}}
 * This is a sample of the response when you add your BOT into a group.
 * Use the "id" of the "chat" object to send your messages.
 * (If you created the new group with the bot and you only get {"ok":true,"result":[]}, remove and add the bot again to the group)
 */
abstract class AbstractTelegramMessenger extends AbstractMessenger
{
    protected string $tgApiToken;

    protected string $tgBotName;

    protected string $tgChatId;

    /**
     * @param EntityManager $entityManager
     * @param string $tgApiToken
     * @param string $tgBotName
     * @param string $tgChatId
     * @param string|null $instanceName
     */
    public function __construct(
        EntityManager $entityManager,
        string $tgApiToken,
        string $tgBotName,
        string $tgChatId,
        ?string $instanceName = null
    ) {
        parent::__construct($entityManager, $instanceName);
        $this->tgApiToken = $tgApiToken;
        $this->tgBotName = $tgBotName;
        $this->tgChatId = $tgChatId;
    }

    /**
     * @param string $message
     *
     * @return $this
     * @throws TelegramException
     * @throws \Exception
     */
    protected function sendMessage(string $message): self
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
    protected function sendPhoto(string $url): self
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
    protected function execute(\Closure $sendMessage): self
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
