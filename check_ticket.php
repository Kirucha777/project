<?php
require_once 'config.php';

// Этот роут общедоступный
$ticketNumber = $_GET['number'] ?? '';

if (!$ticketNumber) {
    header('Location: index.php');
    exit();
}

$pdo = getDB();
$stmt = $pdo->prepare("
    SELECT t.*, s.start_time, m.title, u.full_name, se.row_num, se.seat_num 
    FROM tickets t
    JOIN sessions s ON t.session_id = s.id
    JOIN movies m ON s.movie_id = m.id
    JOIN users u ON t.user_id = u.id
    JOIN seats se ON t.seat_id = se.id
    WHERE t.ticket_number = ?
");
$stmt->execute([$ticketNumber]);
$ticket = $stmt->fetch();

$isValid = false;
$message = '';

if ($ticket) {
    if ($ticket['status'] === 'active' && strtotime($ticket['start_time']) > time()) {
        $isValid = true;
        $message = '✅ Билет действителен';
    } else {
        $message = '❌ Билет недействителен';
    }
} else {
    $message = '❌ Билет не найден';
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Проверка билета - Кинотеатр</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #0d47a1 0%, #1976d2 100%);
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 20px;
        }
        
        .check-container {
            background: rgba(255, 255, 255, 0.95);
            border-radius: 20px;
            padding: 40px;
            width: 100%;
            max-width: 500px;
            box-shadow: 0 15px 35px rgba(0,0,0,0.2);
            text-align: center;
            backdrop-filter: blur(10px);
        }
        
        .logo {
            font-size: 32px;
            font-weight: bold;
            color: #0d47a1;
            margin-bottom: 30px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }
        
        .status-icon {
            font-size: 80px;
            margin: 20px 0;
        }
        
        .status-valid {
            color: #4caf50;
        }
        
        .status-invalid {
            color: #f44336;
        }
        
        .status-message {
            font-size: 24px;
            margin-bottom: 30px;
            font-weight: 600;
        }
        
        .ticket-info {
            background: #f5f5f5;
            border-radius: 15px;
            padding: 20px;
            margin-top: 20px;
            text-align: left;
        }
        
        .info-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 10px;
            padding-bottom: 10px;
            border-bottom: 1px solid #e0e0e0;
        }
        
        .info-row:last-child {
            border-bottom: none;
            margin-bottom: 0;
        }
        
        .info-label {
            color: #666;
            font-weight: 500;
        }
        
        .info-value {
            color: #333;
            font-weight: 600;
        }
        
        .ticket-number {
            font-family: monospace;
            font-size: 20px;
            letter-spacing: 2px;
            background: #333;
            color: white;
            padding: 10px;
            border-radius: 10px;
            margin: 20px 0;
        }
        
        .back-link {
            margin-top: 30px;
        }
        
        .back-link a {
            display: inline-block;
            padding: 12px 25px;
            background: linear-gradient(135deg, #0d47a1 0%, #1976d2 100%);
            color: white;
            text-decoration: none;
            border-radius: 25px;
            font-weight: 500;
            transition: all 0.3s ease;
        }
        
        .back-link a:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(13, 71, 161, 0.4);
        }
        
        .qr-code {
            margin: 20px auto;
            width: 150px;
            height: 150px;
            background: #f0f0f0;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 14px;
            color: #666;
        }
    </style>
</head>
<body>
    <div class="check-container">
        <div class="logo">🎬 Кинотеатр</div>
        
        <div class="ticket-number"><?php echo htmlspecialchars($ticketNumber); ?></div>
        
        <div class="status-icon <?php echo $isValid ? 'status-valid' : 'status-invalid'; ?>">
            <?php echo $isValid ? '✅' : '❌'; ?>
        </div>
        
        <div class="status-message"><?php echo $message; ?></div>
        
        <?php if ($ticket): ?>
            <div class="ticket-info">
                <div class="info-row">
                    <span class="info-label">Фильм:</span>
                    <span class="info-value"><?php echo htmlspecialchars($ticket['title']); ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">Владелец:</span>
                    <span class="info-value"><?php echo htmlspecialchars($ticket['full_name']); ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">Дата сеанса:</span>
                    <span class="info-value"><?php echo date('d.m.Y H:i', strtotime($ticket['start_time'])); ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">Место:</span>
                    <span class="info-value">Ряд <?php echo $ticket['row_num']; ?>, Место <?php echo $ticket['seat_num']; ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">Статус:</span>
                    <span class="info-value"><?php echo $ticket['status'] === 'active' ? 'Активен' : ($ticket['status'] === 'used' ? 'Использован' : 'Отменен'); ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">Цена:</span>
                    <span class="info-value"><?php echo number_format($ticket['price'], 0, '.', ' '); ?> ₽</span>
                </div>
            </div>
            
            <?php if ($ticket['qr_code']): ?>
                <div class="qr-code">
                    <img src="data:image/png;base64,<?php echo $ticket['qr_code']; ?>" 
                         alt="QR Code" 
                         style="width: 100%; height: 100%; object-fit: contain;">
                </div>
            <?php endif; ?>
        <?php endif; ?>
        
        <div class="back-link">
            <a href="index.php">На главную</a>
        </div>
    </div>
</body>
</html>