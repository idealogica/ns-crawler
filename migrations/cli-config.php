<?php
/**
 * @var EntityManager $entityManager
 */

use Doctrine\Migrations\Tools\Console\Command\DiffCommand;
use Doctrine\Migrations\Tools\Console\Command\ExecuteCommand;
use Doctrine\Migrations\Tools\Console\Command\GenerateCommand;
use Doctrine\Migrations\Tools\Console\Command\LatestCommand;
use Doctrine\Migrations\Tools\Console\Command\MigrateCommand;
use Doctrine\Migrations\Tools\Console\Command\StatusCommand;
use Doctrine\Migrations\Tools\Console\Command\UpToDateCommand;
use Doctrine\Migrations\Tools\Console\Command\VersionCommand;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Tools\Console\ConsoleRunner;
use Symfony\Component\Console\Helper\QuestionHelper;

include '../bootstrap.php';

$helperSet = ConsoleRunner::createHelperSet($entityManager);

$helperSet->set(new QuestionHelper(), 'dialog');
ConsoleRunner::run($helperSet, [
    new ExecuteCommand(),
    new GenerateCommand(),
    new LatestCommand(),
    new MigrateCommand(),
    new DiffCommand(),
    new UpToDateCommand(),
    new StatusCommand(),
    new VersionCommand()
]);
