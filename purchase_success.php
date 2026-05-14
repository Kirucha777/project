<?php
require_once 'config.php';

// Проверяем, есть ли данные о покупке в сессии
if (!isset($_SESSION['purchase_success'])) {
    header('Location: index.php');
    exit();
}

$purchaseData = $_SESSION['purchase_success'];
unset($_SESSION['purchase_success']);

// Получаем детали покупки из БД
$pdo = getDB();
$placeholders = str_repeat('?,', count($purchaseData['ticket_numbers']) - 1) . '?';
$stmt = $pdo->prepare("
    SELECT t.*, s.start_time, m.title, se.row_num, se.seat_num 
    FROM tickets t
    JOIN sessions s ON t.session_id = s.id
    JOIN movies m ON s.movie_id = m.id
    JOIN seats se ON t.seat_id = se.id
    WHERE t.ticket_number IN ($placeholders)
    ORDER BY t.id
");
$stmt->execute($purchaseData['ticket_numbers']);
$tickets = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Покупка успешна - Кинотеатр</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #6a11cb 0%, #2575fc 100%);
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 20px;
        }
        
        .success-container {
            background: rgba(255, 255, 255, 0.95);
            border-radius: 20px;
            padding: 40px;
            width: 100%;
            max-width: 800px;
            box-shadow: 0 15px 35px rgba(0,0,0,0.2);
            text-align: center;
            backdrop-filter: blur(10px);
        }
        
        .logo {
            font-size: 32px;
            font-weight: bold;
            color: #2e7d32;
            margin-bottom: 30px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }
        
        .success-icon {
            font-size: 80px;
            color: #4caf50;
            margin: 20px 0;
        }
        
        .success-title {
            font-size: 32px;
            color: #2e7d32;
            margin-bottom: 15px;
        }
        
        .success-message {
            font-size: 18px;
            color: #666;
            margin-bottom: 30px;
        }
        
        .ticket-summary {
            background: #f5f5f5;
            border-radius: 15px;
            padding: 25px;
            margin: 20px 0;
            text-align: left;
        }
        
        .ticket-list {
            list-style: none;
            margin: 20px 0;
        }
        
        .ticket-item {
            background: white;
            border-radius: 10px;
            padding: 15px;
            margin-bottom: 10px;
            border-left: 4px solid #4caf50;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .ticket-number {
            font-family: monospace;
            font-weight: bold;
            color: #2e7d32;
            font-size: 16px;
        }
        
        .ticket-seat {
            color: #666;
            font-size: 14px;
        }
        
        .total-amount {
            font-size: 24px;
            font-weight: bold;
            color: #2e7d32;
            margin: 20px 0;
            padding-top: 20px;
            border-top: 2px solid #e0e0e0;
        }
        
        .actions {
            display: flex;
            gap: 15px;
            justify-content: center;
            margin-top: 30px;
            flex-wrap: wrap;
        }
        
        .btn {
            padding: 15px 30px;
            border: none;
            border-radius: 25px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 10px;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #2e7d32 0%, #66bb6a 100%);
            color: white;
        }
        
        .btn-secondary {
            background: #f5f5f5;
            color: #333;
        }
        
        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.2);
        }
        
        .important-note {
            background: #fff8e1;
            border-radius: 10px;
            padding: 15px;
            margin-top: 25px;
            border-left: 4px solid #ffb300;
            text-align: left;
        }
        
        .important-note h4 {
            color: #ff8f00;
            margin-bottom: 10px;
        }
        
        .qr-codes {
            display: flex;
            flex-wrap: wrap;
            gap: 15px;
            justify-content: center;
            margin: 20px 0;
        }
        
        .qr-code-item {
            text-align: center;
        }
        
        .qr-code {
            width: 120px;
            height: 120px;
            background: #f0f0f0;
            border-radius: 10px;
            margin: 0 auto 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 12px;
            color: #666;
        }
        
        .ticket-count {
            font-size: 14px;
            color: #666;
            margin: 10px 0;
        }
    </style>
</head>
<body>
    <div class="success-container">
        <div class="logo">🎬 CinemaX</div>
        
        <div class="success-icon">✅</div>
        
        <h1 class="success-title">Покупка успешно завершена!</h1>
        
        <p class="success-message">
            Ваши билеты успешно приобретены. Информация отправлена на вашу почту.
        </p>
        
        <div class="ticket-summary">
            <h3 style="color: #2e7d32; margin-bottom: 20px;">Детали покупки:</h3>
            
            <p><strong>Фильм:</strong> <?php echo htmlspecialchars($purchaseData['session_title']); ?></p>
            <p class="ticket-count"><strong>Количество билетов:</strong> <?php echo count($tickets); ?></p>
            
            <ul class="ticket-list">
                <?php foreach ($tickets as $ticket): ?>
                    <li class="ticket-item">
                        <div>
                            <div class="ticket-number"><?php echo htmlspecialchars($ticket['ticket_number']); ?></div>
                            <div class="ticket-seat">
                                Ряд <?php echo $ticket['row_num']; ?>, Место <?php echo $ticket['seat_num']; ?> | 
                                <?php echo date('H:i', strtotime($ticket['start_time'])); ?>
                            </div>
                        </div>
                        <div style="font-weight: bold; color: #2e7d32;">
                            <?php echo number_format($ticket['price'], 0, '.', ' '); ?> ₽
                        </div>
                    </li>
                <?php endforeach; ?>
            </ul>
            
            <div class="total-amount">
                Итого к оплате: <?php echo number_format($purchaseData['total_price'], 0, '.', ' '); ?> ₽
            </div>
        </div>
        
        <div class="important-note">
            <h4>⚠️ Важная информация:</h4>
            <p>• Сохраните номера билетов или QR-коды</p>
            <p>• Приходите за 15 минут до начала сеанса</p>
            <p>• При посещении покажите QR-код на экране телефона</p>
            <p>• Билеты можно посмотреть в личном кабинете</p>
        </div>
        
        <div class="actions">
            <a href="profile.php" class="btn btn-primary">
                📋 Перейти в личный кабинет
            </a>
            <a href="index.php" class="btn btn-secondary">
                🎬 Смотреть другие фильмы
            </a>
            <button onclick="window.print()" class="btn btn-secondary">
                🖨️ Распечатать билеты
            </button>
        </div>
        
        <p style="margin-top: 30px; color: #666; font-size: 14px;">
            Спасибо за покупку! Желаем приятного просмотра!
        </p>
    </div>
    
    <script>
        // Генерация QR-кодов для билетов
        document.addEventListener('DOMContentLoaded', function() {
            const qrCodesContainer = document.createElement('div');
            qrCodesContainer.className = 'qr-codes';
            
            <?php foreach ($tickets as $ticket): ?>
                const qrItem = document.createElement('div');
                qrItem.className = 'qr-code-item';
                
                const qrCode = document.createElement('div');
                qrCode.className = 'qr-code';
                qrCode.innerHTML = `
                    <div style="text-align: center; padding: 10px;">
                        <div style="font-weight: bold; margin-bottom: 5px;"><?php echo substr($ticket['ticket_number'], 0, 8); ?>...</div>
                        <div style="font-size: 10px;">Кинотеатр</div>
                    </div>
                `;
                
                const ticketNum = document.createElement('div');
                ticketNum.style.fontSize = '12px';
                ticketNum.style.marginTop = '5px';
                ticketNum.textContent = '<?php echo $ticket['ticket_number']; ?>';
                
                qrItem.appendChild(qrCode);
                qrItem.appendChild(ticketNum);
                qrCodesContainer.appendChild(qrItem);
            <?php endforeach; ?>
            
            document.querySelector('.ticket-summary').appendChild(qrCodesContainer);
        });
    </script>
</body>
</html>