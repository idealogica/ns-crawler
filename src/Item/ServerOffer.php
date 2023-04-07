<?php
namespace Idealogica\NsCrawler\Item;

class ServerOffer implements ItemInterface
{
    private string $sourceName;

    private string $id;

    private string $link;

    private float $price;

    private string $title;

    /**
     * @return string
     */
    public function getSourceName(): string
    {
        return $this->sourceName;
    }

    /**
     * @param string $sourceName
     *
     * @return ServerOffer
     */
    public function setSourceName(string $sourceName): ServerOffer
    {
        $this->sourceName = $sourceName;
        return $this;
    }

    /**
     * @return string
     */
    public function getId(): string
    {
        return $this->id;
    }

    /**
     * @param string $id
     *
     * @return ServerOffer
     */
    public function setId(string $id): ServerOffer
    {
        $this->id = $id;
        return $this;
    }

    /**
     * @return string
     */
    public function getLink(): string
    {
        return $this->link;
    }

    /**
     * @param string $link
     *
     * @return ServerOffer
     */
    public function setLink(string $link): ServerOffer
    {
        $this->link = $link;
        return $this;
    }

    /**
     * @return float
     */
    public function getPrice(): float
    {
        return $this->price;
    }

    /**
     * @param float $price
     *
     * @return ServerOffer
     */
    public function setPrice(float $price): ServerOffer
    {
        $this->price = $price;
        return $this;
    }

    /**
     * @return string
     */
    public function getTitle(): string
    {
        return $this->title;
    }

    /**
     * @param string $title
     *
     * @return ServerOffer
     */
    public function setTitle(string $title): ServerOffer
    {
        $this->title = $title;
        return $this;
    }

    /**
     * @return string
     */
    public function __toString()
    {
        $string = sprintf(
            "%s - %s\n\n*ID*: %s\n*Price*: %s\n*Link*: %s",
            $this->getSourceName(),
            $this->prepareMarkdown($this->getTitle()),
            $this->getId(),
            $this->getPrice(),
            $this->getLink()
        );
        return $string;
    }

    /**
     * @param string $markdown
     *
     * @return string
     */
    private function prepareMarkdown(string $markdown): string
    {
        return preg_replace('#[*\[\]_`]#', '', $markdown);
    }
}
