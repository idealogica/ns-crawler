<?php
namespace Idealogica\NsCrawler\Source;

use Idealogica\NsCrawler\Item\ItemInterface;

interface SourceInterface
{
    /**
     * @param array $errors
     *
     * @return ItemInterface[]
     */
    public function fetchItems(array &$errors = []): array;
}
