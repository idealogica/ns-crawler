<?php
namespace Idealogica\NsCrawler\Messenger;

interface MessengerInterface
{
    /**
     * @param array $items
     *
     * @return $this
     */
    public function sendItems(array $items): self;
}
