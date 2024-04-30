<?php
require_once 'config/config.php';

// Подключение к базе данных и установка режима обработки ошибок
try {
    $db = new PDO("pgsql:host=$host;port=$port;dbname=$dbname;user=$username;password=$password");
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    // В случае ошибки подключения к базе данных выводим сообщение об ошибке
    die(json_encode(['error' => 'Ошибка подключения к базе данных: ' . $e->getMessage() . ' (Код ошибки: ' . $e->getCode() . ')']));
}
