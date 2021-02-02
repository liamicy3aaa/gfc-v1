<?php

/**
 * Email Queue Flush Cron
 * @desc - Clear database of processed emails to reduce clutter.
 */

// load the required classes
$emailQueue = $app->get("emailQueue");

// Begin the process
$count = $emailQueue->clean();

$response->getBody()->write($count . " emails cleaned.");

return $response->withStatus(200);