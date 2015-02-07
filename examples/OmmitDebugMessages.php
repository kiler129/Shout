<?php
require_once("../../../autoloader.php");

/**
 * This example will print info and warning message only.
 */
use noFlash\Shout\Shout;

$logger = new Shout();
$logger->setMaximumLogLevel(6);

$logger->info("Hello there");
$logger->debug("This will not be displayed");
$logger->info("Howdy?");