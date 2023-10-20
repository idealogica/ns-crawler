<?php
namespace Idealogica\NsCrawler\Item;

class Property implements ItemInterface
{
    private string $sourceName;

    private string $id;

    private string $link;

    private array $images;

    private string $title;

    private ?string $location = null;

    private ?string $roomsNumber = null;

    private ?string $area = null;

    private ?bool $agency = null;

    private ?bool $furnished = null;

    private ?string $district = null;

    private ?int $price = null;

    private ?string $description = null;

    private ?\DateTime $date;

    private array $phoneNumbers = [];

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
     * @return Property
     */
    public function setSourceName(string $sourceName): Property
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
     * @return Property
     */
    public function setId(string $id): Property
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
     * @return Property
     */
    public function setLink(string $link): Property
    {
        $this->link = $link;
        return $this;
    }

    /**
     * @return array
     */
    public function getImages(): array
    {
        return $this->images;
    }

    /**
     * @param array $images
     *
     * @return Property
     */
    public function setImages(array $images): Property
    {
        $this->images = $images;
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
     * @return Property
     */
    public function setTitle(string $title): Property
    {
        $this->title = $title;
        return $this;
    }

    /**
     * @return null|string
     */
    public function getLocation(): ?string
    {
        return $this->location;
    }

    /**
     * @param null|string $location
     *
     * @return Property
     */
    public function setLocation(?string $location): Property
    {
        $this->location = $location;
        return $this;
    }

    /**
     * @return string|null
     */
    public function getRoomsNumber(): ?string
    {
        return $this->roomsNumber;
    }

    /**
     * @param string|null $roomsNumber
     *
     * @return Property
     */
    public function setRoomsNumber(?string $roomsNumber): Property
    {
        $this->roomsNumber = $roomsNumber;
        return $this;
    }

    /**
     * @return string|null
     */
    public function getArea(): ?string
    {
        return $this->area;
    }

    /**
     * @param string|null $area
     *
     * @return Property
     */
    public function setArea(?string $area): Property
    {
        $this->area = $area;
        return $this;
    }

    /**
     * @return bool|null
     */
    public function isAgency(): ?bool
    {
        return $this->agency;
    }

    /**
     * @param null|bool $agency
     *
     * @return Property
     */
    public function setAgency(?bool $agency): Property
    {
        $this->agency = $agency;
        return $this;
    }

    /**
     * @return bool|null
     */
    public function isFurnished(): ?bool
    {
        return $this->furnished;
    }

    /**
     * @param null|bool $furnished
     *
     * @return Property
     */
    public function setFurnished(?bool $furnished): Property
    {
        $this->furnished = $furnished;
        return $this;
    }

    /**
     * @return string|null
     */
    public function getDistrict(): ?string
    {
        return $this->district;
    }

    /**
     * @param string|null $district
     *
     * @return Property
     */
    public function setDistrict(?string $district): Property
    {
        $this->district = $district;
        return $this;
    }

    /**
     * @return int|null
     */
    public function getPrice(): ?int
    {
        return $this->price;
    }

    /**
     * @param null|int $price
     *
     * @return Property
     */
    public function setPrice(?int $price): Property
    {
        $this->price = $price;
        return $this;
    }

    /**
     * @return null|string
     */
    public function getDescription(): ?string
    {
        return $this->description;
    }

    /**
     * @param null|string $description
     *
     * @return Property
     */
    public function setDescription(?string $description): Property
    {
        $this->description = $description;
        return $this;
    }

    /**
     * @return \DateTime|null
     */
    public function getDate(): ?\DateTime
    {
        return $this->date;
    }

    /**
     * @param \DateTime|null $date
     *
     * @return Property
     */
    public function setDate(?\DateTime $date): Property
    {
        $this->date = $date;
        return $this;
    }

    /**
     * @return array
     */
    public function getPhoneNumbers(): array
    {
        return $this->phoneNumbers;
    }

    /**
     * @param array $phoneNumbers
     *
     * @return Property
     */
    public function setPhoneNumbers(array $phoneNumbers): Property
    {
        $this->phoneNumbers = $phoneNumbers;
        return $this;
    }

    /**
     * @return string
     */
    public function __toString()
    {
        $string = sprintf(
            "%s\n\n*ID*: %s",
            $this->prepareMarkdown($this->getTitle()),
            $this->getId()
        );
        if ($this->getLocation()) {
            $string .= "\n*Location*: " . $this->getLocation();
        }
        if ($this->getRoomsNumber()) {
            $string .= "\n*Rooms number*: " . $this->getRoomsNumber();
        }
        if ($this->getArea()) {
            $string .= "\n*Floor area*: " . $this->getArea();
        }
        if (is_bool($this->isAgency())) {
            $string .= "\n*Agency*: " . ($this->isAgency() ? 'Yes' : 'No');
        }
        if (is_bool($this->isFurnished())) {
            $string .= "\n*Furnished*: " . ($this->isFurnished() ? 'Yes' : 'No');
        }
        if ($this->getDistrict()) {
            $string .= "\n*District*: " . $this->getDistrict();
        }
        $phoneNumberLinks = [];
        foreach ($this->getPhoneNumbers() as $phoneNumber) {
            $phoneNumberLinks[] = '[' . $phoneNumber . '](tel:' . $phoneNumber .  ')';
        }
        $price = null;
        $priceSqm = null;
        if ($this->getPrice() && preg_match('#([0-9.,]+)#i', $this->getPrice(), $priceMatches)) {
            $price = $priceMatches[1];
            if ($this->getArea() && preg_match('#([0-9.,]+)#i', $this->getArea(), $areaMatches)) {
                $priceSqm = (int) ($price / $areaMatches[1]);
            }
        }
        $string .= sprintf(
            "\n*Price*: %s\n*Date*: %s\n*Phones*: %s\n\n%s\n\n%s",
            ($price ? $price . ' EUR' : '-') .
            ($priceSqm ? (' (' . $priceSqm . ' EUR/M2' . ($priceSqm <= 2300 ? ' \xE2\x9C\x85' : '') . ')') : ''),
            $this->getDate()->format('Y-m-d'),
            $phoneNumberLinks ? implode(', ', $phoneNumberLinks) : '-',
            $this->getDescription() ? $this->prepareMarkdown($this->getDescription()) : '-',
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
