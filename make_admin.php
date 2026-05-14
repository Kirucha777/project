<?php
require_once 'config.php';

// Проверяем, что запрос от администратора
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Access denied']);
    exit();
}

// Получаем данные из запроса
$data = json_decode(file_get_contents('php://input'), true);
$userId = $data['user_id'] ?? 0;

if (!$userId) {
    echo json_encode(['success' => false, 'error' => 'Invalid user ID']);
    exit();
}

$pdo = getDB();

// Назначаем пользователя администратором
$stmt = $pdo->prepare("UPDATE users SET role = 'admin' WHERE id = ?");
$stmt->execute([$userId]);

echo json_encode(['success' => true]);
exit();
?>