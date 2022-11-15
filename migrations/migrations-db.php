<?php
/**
 * @var EntityManager $entityManager
 */

use Doctrine\ORM\EntityManager;

require '../bootstrap.php';

return $entityManager->getConnection();
