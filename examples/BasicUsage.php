<?php
require_once("../../../autoload.php");

/**
 * This example should output "Hello world" and "Hello you!" to stdout
 */
use noFlash\Shout\Shout;
use Psr\Log\LogLevel;

$logger = new Shout();
$logger->info("Hello world");
$logger->log(LogLevel::INFO, "Hello you!");