<?php
require_once 'config.php';

// Проверяем авторизацию
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

// Проверяем, что это POST запрос
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: profile.php');
    exit();
}

$ticketId = $_POST['ticket_id'] ?? 0;
if (!$ticketId) {
    header('Location: profile.php');
    exit();
}

$pdo = getDB();

// Проверяем, что билет принадлежит пользователю и еще не использован
$stmt = $pdo->prepare("
    SELECT t.*, s.start_time 
    FROM tickets t
    JOIN sessions s ON t.session_id = s.id
    WHERE t.id = ? AND t.user_id = ? AND t.status = 'active'
");
$stmt->execute([$ticketId, $_SESSION['user_id']]);
$ticket = $stmt->fetch();

if (!$ticket) {
    header('Location: profile.php?error=ticket_not_found');
    exit();
}

// Проверяем, что до сеанса еще есть время (не менее 1 часа)
if (strtotime($ticket['start_time']) <= time() + 3600) {
    header('Location: profile.php?error=too_late');
    exit();
}

// Отменяем билет
$stmt = $pdo->prepare("
    UPDATE tickets 
    SET status = 'cancelled' 
    WHERE id = ?
");
$stmt->execute([$ticketId]);

// Освобождаем место
$stmt = $pdo->prepare("
    UPDATE seats 
    SET status = 'free', booked_by = NULL, booked_until = NULL 
    WHERE id = ?
");
$stmt->execute([$ticket['seat_id']]);

// Сбрасываем кеш
clearCache("seats_session_{$ticket['session_id']}");

// Перенаправляем обратно в профиль
header('Location: profile.php?success=ticket_cancelled');
exit();
?>