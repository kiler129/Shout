<?php
require_once("../../../autoload.php");

/**
 * This example should output two lines of logs similar to below:
 * 1423350960|INFO|Test
 * 1423350960|WARNING|Test2
 */
use noFlash\Shout\Shout;

$logger = new Shout();
$logger->setLineFormat("%5\$s|%2\$s|%3\$s\n");
$logger->info("Test");
$logger->warning("Test2");