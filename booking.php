<?php
require_once 'config.php';

// Проверяем авторизацию
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

// Получаем ID сеанса
$sessionId = $_GET['session'] ?? 0;
if (!$sessionId) {
    header('Location: index.php');
    exit();
}

// Получаем информацию о сеансе
$pdo = getDB();
$stmt = $pdo->prepare("
    SELECT s.*, m.title, m.duration, m.poster_url 
    FROM sessions s 
    JOIN movies m ON s.movie_id = m.id 
    WHERE s.id = ?
");
$stmt->execute([$sessionId]);
$session = $stmt->fetch();

if (!$session) {
    header('Location: index.php');
    exit();
}

// Проверяем, что сеанс еще не начался
if (strtotime($session['start_time']) <= time()) {
    die("Сеанс уже начался или завершился");
}

// Проверяем, не превышен ли лимит билетов (5 на пользователя)
$stmt = $pdo->prepare("
    SELECT COUNT(*) as ticket_count 
    FROM tickets 
    WHERE user_id = ? 
    AND DATE(purchase_time) = CURDATE()
    AND status = 'active'
");
$stmt->execute([$_SESSION['user_id']]);
$ticketCount = $stmt->fetch()['ticket_count'];

if ($ticketCount >= MAX_TICKETS_PER_USER) {
    die("Вы достигли дневного лимита в 5 билетов");
}

// Обработка бронирования/покупки
$selectedSeats = [];
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $seatsInput = $_POST['seats'] ?? '';
    
    if (!empty($seatsInput) && in_array($action, ['book', 'buy'])) {
        // Преобразуем строку в массив
        if (is_string($seatsInput)) {
            $selectedSeats = array_map('intval', explode(',', $seatsInput));
            $selectedSeats = array_filter($selectedSeats); // Убираем пустые значения
        } elseif (is_array($seatsInput)) {
            $selectedSeats = array_map('intval', $seatsInput);
        }
        
        if (!empty($selectedSeats)) {
            // Проверяем доступность мест
            $placeholders = implode(',', array_fill(0, count($selectedSeats), '?'));
            $stmt = $pdo->prepare("
                SELECT id, status 
                FROM seats 
                WHERE id IN ($placeholders) 
                AND session_id = ?
                AND (status = 'free' OR (status = 'booked' AND booked_by = ? AND booked_until > NOW()))
            ");
            $params = array_merge($selectedSeats, [$sessionId, $_SESSION['user_id']]);
            $stmt->execute($params);
            $availableSeats = $stmt->fetchAll(PDO::FETCH_COLUMN);
            
            if (count($availableSeats) === count($selectedSeats)) {
                if ($action === 'book') {
                    // Бронируем места на 15 минут
                    $bookedUntil = date('Y-m-d H:i:s', time() + 900); // 15 минут
                    
                    $stmt = $pdo->prepare("
                        UPDATE seats 
                        SET status = 'booked', 
                            booked_by = ?, 
                            booked_until = ? 
                        WHERE id IN ($placeholders)
                    ");
                    $params = array_merge([$_SESSION['user_id'], $bookedUntil], $selectedSeats);
                    $stmt->execute($params);
                    
                    // Сбрасываем кеш
                    clearCache("seats_session_{$sessionId}");
                    
                    $success = "Места успешно забронированы на 15 минут";
                } else { // buy
                    // Покупаем билеты
                    $ticketNumbers = [];
                    
                    // Начинаем транзакцию
                    $pdo->beginTransaction();
                    
                    try {
                        foreach ($selectedSeats as $seatId) {
                            // Генерируем уникальный номер билета
                            $ticketNumber = 'T' . date('Ymd') . str_pad(mt_rand(1, 99999), 5, '0', STR_PAD_LEFT);
                            
                            // Создаем билет
                            $stmt = $pdo->prepare("
                                INSERT INTO tickets (user_id, session_id, seat_id, ticket_number, price, status)
                                VALUES (?, ?, ?, ?, ?, 'active')
                            ");
                            $stmt->execute([
                                $_SESSION['user_id'],
                                $sessionId,
                                $seatId,
                                $ticketNumber,
                                $session['price']
                            ]);
                            
                            // Обновляем статус места
                            $stmt = $pdo->prepare("
                                UPDATE seats 
                                SET status = 'sold', 
                                    booked_by = NULL, 
                                    booked_until = NULL 
                                WHERE id = ?
                            ");
                            $stmt->execute([$seatId]);
                            
                            $ticketNumbers[] = $ticketNumber;
                        }
                        
                        $pdo->commit();
                        
                        // Сбрасываем кеш
                        clearCache("seats_session_{$sessionId}");
                        
                        // Перенаправляем на страницу успешной покупки
                        $_SESSION['purchase_success'] = [
                            'ticket_numbers' => $ticketNumbers,
                            'session_title' => $session['title'],
                            'total_price' => count($selectedSeats) * $session['price']
                        ];
                        
                        header('Location: purchase_success.php');
                        exit();
                        
                    } catch (Exception $e) {
                        $pdo->rollBack();
                        $error = "Ошибка при покупке билетов: " . $e->getMessage();
                    }
                }
            } else {
                $error = "Некоторые места уже заняты. Пожалуйста, обновите страницу и выберите другие места.";
            }
        } else {
            $error = "Выберите места для бронирования или покупки";
        }
    } else {
        $error = "Выберите места для бронирования или покупки";
    }
}

// Получаем места для сеанса
$cacheKey = "seats_session_{$sessionId}";
$seats = getCache($cacheKey);

if (!$seats) {
    $stmt = $pdo->prepare("
        SELECT * FROM seats 
        WHERE session_id = ? 
        ORDER BY row_num, seat_num
    ");
    $stmt->execute([$sessionId]);
    $seats = $stmt->fetchAll();
    setCache($cacheKey, $seats);
}

// Очищаем устаревшие бронирования
$stmt = $pdo->prepare("
    UPDATE seats 
    SET status = 'free', 
        booked_by = NULL, 
        booked_until = NULL 
    WHERE session_id = ? 
    AND status = 'booked' 
    AND booked_until < NOW()
");
$stmt->execute([$sessionId]);

// Группируем места по рядам
$rows = [];
foreach ($seats as $seat) {
    $rows[$seat['row_num']][] = $seat;
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Выбор мест - <?php echo htmlspecialchars($session['title']); ?></title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #1a237e 0%, #311b92 100%);
            min-height: 100vh;
            color: #333;
        }
        
        .container {
            max-width: 1400px;
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
            color: #1a237e;
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
        
        .nav a:hover {
            background: linear-gradient(135deg, #1a237e 0%, #311b92 100%);
            color: white;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(26, 35, 126, 0.4);
        }
        
        .booking-container {
            display: grid;
            grid-template-columns: 1fr 400px;
            gap: 30px;
        }
        
        @media (max-width: 1024px) {
            .booking-container {
                grid-template-columns: 1fr;
            }
        }
        
        .movie-info {
            background: rgba(255, 255, 255, 0.95);
            border-radius: 20px;
            padding: 30px;
            margin-bottom: 20px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
        }
        
        .movie-title {
            font-size: 32px;
            color: #1a237e;
            margin-bottom: 10px;
        }
        
        .session-info {
            color: #666;
            font-size: 18px;
            margin-bottom: 20px;
        }
        
        .hall-container {
            background: rgba(255, 255, 255, 0.95);
            border-radius: 20px;
            padding: 40px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            width: 900px;
        }
        
        .screen {
            background: #e0e0e0;
            padding: 20px;
            text-align: center;
            border-radius: 10px;
            margin-bottom: 40px;
            font-weight: bold;
            color: #333;
            box-shadow: inset 0 0 10px rgba(0,0,0,0.2);
        }
        
        .rows-container {
            display: flex;
            flex-direction: column;
            gap: 15px;
            margin-bottom: 30px;
        }
        
        .row {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .row-label {
            width: 40px;
            text-align: center;
            font-weight: bold;
            color: #1a237e;
        }
        
        .seats {
            display: flex;
            gap: 8px;
        }
        
        .seat {
            width: 60px;
            height: 60px;
            border-radius: 8px;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            font-size: 14px;
            transition: all 0.3s ease;
            user-select: none;
            position: relative;
        }
        
        .seat.free {
            background: #4caf50;
            color: white;
        }
        
        .seat.free:hover {
            background: #388e3c;
            transform: scale(1.1);
        }
        
        .seat.selected {
            background: #ff9800;
            color: white;
            transform: scale(1.1);
        }
        
        .seat.booked {
            background: #f44336;
            color: white;
            cursor: not-allowed;
        }
        
        .seat.sold {
            background: #9e9e9e;
            color: white;
            cursor: not-allowed;
        }
        
        .seat-label {
            position: absolute;
            top: -25px;
            background: #333;
            color: white;
            padding: 2px 5px;
            border-radius: 3px;
            font-size: 12px;
            opacity: 0;
            transition: opacity 0.3s ease;
            pointer-events: none;
            white-space: nowrap;
        }
        
        .seat:hover .seat-label {
            opacity: 1;
        }
        
        .selected-seats {
            background: rgba(255, 255, 255, 0.95);
            border-radius: 20px;
            padding: 30px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            position: sticky;
            top: 20px;
        }
        
        .selected-title {
            font-size: 24px;
            color: #1a237e;
            margin-bottom: 20px;
        }
        
        .selected-list {
            list-style: none;
            margin-bottom: 20px;
        }
        
        .selected-list li {
            padding: 10px;
            background: #f5f5f5;
            border-radius: 10px;
            margin-bottom: 10px;
            display: flex;
            justify-content: space-between;
        }
        
        .total {
            font-size: 20px;
            font-weight: bold;
            color: #1a237e;
            margin-bottom: 20px;
            padding-top: 20px;
            border-top: 2px solid #eee;
        }
        
        .buttons {
            display: flex;
            flex-direction: column;
            gap: 15px;
        }
        
        .btn {
            padding: 15px;
            border: none;
            border-radius: 10px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .btn-book {
            background: linear-gradient(135deg, #ff9800 0%, #f57c00 100%);
            color: white;
        }
        
        .btn-buy {
            background: linear-gradient(135deg, #4caf50 0%, #388e3c 100%);
            color: white;
        }
        
        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.2);
        }
        
        .legend {
            display: flex;
            gap: 15px;
            margin-top: 20px;
            flex-wrap: wrap;
        }
        
        .legend-item {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 14px;
        }
        
        .legend-color {
            width: 20px;
            height: 20px;
            border-radius: 4px;
        }
        
        .error {
            background: #ffebee;
            color: #c62828;
            padding: 15px;
            border-radius: 10px;
            margin-bottom: 20px;
            border-left: 4px solid #c62828;
        }
        
        .success {
            background: #e8f5e9;
            color: #2e7d32;
            padding: 15px;
            border-radius: 10px;
            margin-bottom: 20px;
            border-left: 4px solid #2e7d32;
        }
        
        .info-box {
            background: #e3f2fd;
            border-radius: 15px;
            padding: 20px;
            margin-top: 20px;
            border-left: 4px solid #2196f3;
        }
        
        .info-box h4 {
            color: #1976d2;
            margin-bottom: 10px;
        }
        
        .sections {
            display: flex;
            justify-content: space-between;
            margin-bottom: 20px;
        }
        
        .section {
            background: rgba(255,255,255,0.1);
            color: black;
            padding: 10px 20px;
            border-radius: 10px;
            font-weight: bold;
        }
        
        .sections-container {
            margin: 20px 0;
        }
        
        .section-title {
            text-align: center;
            color: white;
            margin: 10px 0;
            font-weight: bold;
            padding: 5px;
            border-radius: 5px;
            background: rgba(255,255,255,0.1);
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <a href="index.php" class="logo">🎬 CinemaX</a>
            <div class="nav">
                <a href="index.php">Главная</a>
                <a href="profile.php">Личный кабинет</a>
                <?php if ($_SESSION['user_role'] === 'admin'): ?>
                    <a href="admin.php">Админ-панель</a>
                <?php endif; ?>
                <a href="logout.php">Выйти</a>
            </div>
        </div>
        
        <div class="movie-info">
            <h1 class="movie-title"><?php echo htmlspecialchars($session['title']); ?></h1>
            <div class="session-info">
                📅 <?php echo date('d.m.Y', strtotime($session['start_time'])); ?> 
                | 🕒 <?php echo date('H:i', strtotime($session['start_time'])); ?> 
                | 💰 <?php echo number_format($session['price'], 0, '.', ' '); ?> ₽
                | ⏱️ <?php echo floor($session['duration'] / 60); ?>ч <?php echo $session['duration'] % 60; ?>мин
            </div>
            <p>Осталось мест: <strong><?php 
                $freeSeats = 0;
                foreach ($seats as $seat) {
                    if ($seat['status'] === 'free' || ($seat['status'] === 'booked' && $seat['booked_by'] == $_SESSION['user_id'] && strtotime($seat['booked_until']) > time())) {
                        $freeSeats++;
                    }
                }
                echo $freeSeats; 
            ?></strong> из 80</p>
        </div>
        
        <?php if (!empty($error)): ?>
            <div class="error">
                <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>
        
        <?php if (!empty($success)): ?>
            <div class="success">
                <?php echo htmlspecialchars($success); ?>
            </div>
        <?php endif; ?>
        
        <div class="booking-container">
            <div class="hall-container">
                <div class="screen">ЭКРАН</div>
                
                <div class="sections">
                    <div class="section">Секция 1</div>
                    <div class="section">Секция 2</div>
                </div>
                
                <div class="rows-container" id="seats-container">
                    <?php foreach ($rows as $rowNum => $rowSeats): ?>
                        <div class="row">
                            <div class="row-label">Ряд <?php echo $rowNum; ?></div>
                            <div class="seats">
                                <?php 
                                $seatsInRow = array_chunk($rowSeats, 4);
                                foreach ($seatsInRow as $chunkIndex => $seatChunk): 
                                    if ($chunkIndex > 0): ?>
                                        <div style="width: 50px;"></div> <!-- разделитель между секциями -->
                                    <?php endif; ?>
                                    <?php foreach ($seatChunk as $seat): 
                                        $seatClass = '';
                                        $title = '';
                                        
                                        if ($seat['status'] === 'sold') {
                                            $seatClass = 'sold';
                                            $title = 'Продано';
                                        } elseif ($seat['status'] === 'booked') {
                                            if ($seat['booked_by'] == $_SESSION['user_id'] && strtotime($seat['booked_until']) > time()) {
                                                $seatClass = 'selected';
                                                $title = 'Забронировано вами';
                                            } else {
                                                $seatClass = 'booked';
                                                $title = 'Забронировано';
                                            }
                                        } else {
                                            $seatClass = 'free';
                                            $title = 'Ряд ' . $seat['row_num'] . ', Место ' . $seat['seat_num'];
                                        }
                                    ?>
                                        <div class="seat <?php echo $seatClass; ?>" 
                                             data-seat-id="<?php echo $seat['id']; ?>"
                                             data-row="<?php echo $seat['row_num']; ?>"
                                             data-seat="<?php echo $seat['seat_num']; ?>"
                                             data-status="<?php echo $seat['status']; ?>"
                                             title="<?php echo htmlspecialchars($title); ?>">
                                            <?php echo $seat['seat_num']; ?>
                                            <span class="seat-label">
                                                Ряд <?php echo $seat['row_num']; ?>, Место <?php echo $seat['seat_num']; ?>
                                            </span>
                                        </div>
                                    <?php endforeach; ?>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        
                        <?php if ($rowNum == 5): ?>
                            <div class="sections">
                                <div class="section">Секция 3</div>
                                <div class="section">Секция 4</div>
                            </div>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </div>
                
                <div class="legend">
                    <div class="legend-item">
                        <div class="legend-color" style="background: #4caf50;"></div>
                        <span>Свободно</span>
                    </div>
                    <div class="legend-item">
                        <div class="legend-color" style="background: #ff9800;"></div>
                        <span>Ваше бронирование</span>
                    </div>
                    <div class="legend-item">
                        <div class="legend-color" style="background: #f44336;"></div>
                        <span>Занято</span>
                    </div>
                    <div class="legend-item">
                        <div class="legend-color" style="background: #9e9e9e;"></div>
                        <span>Продано</span>
                    </div>
                </div>
            </div>
            
            <div class="selected-seats">
                <h3 class="selected-title">Выбранные места</h3>
                
                <ul class="selected-list" id="selected-list">
                    <!-- Сюда будут добавляться выбранные места -->
                </ul>
                
                <div class="total">
                    Итого: <span id="total-price">0</span> ₽
                </div>
                
                <form method="POST" action="" class="buttons" id="booking-form">
                    <input type="hidden" name="seats" id="selected-seats-input" value="">
                    
                    <button type="submit" name="action" value="book" class="btn btn-book">
                        🕐 Забронировать на 15 минут
                    </button>
                    
                    <button type="submit" name="action" value="buy" class="btn btn-buy">
                        💳 Купить билеты
                    </button>
                </form>
                
                <div class="info-box">
                    <h4>ℹ️ Информация:</h4>
                    <p>• Бронирование действует 15 минут</p>
                    <p>• Максимум 5 билетов в день на одного пользователя</p>
                    <p>• Вы можете выбрать несколько мест за раз</p>
                    <p>• При покупке бронирование снимается</p>
                </div>
            </div>
        </div>
    </div>
    
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const seats = document.querySelectorAll('.seat.free, .seat.selected');
            const selectedSeats = new Set();
            const selectedSeatsInput = document.getElementById('selected-seats-input');
            const selectedList = document.getElementById('selected-list');
            const totalPriceElement = document.getElementById('total-price');
            const pricePerSeat = <?php echo $session['price']; ?>;
            
            // Обновляем отображение выбранных мест
            function updateSelectedDisplay() {
                // Обновляем список
                selectedList.innerHTML = '';
                let totalPrice = 0;
                
                selectedSeats.forEach(seatId => {
                    const seatElement = document.querySelector(`.seat[data-seat-id="${seatId}"]`);
                    if (seatElement) {
                        const row = seatElement.dataset.row;
                        const seat = seatElement.dataset.seat;
                        const listItem = document.createElement('li');
                        listItem.innerHTML = `
                            <span>Ряд ${row}, Место ${seat}</span>
                            <span>${pricePerSeat.toLocaleString('ru-RU')} ₽</span>
                        `;
                        selectedList.appendChild(listItem);
                        totalPrice += pricePerSeat;
                    }
                });
                
                // Обновляем общую сумму
                totalPriceElement.textContent = totalPrice.toLocaleString('ru-RU');
                
                // Обновляем скрытое поле формы
                selectedSeatsInput.value = Array.from(selectedSeats).join(',');
            }
            
            // Обработка клика на место
            seats.forEach(seat => {
                seat.addEventListener('click', function() {
                    const seatId = this.dataset.seatId;
                    const status = this.dataset.status;
                    
                    // Можно выбирать только свободные места или свои бронирования
                    if (status === 'free' || (status === 'booked' && this.classList.contains('selected'))) {
                        if (selectedSeats.has(seatId)) {
                            // Убираем из выбранных
                            selectedSeats.delete(seatId);
                            this.classList.remove('selected');
                        } else {
                            // Добавляем в выбранные
                            if (selectedSeats.size < 5) { // Максимум 5 мест
                                selectedSeats.add(seatId);
                                this.classList.add('selected');
                            } else {
                                alert('Вы можете выбрать максимум 5 мест');
                            }
                        }
                        
                        updateSelectedDisplay();
                    }
                });
            });
            
            // Предзаполняем уже забронированные пользователем места
            document.querySelectorAll('.seat.selected').forEach(seat => {
                const seatId = seat.dataset.seatId;
                selectedSeats.add(seatId);
            });
            
            updateSelectedDisplay();
            
            // Подтверждение покупки
            document.querySelector('button[value="buy"]').addEventListener('click', function(e) {
                if (selectedSeats.size === 0) {
                    e.preventDefault();
                    alert('Выберите хотя бы одно место');
                    return false;
                }
                
                if (!confirm(`Подтвердите покупку ${selectedSeats.size} билетов на сумму ${(selectedSeats.size * pricePerSeat).toLocaleString('ru-RU')} ₽`)) {
                    e.preventDefault();
                    return false;
                }
            });
            
            // Подтверждение бронирования
            document.querySelector('button[value="book"]').addEventListener('click', function(e) {
                if (selectedSeats.size === 0) {
                    e.preventDefault();
                    alert('Выберите хотя бы одно место');
                    return false;
                }
                
                if (!confirm(`Забронировать ${selectedSeats.size} мест на 15 минут?`)) {
                    e.preventDefault();
                    return false;
                }
            });
        });
    </script>
</body>
</html>