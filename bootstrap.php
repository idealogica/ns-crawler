<?php
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\ORMSetup;

error_reporting(E_ALL & ~E_DEPRECATED);

include 'config.php';
include 'vendor/autoload.php';

$config = ORMSetup::createAnnotationMetadataConfiguration(
    paths: array(__DIR__ . "/model"),
    isDevMode: true,
);
$conn = array(
    'dbname' => DB_NAME,
    'user' => DB_USWER,
    'password' => DB_PASSWORD,
    'host' => DB_HOST,
    'driver' => DB_DRIVER,
);
$entityManager = EntityManager::create($conn, $config);
