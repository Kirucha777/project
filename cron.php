<?php
// Этот файл должен запускаться по cron каждую минуту
// Добавьте в crontab: * * * * * /usr/bin/php /path/to/kino/cron.php

require_once 'config.php';

// 1. Обработка очереди заданий
require_once 'jobs.php';

// 2. Очистка устаревших бронирований
$pdo = getDB();
$stmt = $pdo->prepare("
    UPDATE seats 
    SET status = 'free', booked_by = NULL, booked_until = NULL 
    WHERE status = 'booked' 
    AND booked_until < NOW()
");
$stmt->execute();

// 3. Очистка старых заданий (старше 1 дня)
$stmt = $pdo->prepare("DELETE FROM jobs WHERE created_at < DATE_SUB(NOW(), INTERVAL 1 DAY)");
$stmt->execute();

echo "Cron job completed at " . date('Y-m-d H:i:s') . "\n";
?>