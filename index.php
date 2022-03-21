<?php

include __DIR__ . '/includes/config.php';
include __DIR__ . '/includes/send_message.php';

$dictionary = json_decode(file_get_contents('dictionary.json'), true);
$lang = $config['lang'];

# Open Weather Map Setting
$owm_data = array(
    'units' => 'metric',
    'lang' => $lang,
    'appid' => $owm_api_key
);

# Токен и ресурс Telegram
$url = "https://api.telegram.org/bot" . $botToken;

# Принимаем запрос
$data = file_get_contents('php://input');
$data = json_decode($data, true);

# Определяем какого типа запрос
if ($data['callback_query']) {
    $message = $data['callback_query']['data'];
    $data = $data['callback_query'];
    $is_callack = true;
} else {
    $message = $data['message']['text'];
    $data = $data['message'];
}

# Запись ответа в файл для отладки
file_put_contents('log/data.txt', print_r($data, true));

$chat_id = $data['from']['id'];
$user_name = $data['from']['first_name'];
$message_id = $data['message']['message_id'];
$method = 'sendMessage';
$location = $data['location'] ? $data['location'] : $message;

# Сохранить в историю запросов
file_put_contents('log/history.csv', "$chat_id;$user_name;$location\n", FILE_APPEND);

if ($message === '/start') {
    $send_data = [
        'text' => "Hi, $user_name! What language do you prefer?",
        'chat_id' => $chat_id,
        'reply_markup' => json_encode([
            'inline_keyboard' => [
                [
                    ['text' => "🇬🇧 English", "callback_data" => "en"],
                    ['text' => "🇷🇺 Russian", "callback_data" => "ru"],
                ],
            ],
        ]),
    ];
    sendMessage($url, $method, $send_data);
    return;
}

# Set language
if ($message === 'en' || $message === 'ru') {
    if ($message == 'en') {
        $config = ['lang' => 'en'];
    } else {
        $config = ['lang' => 'ru'];
    }
    file_put_contents('config.json', json_encode($config));
    $send_data = [
        'text' => $dictionary[$message]['greeting'],
        'chat_id' => $chat_id,
    ];
    sendMessage($url, $method, $send_data);
    return;
}

if ($is_callack) {
    $forecast = get_forecast($message);
    $send_data = [
        'text' => $forecast,
        'chat_id' => $chat_id,
        'message_id' => $message_id,
        'parse_mode' => 'Markdown',
    ];
    sendMessage($url, $method, $send_data);
    return;
}

$forecast = get_current_weather($location);

if (isset($forecast)) {
    $send_data = [
        'text' => format_message($forecast, $lang),
        'chat_id' => $chat_id,
        'message_id' => $message_id,
        'parse_mode' => 'Markdown',
        'reply_markup' => json_encode([
            'inline_keyboard' => [
                [
                    ['text' => $dictionary[$lang]['12h'], "callback_data" => "&q=$location&cnt=5"],
                    ['text' => $dictionary[$lang]['3d'], "callback_data" => "&q=$location&cnt=20"],
                ],
            ],
        ]),
    ];
    sendMessage($url, $method, $send_data);
    return;
} else {
    $send_data = [
        'text' => $dictionary[$lang]['unknown-place'],
        'chat_id' => $chat_id,
    ];
    sendMessage($url, $method, $send_data);
    return;
}

function get_current_weather($location)
{
    $api_endpoint = 'http://api.openweathermap.org/data/2.5/weather';
    $data = $GLOBALS['owm_data'];
    if (is_array($location)) {
        $data['lat'] = $location['latitude'];
        $data['lon'] = $location['longitude'];
    } else {
        $data['q'] = $location;
    }
    $data = http_build_query($data);
    $response = file_get_contents("$api_endpoint?$data");
    $response = json_decode($response, true);

    return $response;
}

function get_forecast($request)
{
    $api_endpoint = 'http://api.openweathermap.org/data/2.5/forecast';
    $data = $GLOBALS['owm_data'];
    $lang = $data['lang'];
    $data = http_build_query($data) . $request;
    $response = file_get_contents("$api_endpoint?$data");
    $response = json_decode($response, true);
    $list = $response['list'];
    $message = "";
    foreach ($list as $forecast) {
        $message .= format_message($forecast, $lang) . "\n\n";
    }
    return $message;
}

function format_message($forecast, $lang)
{
    $city = $forecast['name'] ? $forecast['name'] : '';
    $date = $forecast['dt_txt'] ? $forecast['dt_txt'] : '';
    $description = $forecast['weather'][0]['description'];
    $temp = $forecast['main']['temp'];
    $feels = $forecast['main']['feels_like'];
    $pressure = $forecast['main']['pressure'];
    $humidity = $forecast['main']['humidity'];
    $windspeed = $forecast['wind']['speed'];

    if ($lang == 'en') {
        $message = [
            'city' => "*City:* $city",
            'date' => "*Date:* $date",
            'description' => "*Sky:* $description",
            'temperature' => "*Temperature:* $temp °C",
            'feels' => "*Feels like:* $feels °C",
            'pressure' => "*Pressure:* $pressure мбар",
            'humidity' => "*Humidity:* $humidity %",
            'windspeed' => "*Wind speed:* $windspeed м/с",
        ];
    } else {
        $message = [
            'city' => "*Город:* $city",
            'date' => "*Время:* $date",
            'description' => "*Небо:* $description",
            'temperature' => "*Температура:* $temp °C",
            'feels' => "*Ощущается как:* $feels °C",
            'pressure' => "*Давление:* $pressure мбар",
            'humidity' => "*Влажность:* $humidity %",
            'windspeed' => "*Ветер:* $windspeed м/с",
        ];
    }

    if ($city === '') {
        unset($message['city']);
    }

    if ($date === '') {
        unset($message['date']);
    }

    return implode("\n", $message);
}
