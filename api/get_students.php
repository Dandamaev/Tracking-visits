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

        // Получаем параметр ClassID из GET запроса
        $class_id = $_GET['ClassID'];

        // Запрос для получения списка студентов по группе занятия
        $studentsQuery = "
                            SELECT 
                                u.itmo_id AS itmo_id,
                                grp.name AS group_name,
                                u.full_name AS full_name,
                                att.status AS status
                            FROM 
                                Attendance att 
                            JOIN 
                                Students st ON att.student_id = st.id
                            JOIN 
                                Users u ON u.itmo_id = st.itmo_id
                            JOIN
                                Groups grp ON st.group_id = grp.id
                            WHERE 
                                att.class_id = :class_id
                            ORDER BY 
                                full_name ASC;
        ";

        // Подготовка запроса
        $stmtStudents = $db->prepare($studentsQuery);
        $stmtStudents->bindValue(':class_id', $class_id, PDO::PARAM_INT);

        try {
            $stmtStudents->execute();
            $groupStudents = $stmtStudents->fetchAll(PDO::FETCH_ASSOC);

            // Установка HTTP заголовка для ответа в формате JSON
            header('Content-Type: application/json');
            http_response_code(200);

            // Вывод данных в виде JSON объекта
            echo json_encode(['students' => $groupStudents]);
        } catch (PDOException $e) {
            http_response_code(500); // Ошибка сервера
            echo json_encode(['error' => 'Ошибка выполнения запроса: ' . $e->getMessage()]);
            exit;
        }
    }
}
