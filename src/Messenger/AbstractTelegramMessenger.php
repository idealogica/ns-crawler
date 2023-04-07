<?php
namespace Idealogica\NsCrawler\Messenger;

use Doctrine\ORM\EntityManager;
use Longman\TelegramBot\Entities\ServerResponse;
use Longman\TelegramBot\Exception\TelegramException;
use Longman\TelegramBot\Request;

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
