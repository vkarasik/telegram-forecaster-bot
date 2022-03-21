<?php

$botToken = '<bot_token>';
$owm_api_key = '<OWM_api_key>';

$config = json_decode(file_get_contents('config.json'), true);

/*
    Set webhook
    https://api.telegram.org/bot<bot_token>/setwebhook?url=<url>/forecaster_bot/index.php
*/
