<?php
require_once 'config.php';

// Проверяем авторизацию и права администратора
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    header('Location: index.php');
    exit();
}

$pdo = getDB();
$action = $_GET['action'] ?? '';

// Обработка действий администратора
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    switch ($_POST['action'] ?? '') {
        case 'add_movie':
            $title = trim($_POST['title'] ?? '');
            $description = trim($_POST['description'] ?? '');
            $duration = intval($_POST['duration'] ?? 0);
            $posterUrl = trim($_POST['poster_url'] ?? '');
            
            if ($title && $duration > 0) {
                $stmt = $pdo->prepare("
                    INSERT INTO movies (title, description, duration, poster_url) 
                    VALUES (?, ?, ?, ?)
                ");
                $stmt->execute([$title, $description, $duration, $posterUrl]);
                $success = "Фильм успешно добавлен";
                clearCache(); // Сбрасываем весь кеш
            }
            break;
            
        case 'add_session':
            $movieId = intval($_POST['movie_id'] ?? 0);
            $startTime = $_POST['start_time'] ?? '';
            $price = floatval($_POST['price'] ?? 0);
            
            if ($movieId && $startTime && $price > 0) {
                $stmt = $pdo->prepare("
                    INSERT INTO sessions (movie_id, start_time, price) 
                    VALUES (?, ?, ?)
                ");
                $stmt->execute([$movieId, $startTime, $price]);
                $sessionId = $pdo->lastInsertId();
                
                // Создаем места для сеанса
                require_once 'generate_seats.php';
                generateSeatsForSession($sessionId);
                
                $success = "Сеанс успешно добавлен";
                clearCache(); // Сбрасываем весь кеш
            }
            break;
            
        case 'delete_movie':
            $movieId = intval($_POST['movie_id'] ?? 0);
            if ($movieId) {
                $stmt = $pdo->prepare("DELETE FROM movies WHERE id = ?");
                $stmt->execute([$movieId]);
                $success = "Фильм удален";
                clearCache();
            }
            break;
            
        case 'delete_session':
            $sessionId = intval($_POST['session_id'] ?? 0);
            if ($sessionId) {
                $stmt = $pdo->prepare("DELETE FROM sessions WHERE id = ?");
                $stmt->execute([$sessionId]);
                $success = "Сеанс удален";
                clearCache();
            }
            break;
    }
}

// Получаем статистику
$stats = [];
$stmt = $pdo->query("SELECT COUNT(*) FROM users");
$stats['users'] = $stmt->fetchColumn();

$stmt = $pdo->query("SELECT COUNT(*) FROM movies");
$stats['movies'] = $stmt->fetchColumn();

$stmt = $pdo->query("SELECT COUNT(*) FROM sessions WHERE start_time > NOW()");
$stats['active_sessions'] = $stmt->fetchColumn();

$stmt = $pdo->query("SELECT COUNT(*) FROM tickets WHERE DATE(purchase_time) = CURDATE()");
$stats['tickets_today'] = $stmt->fetchColumn();

$stmt = $pdo->query("SELECT SUM(price) FROM tickets WHERE DATE(purchase_time) = CURDATE()");
$stats['revenue_today'] = $stmt->fetchColumn() ?? 0;

// Получаем список фильмов
$movies = $pdo->query("SELECT * FROM movies ORDER BY title")->fetchAll();

// Получаем список сеансов
$stmt = $pdo->prepare("
    SELECT s.*, m.title as movie_title 
    FROM sessions s 
    JOIN movies m ON s.movie_id = m.id 
    WHERE s.start_time > NOW() 
    ORDER BY s.start_time
");
$stmt->execute();
$sessions = $stmt->fetchAll();

// Получаем последние билеты
$stmt = $pdo->query("
    SELECT t.*, u.email, u.full_name, m.title as movie_title 
    FROM tickets t 
    JOIN users u ON t.user_id = u.id 
    JOIN sessions s ON t.session_id = s.id 
    JOIN movies m ON s.movie_id = m.id 
    ORDER BY t.purchase_time DESC 
    LIMIT 10
");
$recentTickets = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Админ-панель - Кинотеатр</title>
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
            color: #6a11cb;
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
            background: linear-gradient(135deg, #6a11cb 0%, #2575fc 100%);
            color: white;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(27, 94, 32, 0.4);
        }
        
        .admin-container {
            display: grid;
            grid-template-columns: 250px 1fr;
            gap: 30px;
        }
        
        .admin-sidebar {
            background: rgba(255, 255, 255, 0.95);
            border-radius: 20px;
            padding: 25px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            height: fit-content;
        }
        
        .sidebar-title {
            font-size: 20px;
            color: #6a11cb;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid #f0f0f0;
        }
        
        .sidebar-menu {
            list-style: none;
        }
        
        .sidebar-menu li {
            margin-bottom: 10px;
        }
        
        .sidebar-menu a {
            display: block;
            padding: 12px 15px;
            color: #333;
            text-decoration: none;
            border-radius: 10px;
            transition: all 0.3s ease;
        }
        
        .sidebar-menu a:hover, .sidebar-menu a.active {
            background: linear-gradient(135deg, #6a11cb 0%, #2575fc 100%);
            color: white;
        }
        
        .admin-content {
            background: rgba(255, 255, 255, 0.95);
            border-radius: 20px;
            padding: 30px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
        }
        
        .content-title {
            font-size: 28px;
            color: #6a11cb;
            margin-bottom: 25px;
            padding-bottom: 15px;
            border-bottom: 2px solid #f0f0f0;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background: #f5f5f5;
            border-radius: 15px;
            padding: 20px;
            text-align: center;
            transition: all 0.3s ease;
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 20px rgba(0,0,0,0.1);
        }
        
        .stat-number {
            font-size: 36px;
            font-weight: bold;
            color: #6a11cb;
            margin-bottom: 10px;
        }
        
        .stat-label {
            font-size: 16px;
            color: #666;
        }
        
        .form-section {
            background: #f9f9f9;
            border-radius: 15px;
            padding: 25px;
            margin-bottom: 30px;
        }
        
        .form-title {
            font-size: 22px;
            color: #6a11cb;
            margin-bottom: 20px;
        }
        
        .form-group {
            margin-bottom: 15px;
        }
        
        label {
            display: block;
            margin-bottom: 8px;
            color: #333;
            font-weight: 500;
        }
        
        input, select, textarea {
            width: 100%;
            padding: 12px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-size: 16px;
            transition: border-color 0.3s ease;
        }
        
        input:focus, select:focus, textarea:focus {
            outline: none;
            border-color: #6a11cb;
        }
        
        .btn {
            padding: 12px 25px;
            background: linear-gradient(135deg, #6a11cb 0%, #2575fc 100%);
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(27, 94, 32, 0.4);
        }
        
        .btn-danger {
            background: linear-gradient(135deg, #c62828 0%, #d32f2f 100%);
        }
        
        .btn-danger:hover {
            box-shadow: 0 5px 15px rgba(198, 40, 40, 0.4);
        }
        
        .table-container {
            overflow-x: auto;
            margin-bottom: 30px;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        
        th {
            background: #f5f5f5;
            padding: 15px;
            text-align: left;
            font-weight: 600;
            color: #6a11cb;
            border-bottom: 2px solid #e0e0e0;
        }
        
        td {
            padding: 15px;
            border-bottom: 1px solid #f0f0f0;
        }
        
        tr:hover {
            background: #f9f9f9;
        }
        
        .status-badge {
            display: inline-block;
            padding: 5px 10px;
            border-radius: 15px;
            font-size: 12px;
            font-weight: 500;
        }
        
        .status-active {
            background: #e8f5e9;
            color: #6a11cb;
        }
        
        .success-message {
            background: #e8f5e9;
            color: #6a11cb;
            padding: 15px;
            border-radius: 10px;
            margin-bottom: 20px;
            border-left: 4px solid #6a11cb;
        }
        
        .tabs {
            display: flex;
            gap: 10px;
            margin-bottom: 25px;
            border-bottom: 2px solid #f0f0f0;
            padding-bottom: 15px;
        }
        
        .tab {
            padding: 10px 20px;
            background: #f5f5f5;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-size: 16px;
            transition: all 0.3s ease;
        }
        
        .tab:hover, .tab.active {
            background: #6a11cb;
            color: white;
        }
        
        .tab-content {
            display: none;
        }
        
        .tab-content.active {
            display: block;
        }
        
        .empty-state {
            text-align: center;
            padding: 40px;
            color: #666;
            font-size: 18px;
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
                <a href="admin.php" class="active">Админ-панель</a>
                <a href="logout.php">Выйти</a>
            </div>
        </div>
        
        <?php if (isset($success)): ?>
            <div class="success-message">
                <?php echo htmlspecialchars($success); ?>
            </div>
        <?php endif; ?>
        
      
            
            <div class="admin-content">
                <div class="tabs">
                    <button class="tab active" onclick="showTab('dashboard')">📊 Дашборд</button>
                    <button class="tab" onclick="showTab('movies')">🎬 Фильмы</button>
                    <button class="tab" onclick="showTab('sessions')">🕒 Сеансы</button>
                    <button class="tab" onclick="showTab('tickets')">🎫 Билеты</button>
                    <button class="tab" onclick="showTab('users')">👥 Пользователи</button>
                </div>
                
                <!-- Дашборд -->
                <div id="dashboard" class="tab-content active">
                    <h2 class="content-title">Дашборд</h2>
                    
                    <div class="stats-grid">
                        <div class="stat-card">
                            <div class="stat-number"><?php echo $stats['users']; ?></div>
                            <div class="stat-label">Пользователей</div>
                        </div>
                        <div class="stat-card">
                            <div class="stat-number"><?php echo $stats['movies']; ?></div>
                            <div class="stat-label">Фильмов</div>
                        </div>
                        <div class="stat-card">
                            <div class="stat-number"><?php echo $stats['active_sessions']; ?></div>
                            <div class="stat-label">Активных сеансов</div>
                        </div>
                        <div class="stat-card">
                            <div class="stat-number"><?php echo $stats['tickets_today']; ?></div>
                            <div class="stat-label">Билетов сегодня</div>
                        </div>
                        <div class="stat-card">
                            <div class="stat-number"><?php echo number_format($stats['revenue_today'], 0, '.', ' '); ?> ₽</div>
                            <div class="stat-label">Выручка сегодня</div>
                        </div>
                    </div>
                </div>
                
                <!-- Фильмы -->
                <div id="movies" class="tab-content">
                    <h2 class="content-title">Управление фильмами</h2>
                    
                    <div class="form-section">
                        <h3 class="form-title">Добавить новый фильм</h3>
                        <form method="POST">
                            <input type="hidden" name="action" value="add_movie">
                            
                            <div class="form-group">
                                <label for="title">Название фильма *</label>
                                <input type="text" id="title" name="title" required>
                            </div>
                            
                            <div class="form-group">
                                <label for="description">Описание</label>
                                <textarea id="description" name="description" rows="4"></textarea>
                            </div>
                            
                            <div class="form-group">
                                <label for="duration">Длительность (минуты) *</label>
                                <input type="number" id="duration" name="duration" required min="60" max="300">
                            </div>
                            
                            <div class="form-group">
                                <label for="poster_url">URL постера</label>
                                <input type="url" id="poster_url" name="poster_url" 
                                       placeholder="https://example.com/poster.jpg">
                            </div>
                            
                            <button type="submit" class="btn">Добавить фильм</button>
                        </form>
                    </div>
                    
                    <div class="table-container">
                        <h3 class="form-title">Список фильмов</h3>
                        <?php if (empty($movies)): ?>
                            <div class="empty-state">Фильмы не найдены</div>
                        <?php else: ?>
                            <table>
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Постер</th>
                                        <th>Название</th>
                                        <th>Длительность</th>
                                        <th>Дата добавления</th>
                                        <th>Действия</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($movies as $movie): ?>
                                        <tr>
                                            <td><?php echo $movie['id']; ?></td>
                                            <td>
                                                <?php if ($movie['poster_url']): ?>
                                                    <img src="<?php echo htmlspecialchars($movie['poster_url']); ?>" 
                                                         alt="<?php echo htmlspecialchars($movie['title']); ?>"
                                                         style="width: 60px; height: 90px; object-fit: cover; border-radius: 5px;">
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <strong><?php echo htmlspecialchars($movie['title']); ?></strong>
                                                <div style="font-size: 12px; color: #666; margin-top: 5px;">
                                                    <?php echo substr(htmlspecialchars($movie['description'] ?? ''), 0, 100); ?>...
                                                </div>
                                            </td>
                                            <td><?php echo floor($movie['duration'] / 60); ?>ч <?php echo $movie['duration'] % 60; ?>мин</td>
                                            <td><?php echo date('d.m.Y', strtotime($movie['created_at'])); ?></td>
                                            <td>
                                                <form method="POST" style="display: inline;" onsubmit="return confirm('Удалить фильм?')">
                                                    <input type="hidden" name="action" value="delete_movie">
                                                    <input type="hidden" name="movie_id" value="<?php echo $movie['id']; ?>">
                                                    <button type="submit" class="btn btn-danger">Удалить</button>
                                                </form>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Сеансы -->
                <div id="sessions" class="tab-content">
                    <h2 class="content-title">Управление сеансами</h2>
                    
                    <div class="form-section">
                        <h3 class="form-title">Добавить новый сеанс</h3>
                        <form method="POST">
                            <input type="hidden" name="action" value="add_session">
                            
                            <div class="form-group">
                                <label for="movie_id">Фильм *</label>
                                <select id="movie_id" name="movie_id" required>
                                    <option value="">Выберите фильм</option>
                                    <?php foreach ($movies as $movie): ?>
                                        <option value="<?php echo $movie['id']; ?>">
                                            <?php echo htmlspecialchars($movie['title']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="form-group">
                                <label for="start_time">Дата и время сеанса *</label>
                                <input type="datetime-local" id="start_time" name="start_time" required>
                            </div>
                            
                            <div class="form-group">
                                <label for="price">Цена (руб) *</label>
                                <input type="number" id="price" name="price" required min="100" max="5000" step="50">
                            </div>
                            
                            <button type="submit" class="btn">Добавить сеанс</button>
                        </form>
                    </div>
                    
                    <div class="table-container">
                        <h3 class="form-title">Ближайшие сеансы</h3>
                        <?php if (empty($sessions)): ?>
                            <div class="empty-state">Сеансы не найдены</div>
                        <?php else: ?>
                            <table>
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Фильм</th>
                                        <th>Дата и время</th>
                                        <th>Цена</th>
                                        <th>Зал</th>
                                        <th>Действия</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($sessions as $session): ?>
                                        <tr>
                                            <td><?php echo $session['id']; ?></td>
                                            <td><?php echo htmlspecialchars($session['movie_title']); ?></td>
                                            <td><?php echo date('d.m.Y H:i', strtotime($session['start_time'])); ?></td>
                                            <td><?php echo number_format($session['price'], 0, '.', ' '); ?> ₽</td>
                                            <td><?php echo $session['hall_number']; ?></td>
                                            <td>
                                                <form method="POST" style="display: inline;" onsubmit="return confirm('Удалить сеанс?')">
                                                    <input type="hidden" name="action" value="delete_session">
                                                    <input type="hidden" name="session_id" value="<?php echo $session['id']; ?>">
                                                    <button type="submit" class="btn btn-danger">Удалить</button>
                                                </form>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Билеты -->
                <div id="tickets" class="tab-content">
                    <h2 class="content-title">Последние билеты</h2>
                    
                    <?php if (empty($recentTickets)): ?>
                        <div class="empty-state">Билеты не найдены</div>
                    <?php else: ?>
                        <div class="table-container">
                            <table>
                                <thead>
                                    <tr>
                                        <th>Билет №</th>
                                        <th>Пользователь</th>
                                        <th>Фильм</th>
                                        <th>Цена</th>
                                        <th>Статус</th>
                                        <th>Дата покупки</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($recentTickets as $ticket): ?>
                                        <tr>
                                            <td>
                                                <span style="font-family: monospace; font-weight: bold;">
                                                    <?php echo htmlspecialchars($ticket['ticket_number']); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <div><?php echo htmlspecialchars($ticket['full_name']); ?></div>
                                                <div style="font-size: 12px; color: #666;">
                                                    <?php echo htmlspecialchars($ticket['email']); ?>
                                                </div>
                                            </td>
                                            <td><?php echo htmlspecialchars($ticket['movie_title']); ?></td>
                                            <td><?php echo number_format($ticket['price'], 0, '.', ' '); ?> ₽</td>
                                            <td>
                                                <span class="status-badge status-active">
                                                    <?php echo $ticket['status']; ?>
                                                </span>
                                            </td>
                                            <td><?php echo date('d.m.Y H:i', strtotime($ticket['purchase_time'])); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
                
                <!-- Пользователи -->
                <div id="users" class="tab-content">
                    <h2 class="content-title">Пользователи системы</h2>
                    
                    <?php 
                        $stmt = $pdo->query("SELECT * FROM users ORDER BY created_at DESC");
                        $users = $stmt->fetchAll();
                    ?>
                    
                    <?php if (empty($users)): ?>
                        <div class="empty-state">Пользователи не найдены</div>
                    <?php else: ?>
                        <div class="table-container">
                            <table>
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>ФИО</th>
                                        <th>Email</th>
                                        <th>Роль</th>
                                        <th>Дата регистрации</th>
                                        <th>Действия</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($users as $user): ?>
                                        <tr>
                                            <td><?php echo $user['id']; ?></td>
                                            <td><?php echo htmlspecialchars($user['full_name']); ?></td>
                                            <td><?php echo htmlspecialchars($user['email']); ?></td>
                                            <td>
                                                <span class="status-badge <?php echo $user['role'] === 'admin' ? 'status-active' : ''; ?>">
                                                    <?php echo $user['role'] === 'admin' ? 'Администратор' : 'Пользователь'; ?>
                                                </span>
                                            </td>
                                            <td><?php echo date('d.m.Y', strtotime($user['created_at'])); ?></td>
                                            <td>
                                                <?php if ($user['role'] !== 'admin'): ?>
                                                    <button class="btn" onclick="makeAdmin(<?php echo $user['id']; ?>)">
                                                        Сделать админом
                                                    </button>
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
    </div>
    
    <script>
        function showTab(tabId) {
            // Скрыть все табы
            document.querySelectorAll('.tab-content').forEach(tab => {
                tab.classList.remove('active');
            });
            
            // Показать выбранный таб
            document.getElementById(tabId).classList.add('active');
            
            // Обновить активную кнопку
            document.querySelectorAll('.tab').forEach(btn => {
                btn.classList.remove('active');
            });
            event.target.classList.add('active');
        }
        
        function makeAdmin(userId) {
            if (confirm('Назначить пользователя администратором?')) {
                fetch('make_admin.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({ user_id: userId })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert('Пользователь назначен администратором');
                        location.reload();
                    } else {
                        alert('Ошибка: ' + data.error);
                    }
                });
            }
        }
    </script>
</body>
</html>