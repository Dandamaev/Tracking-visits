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

        //получение id преподавателя
        $query = "SELECT id FROM Users WHERE itmo_id = :itmo_id and role = 'Teacher'";
        $stmt = $db->prepare($query);
        $stmt->bindValue(':itmo_id', $itmo_id);
        $stmt->execute();

        $teacher_id = $stmt->fetchColumn();

        // Получаем параметр ClassID из GET запроса
        $class_id = $_GET['ClassID'];

        $sql = "
                SELECT 
                    subj.name AS subject_name,
                    grp.name AS group_name,
                    c.type AS class_type,
                    c.building AS building,
                    c.classroom AS classroom,
                    ct.start_time AS start_time,
                    ct.end_time AS end_time
                FROM 
                    Classes c 
                JOIN 
                    Subjects subj ON c.subject_id = subj.id
                JOIN 
                    Groups grp ON c.group_id = grp.id
                JOIN 
                    ClassTimes ct ON c.class_time_id = ct.id
                WHERE 
                    c.id = :class_id
                    AND c.itmo_id = :itmo_id
        ";


        $stmt = $db->prepare($sql);
        $stmt->bindValue(':itmo_id', $payload['itmo_id'], PDO::PARAM_INT);
        $stmt->bindValue(':class_id', $class_id, PDO::PARAM_INT);

        try {
            $stmt->execute();

            // Получаем результаты запроса в виде ассоциативного массива
            $result = $stmt->fetch(PDO::FETCH_ASSOC);

            // Устанавливаем HTTP заголовок для ответа в формате JSON
            header('Content-Type: application/json');
            http_response_code(200);

            // Выводим результаты запроса в виде JSON объекта
            echo json_encode($result);
        } catch (PDOException $e) {
            http_response_code(500); // Ошибка сервера
            echo json_encode(['error' => 'Ошибка выполнения запроса: ' . $e->getMessage()]);
            exit;
        }
    }
}
