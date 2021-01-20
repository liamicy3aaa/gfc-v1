<?php

/**
 * Email Queue Cron
 * @desc - Process emails that are currently in the email queue.
 */

// load the required classes
$emailQueue = $app->get("emailQueue");

// Begin the process
$count = $emailQueue->process();

$response->getBody()->write($count . " emails processed.");

return $response->withStatus(200);