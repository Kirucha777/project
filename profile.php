<?php
require_once 'config.php';

// Проверяем авторизацию
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$pdo = getDB();

// Получаем информацию о пользователе
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch();

// Получаем историю заказов
$stmt = $pdo->prepare("
    SELECT t.*, s.start_time, m.title, se.row_num, se.seat_num, se.section
    FROM tickets t
    JOIN sessions s ON t.session_id = s.id
    JOIN movies m ON s.movie_id = m.id
    JOIN seats se ON t.seat_id = se.id
    WHERE t.user_id = ?
    ORDER BY t.purchase_time DESC
    LIMIT 50
");
$stmt->execute([$_SESSION['user_id']]);
$tickets = $stmt->fetchAll();

// Обработка запроса на формирование PDF
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['generate_ticket'])) {
    $ticketId = $_POST['ticket_id'] ?? 0;
    
    if ($ticketId) {
        // Добавляем задание в очередь на генерацию PDF
        $stmt = $pdo->prepare("
            INSERT INTO jobs (queue, payload, available_at) 
            VALUES ('generate_pdf', ?, NOW())
        ");
        $payload = json_encode([
            'ticket_id' => $ticketId,
            'user_id' => $_SESSION['user_id']
        ]);
        $stmt->execute([$payload]);
        
        $success = "Задание на генерацию билета добавлено в очередь. Билет будет доступен через несколько секунд.";
    }
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Личный кабинет - Кинотеатр</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #283593 0%, #5c6bc0 100%);
            min-height: 100vh;
            color: #333;
        }
        
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }
        
        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            background: rgba(255, 255, 255, 0.95);
            padding: 20px 30px;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
            margin-bottom: 30px;
            backdrop-filter: blur(10px);
        }
        
        .logo {
            font-size: 28px;
            font-weight: bold;
            color: #283593;
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .nav {
            display: flex;
            gap: 15px;
        }
        
        .nav a {
            color: #333;
            text-decoration: none;
            padding: 10px 20px;
            border-radius: 25px;
            transition: all 0.3s ease;
            font-weight: 500;
        }
        
        .nav a:hover, .nav a.active {
            background: linear-gradient(135deg, #283593 0%, #5c6bc0 100%);
            color: white;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(40, 53, 147, 0.4);
        }
        
        .profile-container {
            display: grid;
            grid-template-columns: 300px 1fr;
            gap: 30px;
        }
        
        @media (max-width: 768px) {
            .profile-container {
                grid-template-columns: 1fr;
            }
        }
        
        .user-info {
            background: rgba(255, 255, 255, 0.95);
            border-radius: 20px;
            padding: 30px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            height: fit-content;
        }
        
        .user-avatar {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            background: linear-gradient(135deg, #283593 0%, #5c6bc0 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 20px;
            font-size: 48px;
            color: white;
        }
        
        .user-name {
            text-align: center;
            font-size: 24px;
            color: #283593;
            margin-bottom: 10px;
            font-weight: 600;
        }
        
        .user-email {
            text-align: center;
            color: #666;
            margin-bottom: 20px;
        }
        
        .user-role {
            display: inline-block;
            background: #e3f2fd;
            color: #1976d2;
            padding: 5px 15px;
            border-radius: 20px;
            font-size: 14px;
            margin: 0 auto;
            display: table;
        }
        
        .stats {
            margin-top: 30px;
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
        }
        
        .stat-item {
            text-align: center;
            padding: 15px;
            background: #f5f5f5;
            border-radius: 10px;
        }
        
        .stat-number {
            font-size: 24px;
            font-weight: bold;
            color: #283593;
            margin-bottom: 5px;
        }
        
        .stat-label {
            font-size: 14px;
            color: #666;
        }
        
        .orders-section {
            background: rgba(255, 255, 255, 0.95);
            border-radius: 20px;
            padding: 30px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
        }
        
        .section-title {
            font-size: 28px;
            color: #283593;
            margin-bottom: 25px;
            padding-bottom: 15px;
            border-bottom: 2px solid #f0f0f0;
        }
        
        .tickets-table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .tickets-table th {
            background: #f5f5f5;
            padding: 15px;
            text-align: left;
            font-weight: 600;
            color: #283593;
            border-bottom: 2px solid #e0e0e0;
        }
        
        .tickets-table td {
            padding: 15px;
            border-bottom: 1px solid #f0f0f0;
        }
        
        .tickets-table tr:hover {
            background: #f9f9f9;
        }
        
        .ticket-number {
            font-family: monospace;
            font-weight: bold;
            color: #283593;
        }
        
        .status-active {
            background: #e8f5e9;
            color: #2e7d32;
            padding: 5px 10px;
            border-radius: 15px;
            font-size: 12px;
            font-weight: 500;
        }
        
        .status-cancelled {
            background: #ffebee;
            color: #c62828;
            padding: 5px 10px;
            border-radius: 15px;
            font-size: 12px;
            font-weight: 500;
        }
        
        .status-used {
            background: #f5f5f5;
            color: #757575;
            padding: 5px 10px;
            border-radius: 15px;
            font-size: 12px;
            font-weight: 500;
        }
        
        .action-btn {
            background: linear-gradient(135deg, #283593 0%, #5c6bc0 100%);
            color: white;
            border: none;
            padding: 8px 15px;
            border-radius: 8px;
            cursor: pointer;
            font-size: 14px;
            transition: all 0.3s ease;
            margin: 5px;
        }
        .action-btnn {
            background: linear-gradient(135deg, #283593 0%, #5c6bc0 100%);
            color: white;
            border: none;
            padding: 8px 15px;
            border-radius: 8px;
            cursor: pointer;
            font-size: 14px;
            transition: all 0.3s ease;
            margin: 5px;
            margin-left: -1px ;
        }
        
        .action-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(40, 53, 147, 0.4);
        }
        
        .no-tickets {
            text-align: center;
            padding: 40px;
            color: #666;
            font-size: 18px;
        }
        
        .no-tickets a {
            color: #283593;
            text-decoration: none;
            font-weight: 500;
        }
        
        .no-tickets a:hover {
            text-decoration: underline;
        }
        
        .success-message {
            background: #e8f5e9;
            color: #2e7d32;
            padding: 15px;
            border-radius: 10px;
            margin-bottom: 20px;
            border-left: 4px solid #2e7d32;
        }
        
        .seat-info {
            font-size: 12px;
            color: #666;
            margin-top: 5px;
        }
        
        .pdf-link {
            display: inline-block;
            margin-top: 10px;
            color: #283593;
            text-decoration: none;
            font-size: 14px;
        }
        
        .pdf-link:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <a href="index.php" class="logo">🎬 CinemaX</a>
            <div class="nav">
                <a href="index.php">Главная</a>
                <a href="profile.php" class="active">Личный кабинет</a>
                <?php if ($_SESSION['user_role'] === 'admin'): ?>
                    <a href="admin.php">Админ-панель</a>
                <?php endif; ?>
                <a href="logout.php">Выйти</a>
            </div>
        </div>
        
        <?php if (isset($success)): ?>
            <div class="success-message">
                <?php echo htmlspecialchars($success); ?>
            </div>
        <?php endif; ?>
        
        <div class="profile-container">
            <div class="user-info">
                <div class="user-avatar">
                    <?php echo strtoupper(substr($user['full_name'], 0, 1)); ?>
                </div>
                <h2 class="user-name"><?php echo htmlspecialchars($user['full_name']); ?></h2>
                <p class="user-email"><?php echo htmlspecialchars($user['email']); ?></p>
                <div class="user-role">
                    <?php echo $user['role'] === 'admin' ? '👑 Администратор' : '👤 Пользователь'; ?>
                </div>
                
                <div class="stats">
                    <div class="stat-item">
                        <div class="stat-number">
                            <?php 
                                $stmt = $pdo->prepare("SELECT COUNT(*) FROM tickets WHERE user_id = ?");
                                $stmt->execute([$_SESSION['user_id']]);
                                echo $stmt->fetchColumn();
                            ?>
                        </div>
                        <div class="stat-label">Всего билетов</div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-number">
                            <?php 
                                $stmt = $pdo->prepare("SELECT COUNT(*) FROM tickets WHERE user_id = ? AND status = 'active'");
                                $stmt->execute([$_SESSION['user_id']]);
                                echo $stmt->fetchColumn();
                            ?>
                        </div>
                        <div class="stat-label">Активные</div>
                    </div>
                </div>
            </div>
            
            <div class="orders-section">
                <h2 class="section-title">История заказов</h2>
                
                <?php if (empty($tickets)): ?>
                    <div class="no-tickets">
                        <p>У вас еще нет покупок</p>
                        <p><a href="index.php">Перейти к выбору фильма →</a></p>
                    </div>
                <?php else: ?>
                    <div style="overflow-x: auto;">
                        <table class="tickets-table">
                            <thead>
                                <tr>
                                    <th>Билет №</th>
                                    <th>Фильм</th>
                                    <th>Время сеанса</th>
                                    <th>Место</th>
                                    <th>Цена</th>
                                    <th>Статус</th>
                                    <th>Дата покупки</th>
                                    <th>Действия</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($tickets as $ticket): ?>
                                    <tr>
                                        <td>
                                            <span class="ticket-number"><?php echo htmlspecialchars($ticket['ticket_number']); ?></span>
                                            <div class="seat-info">
                                                Секция <?php echo $ticket['section']; ?>
                                            </div>
                                        </td>
                                        <td>
                                            <strong><?php echo htmlspecialchars($ticket['title']); ?></strong>
                                        </td>
                                        <td>
                                            <?php echo date('d.m.Y H:i', strtotime($ticket['start_time'])); ?>
                                        </td>
                                        <td>
                                            Ряд <?php echo $ticket['row_num']; ?>, Место <?php echo $ticket['seat_num']; ?>
                                        </td>
                                        <td>
                                            <?php echo number_format($ticket['price'], 0, '.', ' '); ?> ₽
                                        </td>
                                        <td>
                                            <?php 
                                                $statusClass = '';
                                                $statusText = '';
                                                switch ($ticket['status']) {
                                                    case 'active':
                                                        $statusClass = 'status-active';
                                                        $statusText = 'Активен';
                                                        break;
                                                    case 'cancelled':
                                                        $statusClass = 'status-cancelled';
                                                        $statusText = 'Отменен';
                                                        break;
                                                    case 'used':
                                                        $statusClass = 'status-used';
                                                        $statusText = 'Использован';
                                                        break;
                                                }
                                            ?>
                                            <span class="<?php echo $statusClass; ?>"><?php echo $statusText; ?></span>
                                        </td>
                                        <td>
                                            <?php echo date('d.m.Y H:i', strtotime($ticket['purchase_time'])); ?>
                                        </td>
                                        <td>
                                            <form method="POST" style="display: inline;">
                                                <input type="hidden" name="ticket_id" value="<?php echo $ticket['id']; ?>">
                                                <button type="submit" name="generate_ticket" class="action-btn">
                                                     Печать
                                                </button>
                                            </form>
                                            
                                            <?php if ($ticket['status'] === 'active' && strtotime($ticket['start_time']) > time()): ?>
                                                <form method="POST" action="cancel_ticket.php" style="display: inline; margin-left: 5px;">
                                                    <input type="hidden" name="ticket_id" value="<?php echo $ticket['id']; ?>">
                                                    <button type="submit" class="action-btnn" style="background: #f44336;">
                                                         Отмена
                                                    </button>
                                                </form>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</body>
</html>