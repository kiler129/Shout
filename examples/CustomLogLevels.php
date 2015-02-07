<?php
require_once("../../../autoloader.php");

/**
 * This example should output "Aaaa!!!" with level of "PARANOIA"
 *
 * Warning: PSR-3 clearly forbids custom log levels. If you use custom log levels in your app you cannot swap logger
 * from Shout to another PSR-3 complaint one without modification!
 */
use noFlash\Shout\Shout;

$logger = new Shout();
$logger->paranoia("Aaaa!!!");