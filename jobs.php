<?php
require_once 'config.php';

// Этот файл запускается по cron каждую минуту
// Для тестирования можно запустить вручную

$pdo = getDB();

// Берем задания из очереди
$stmt = $pdo->prepare("
    SELECT * FROM jobs 
    WHERE queue = 'generate_pdf' 
    AND (reserved_at IS NULL OR reserved_at < DATE_SUB(NOW(), INTERVAL 5 MINUTE))
    AND available_at <= NOW()
    ORDER BY created_at 
    LIMIT 10
    FOR UPDATE SKIP LOCKED
");
$stmt->execute();
$jobs = $stmt->fetchAll();

foreach ($jobs as $job) {
    try {
        // Резервируем задание
        $stmt = $pdo->prepare("UPDATE jobs SET reserved_at = NOW(), attempts = attempts + 1 WHERE id = ?");
        $stmt->execute([$job['id']]);
        
        // Обрабатываем задание
        $payload = json_decode($job['payload'], true);
        $ticketId = $payload['ticket_id'] ?? 0;
        
        if ($ticketId) {
            // Здесь должна быть логика генерации PDF
            // Для примера просто создаем QR-код
            $ticketNumber = 'T' . date('Ymd') . str_pad($ticketId, 5, '0', STR_PAD_LEFT);
            $qrData = "KINOTHEATER:TICKET:{$ticketNumber}";
            
            // Генерируем простой QR-код (в реальном приложении используйте библиотеку)
            $qrCode = base64_encode($qrData);
            
            // Сохраняем QR-код в билете
            $stmt = $pdo->prepare("UPDATE tickets SET qr_code = ? WHERE id = ?");
            $stmt->execute([$qrCode, $ticketId]);
        }
        
        // Удаляем обработанное задание
        $stmt = $pdo->prepare("DELETE FROM jobs WHERE id = ?");
        $stmt->execute([$job['id']]);
        
    } catch (Exception $e) {
        // В случае ошибки оставляем задание для повторной попытки
        error_log("Job processing error: " . $e->getMessage());
    }
}

echo "Processed " . count($jobs) . " jobs\n";
?>