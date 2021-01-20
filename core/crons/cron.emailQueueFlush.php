<?php

/**
 * Email Queue Flush Cron
 * @desc - Clear database of processed emails to reduce clutter.
 */

// load the required classes
$emailQueue = $app->get("emailQueue");
//$emailQueue->add(1,"general", "TEST Email", array("%CONTENT%" => "THIS IS A TEST MESSAGE FROM LIAM."));

//exit;
// Begin the process

$count = $emailQueue->clean();

$response->getBody()->write($count . " emails cleaned.");

return $response->withStatus(200);