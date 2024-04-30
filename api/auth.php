<?php
// Подключаем файл с настройками базы данных и секретным ключом
require_once 'db_connect.php';
require_once 'config/config.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Получаем данные из POST-запроса
    $itmo_id_or_email = $_POST['username'] ?? null;
    $password = $_POST['password'] ?? null;
    $rememberMe = isset($_POST['rememberMe']) && $_POST['rememberMe'] == 'on';

    if (!$itmo_id_or_email || !$password) {
        http_response_code(400);
        echo json_encode(['error' => 'Отсутствуют необходимые данные']);
        exit;
    }

    // Запрос для проверки пользователя в базе данных по itmo_id или email
    $query = "SELECT itmo_id, email, password, role FROM users WHERE (itmo_id = :itmo_id OR email = :email)";
    $stmt = $db->prepare($query);
    $stmt->bindValue(':itmo_id', $itmo_id_or_email, PDO::PARAM_INT);
    $stmt->bindValue(':email', $itmo_id_or_email, PDO::PARAM_STR);
    $stmt->execute();

    // Получаем данные пользователя из базы данных
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user && $user['password'] === $password) {
        // Пароль верный
        $itmo_id = $user['itmo_id'];
        $role = $user['role'];

        // Создаем полезную нагрузку (payload) для JWT, включая itmo_id и role
        $payloadData = [
            'itmo_id' => $itmo_id,
            'role' => $role
        ];
        $payload = base64_encode(json_encode($payloadData));

        // Создаем заголовок (header) для JWT
        $header = base64_encode(json_encode(['alg' => 'HS256', 'typ' => 'JWT']));

        // Генерируем подпись (signature) с использованием HMAC-SHA256
        $signature = hash_hmac('sha256', "$header.$payload", $secretKey);

        // Собираем полный JWT
        $jwt = "$header.$payload.$signature";

        // Если выбрано "Запомнить меня", устанавливаем куку с токеном на длительный срок
        if ($rememberMe) {
            $cookie_expiry = time() + (7 * 24 * 60 * 60); // Токен будет действителен 7 дней (604800 секунд)
            setcookie('jwt_token', $jwt, $cookie_expiry, '/', '', false, true); // Задаем куку с токеном
        }

        // Устанавливаем заголовок с токеном в виде Bearer Token
        header('Authorization: Bearer ' . $jwt);
        // Отправляем JWT клиенту
        http_response_code(200);
        echo json_encode(['token' => $jwt, 'role' => $role]);
        exit; // Завершаем выполнение скрипта после отправки токена
    } else {
        // Неверный ITMO ID (или E-mail) или пароль
        http_response_code(401);
        echo json_encode(['error' => 'Неверный ITMO ID или пароль']);
        exit; // Завершаем выполнение скрипта при ошибке
    }
} else {
    // Метод запроса не POST
    http_response_code(405); // Метод не разрешен
    echo json_encode(['error' => 'Метод не разрешен']);
    exit; // Завершаем выполнение скрипта при ошибке
}
