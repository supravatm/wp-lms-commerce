<?php
require_once __DIR__ . '/wp-load.php';


if (!class_exists('LearnUponLMS\Services\ApiClient')) {
    exit("LUI_Queue class not loaded.\n");
}

$result = (new LearnUponLMS\Services\ApiClient())->getUsers(1);
print_r($result);
error_log("supravat mondal - learnupon");
echo "Queue processing completed.\n";
