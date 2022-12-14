<?php
namespace Idealogica\NsCrawler;

use Doctrine\ORM\Mapping AS ORM;

/**
 * History
 *
 * @ORM\Table(name="History", uniqueConstraints={@ORM\UniqueConstraint(name="id_uindex", columns={"id"})}, indexes={@ORM\Index(name="source_uindex", columns={"source", "sourceId"})})
 * @ORM\Entity
 */
class History
{
    /**
     * @var int
     *
     * @ORM\Column(name="id", type="integer", nullable=false)
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="IDENTITY")
     */
    private int $id;

    /**
     * @var string
     *
     * @ORM\Column(name="source", type="string", length=255, nullable=false)
     */
    private string $source;

    /**
     * @var string
     *
     * @ORM\Column(name="sourceId", type="string", length=255, nullable=false)
     */
    private string $sourceId;

    /**
     * @var null|\DateTime
     *
     * @ORM\Column(name="sentOn", type="datetime", nullable=true)
     */
    private ?\DateTime $sentOn = null;

    /**
     * @var \DateTime
     *
     * @ORM\Column(name="insertedOn", type="datetime", nullable=false)
     */
    private \DateTime $insertedOn;

    /**
     * @return int
     */
    public function getId(): int
    {
        return $this->id;
    }

    /**
     * @param int $id
     *
     * @return History
     */
    public function setId(int $id): History
    {
        $this->id = $id;
        return $this;
    }

    /**
     * @return string
     */
    public function getSource(): string
    {
        return $this->source;
    }

    /**
     * @param string $source
     *
     * @return History
     */
    public function setSource(string $source): History
    {
        $this->source = $source;
        return $this;
    }

    /**
     * @return string
     */
    public function getSourceId(): string
    {
        return $this->sourceId;
    }

    /**
     * @param string $sourceId
     *
     * @return History
     */
    public function setSourceId(string $sourceId): History
    {
        $this->sourceId = $sourceId;
        return $this;
    }

    /**
     * @return \DateTime|null
     */
    public function getSentOn(): ?\DateTime
    {
        return $this->sentOn;
    }

    /**
     * @param \DateTime|null $sentOn
     *
     * @return History
     */
    public function setSentOn(?\DateTime $sentOn): History
    {
        $this->sentOn = $sentOn;
        return $this;
    }

    /**
     * @return \DateTime
     */
    public function getInsertedOn(): \DateTime
    {
        return $this->insertedOn;
    }

    /**
     * @param \DateTime $insertedOn
     *
     * @return History
     */
    public function setInsertedOn(\DateTime $insertedOn): History
    {
        $this->insertedOn = $insertedOn;
        return $this;
    }

    /**
     * @return bool
     * @throws \Exception
     */
    public function isReadyForProcessing(): bool
    {
        $minutes = mt_rand(8, 14);
        $timeDiffInterval = new \DateInterval('PT' . $minutes . 'M');
        $timeDiffInterval->invert = 1;
        return ! $this->getSentOn() && (new \DateTime())->add($timeDiffInterval) >= $this->getInsertedOn();
    }
}
