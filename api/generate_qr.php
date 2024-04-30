<?php
// Подключаем файл с настройками базы данных и секретным ключом
require_once 'db_connect.php';
require_once 'config/config.php';

// Проверяем метод запроса (должен быть GET)
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Проверяем наличие заголовка Authorization с JWT
    $headers = getallheaders();
    if (isset($headers['authorization'])) {
        $jwt = $headers['authorization'];

        // Извлечение токена из заголовка
        list(, $jwtToken) = explode(' ', $jwt);

        // Разбиваем токен на составляющие (header, payload, signature)
        $tokenParts = explode('.', $jwtToken);
        if (count($tokenParts) !== 3) {
            http_response_code(400); // Ошибка некорректного запроса
            die(json_encode(['error' => 'Некорректный формат JWT']));
        }

        // Декодирование и проверка подписи JWT
        $header = $tokenParts[0];
        $payloadBase64 = $tokenParts[1];
        $signature = $tokenParts[2];

        // Проверяем подлинность подписи
        $validSignature = hash_hmac('sha256', "$header.$payloadBase64", $secretKey);
        if ($signature !== $validSignature) {
            http_response_code(401); // Ошибка авторизации
            die(json_encode(['error' => 'Неверная подпись JWT']));
        }

        // Устанавливаем время жизни нового токена в минутах
        $timelive = 2;

        // Создаем текущее время
        $now = time();

        $class_id = $_GET['ClassID'];

        // Сохраняем данные в payload токена
        $payloadData = [
            'exp' => $now + ($timelive * 60), // Время истечения токена
            'classid' => $class_id
        ];

        // Кодируем payload в формат JSON и затем в base64
        $payload = base64_encode(json_encode($payloadData));

        // Создаем заголовок (header) для JWT
        $header = base64_encode(json_encode(['alg' => 'HS256', 'typ' => 'JWT']));

        // Генерируем подпись (signature) с использованием HMAC-SHA256
        $signature = hash_hmac('sha256', "$header.$payload", $secretKey, true);

        // Кодируем подпись в base64
        $signature_base64 = base64_encode($signature);

        // Собираем полный JWT
        $token = "$header.$payload.$signature_base64";

        // Отправляем новый токен клиенту
        header('Content-Type: application/json');
        http_response_code(200);
        echo json_encode(['token' => $token]);
        exit;
    }
}
