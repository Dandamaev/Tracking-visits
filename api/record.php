<?php
// Подключаем файл с настройками базы данных и секретным ключом
require_once 'db_connect.php';
require_once 'config/config.php';

// Проверяем метод запроса (должен быть GET)
if ($_SERVER["REQUEST_METHOD"] == "GET") {
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

        // Декодируем полезную нагрузку (payload) из base64
        $payloadJson = base64_decode($payloadBase64);
        $payload = json_decode($payloadJson, true);

        if (isset($payload['itmo_id'])) {
            $itmo_id = $payload['itmo_id'];
        }

        // Получаем токен из параметра URL
        $token = $_GET['token'];

        // Разбиваем токен на составляющие (header, payload, signature)
        $tokenParts = explode('.', $token);
        if (count($tokenParts) !== 3) {
            http_response_code(400); // Ошибка некорректного запроса
            die(json_encode(['error' => 'Некорректный формат JWT']));
        }

        // Декодируем полезную нагрузку (payload) из base64
        $payloadJson = base64_decode($tokenParts[1]);
        $payload = json_decode($payloadJson, true);

        // Проверяем, есть ли необходимые данные в payload
        if (isset($payload['classid']) && isset($payload['exp'])) {
            $class_id = $payload['classid'];
            $ex = $payload['exp']; // Время жизни, если оно нужно

            // Проверяем, если время жизни токена меньше текущего времени
            if (strtotime($ex) < time()) {
                die(json_encode(['message' => 'Время жизни ссылки истекло']));
            }

            // Ваш SQL запрос, используя $class_id
            $sql = "
                UPDATE Attendance
                SET status = 'Present',
                    timestamp = NOW()
                FROM Students
                WHERE Attendance.student_id = Students.id
                AND Students.itmo_id = :itmo_id
                AND Attendance.class_id = :class_id;
            ";

            $stmt = $db->prepare($sql);
            $stmt->bindValue(':itmo_id', $itmo_id);
            $stmt->bindValue(':class_id', $class_id);
            $stmt->execute();

            // Проверяем, были ли изменены строки
            if ($stmt->rowCount() > 0) {
                // Если были изменения, отправляем сообщение об успешной отметке
                echo json_encode(['message' => 'Вы отметились на занятии']);
            }
        } else {
            http_response_code(400); // Ошибка некорректного запроса
            die(json_encode(['error' => 'Отсутствуют необходимые данные в токене']));
        }
    } else {
        http_response_code(401); // Ошибка авторизации
        die(json_encode(['error' => 'Отсутствует заголовок Authorization']));
    }
} else {
    http_response_code(405); // Метод не поддерживается
    die(json_encode(['error' => 'Метод запроса не поддерживается']));
}
