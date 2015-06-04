<?php
require_once("../../../autoload.php");

/**
 * This example should create file with name similar to "rotate-1423350962.log", save two lines of log there with 1s between them.
 * Next script will wait 2s and create another file named similar to "rotate-1423350965.log" next two lines.
 */
use noFlash\Shout\Shout;

$logger = new Shout("rotate-%1$s.log");
$logger->setRotate(true);

//These two lines will be saved to first file
$logger->info("Test");
sleep(1);
$logger->warning("Test2");

sleep(2);
$logger->rotate();

//Next two lines will go to next file
$logger->info("Test (2nd file)");
$logger->warning("Test2 (2nd file)");