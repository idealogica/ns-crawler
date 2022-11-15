<?php
/**
 * @var EntityManager $entityManager
 */

use Doctrine\ORM\EntityManager;

include '../bootstrap.php';

return $entityManager->getConnection();
