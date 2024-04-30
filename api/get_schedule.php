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

        if (isset($payload['role'])) {
            $role = $payload['role'];
            if ($role = 'Teacher') {


                if (isset($payload['itmo_id'])) {
                    $itmo_id = $payload['itmo_id'];
                }

                //получение id преподавателя
                $query = "SELECT id FROM Users WHERE itmo_id = :itmo_id and role = 'Teacher'";
                $stmt = $db->prepare($query);
                $stmt->bindValue(':itmo_id', $itmo_id);
                $stmt->execute();


                $teacher_id = $stmt->fetchColumn();

                $sql = "
                        SELECT 
                            c.date AS class_date,
                            subj.name AS subject_name,
                            grp.name AS group_name,
                            grp.id AS group_id,
                            c.id AS class_id,
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
                            c.date = CURRENT_DATE
                            AND c.itmo_id =  :itmo_id
                ";

                // Подготовка запроса
                $stmt = $db->prepare($sql);
                $stmt->bindValue(':itmo_id', $payload['itmo_id'], PDO::PARAM_INT);

                // Выполнение запроса
                try {
                    $stmt->execute();
                    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

                    // Формирование данных в виде массива
                    $response = [];
                    foreach ($results as $row) {
                        $lessonData = [
                            'class_id' => $row['class_id'],
                            'subject_name' => $row['subject_name'],
                            'group_name' => $row['group_name'],
                            'class_type' => $row['class_type'],
                            'building' => $row['building'],
                            'classroom' => $row['classroom'],
                            'start_time' => $row['start_time'],
                            'end_time' => $row['end_time']
                        ];
                        $response[] = $lessonData;
                    }


                    // Установка HTTP заголовков для ответа в формате JSON
                    header('Content-Type: application/json');
                    http_response_code(200);

                    // Вывод данных в виде JSON
                    echo json_encode($response);
                    exit; // Завершаем выполнение скрипта после отправки данных      
                } catch (PDOException $e) {
                    http_response_code(500); // Ошибка сервера
                    echo json_encode(['error' => 'Ошибка выполнения запроса: ' . $e->getMessage()]);
                }
            }
        }
    }
}
