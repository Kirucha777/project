<?php
require_once 'config.php';

// Получаем ID фильма из URL
$movieId = $_GET['id'] ?? 0;

if (!$movieId) {
    header('Location: index.php');
    exit();
}

// Получаем информацию о фильме
$cacheKey = "movie_{$movieId}";
$movie = getCache($cacheKey);

if (!$movie) {
    $pdo = getDB();
    $stmt = $pdo->prepare("SELECT * FROM movies WHERE id = ?");
    $stmt->execute([$movieId]);
    $movie = $stmt->fetch();
    
    if (!$movie) {
        header('Location: index.php');
        exit();
    }
    
    setCache($cacheKey, $movie);
}

// Получаем сеансы фильма
$today = date('Y-m-d');
$cacheKeySessions = "movie_sessions_{$movieId}_{$today}";
$sessions = getCache($cacheKeySessions);

if (!$sessions) {
    $pdo = getDB();
    $stmt = $pdo->prepare("
        SELECT * FROM sessions 
        WHERE movie_id = ? 
        AND start_time > NOW()
        AND DATE(start_time) BETWEEN ? AND DATE_ADD(?, INTERVAL 7 DAY)
        ORDER BY start_time
    ");
    $stmt->execute([$movieId, $today, $today]);
    $sessions = $stmt->fetchAll();
    setCache($cacheKeySessions, $sessions);
}

// Группируем сеансы по дням
$sessionsByDay = [];
foreach ($sessions as $session) {
    $date = date('Y-m-d', strtotime($session['start_time']));
    $sessionsByDay[$date][] = $session;
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($movie['title']); ?> - Кинотеатр</title>
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
            box-shadow: 0 5px 15px rgba(106, 17, 203, 0.4);
        }
        
        .back-link {
            margin-bottom: 20px;
        }
        
        .back-link a {
            color: white;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 10px 20px;
            background: rgba(255,255,255,0.1);
            border-radius: 25px;
            transition: all 0.3s ease;
        }
        
        .back-link a:hover {
            background: rgba(255,255,255,0.2);
            transform: translateX(-5px);
        }
        
        .movie-details {
            background: rgba(255, 255, 255, 0.95);
            border-radius: 20px;
            padding: 40px;
            margin-bottom: 30px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
            backdrop-filter: blur(10px);
        }
        
        .movie-header {
            display: flex;
            gap: 40px;
            margin-bottom: 30px;
        }
        
        @media (max-width: 768px) {
            .movie-header {
                flex-direction: column;
            }
        }
        
        .movie-poster {
            flex-shrink: 0;
            width: 300px;
            height: 450px;
            border-radius: 15px;
            overflow: hidden;
            box-shadow: 0 10px 20px rgba(0,0,0,0.2);
        }
        
        .movie-poster img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .movie-info {
            flex: 1;
        }
        
        .movie-title {
            font-size: 36px;
            color: #333;
            margin-bottom: 15px;
            font-weight: 700;
        }
        
        .movie-meta {
            display: flex;
            gap: 20px;
            margin-bottom: 20px;
            color: #666;
            font-size: 16px;
        }
        
        .movie-meta span {
            background: #f0f0f0;
            padding: 5px 15px;
            border-radius: 20px;
        }
        
        .movie-description {
            font-size: 18px;
            line-height: 1.6;
            color: #444;
            margin-bottom: 30px;
        }
        
        .sessions-section {
            background: rgba(255, 255, 255, 0.95);
            border-radius: 20px;
            padding: 40px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
            backdrop-filter: blur(10px);
        }
        
        .section-title {
            font-size: 28px;
            color: #333;
            margin-bottom: 30px;
            padding-bottom: 15px;
            border-bottom: 2px solid #f0f0f0;
        }
        
        .day-sessions {
            margin-bottom: 40px;
        }
        
        .day-title {
            font-size: 22px;
            color: #6a11cb;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .sessions-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 15px;
        }
        
        .session-card {
            background: #f8f9fa;
            border-radius: 15px;
            padding: 20px;
            text-align: center;
            transition: all 0.3s ease;
            border: 2px solid transparent;
        }
        
        .session-card:hover {
            transform: translateY(-5px);
            border-color: #6a11cb;
            box-shadow: 0 5px 15px rgba(106, 17, 203, 0.1);
        }
        
        .session-time {
            font-size: 24px;
            font-weight: 600;
            color: #333;
            margin-bottom: 10px;
        }
        
        .session-price {
            font-size: 18px;
            color: #6a11cb;
            font-weight: 600;
            margin-bottom: 15px;
        }
        
        .book-button {
            display: inline-block;
            background: linear-gradient(135deg, #6a11cb 0%, #2575fc 100%);
            color: white;
            text-decoration: none;
            padding: 10px 20px;
            border-radius: 25px;
            font-weight: 500;
            transition: all 0.3s ease;
        }
        
        .book-button:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(106, 17, 203, 0.4);
        }
        
        .no-sessions {
            text-align: center;
            padding: 40px;
            color: #666;
            font-size: 18px;
            background: #f8f9fa;
            border-radius: 15px;
        }
        
        .footer {
            text-align: center;
            color: white;
            margin-top: 50px;
            padding: 30px;
            opacity: 0.8;
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
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <a href="index.php" class="logo">🎬 CinemaX</a>
            <div class="nav">
                <a href="index.php">Главная</a>
                <?php if (isset($_SESSION['user_id'])): ?>
                    <a href="profile.php">Личный кабинет</a>
                    <?php if ($_SESSION['user_role'] === 'admin'): ?>
                        <a href="admin.php">Админ-панель</a>
                    <?php endif; ?>
                    <a href="logout.php">Выйти</a>
                <?php else: ?>
                    <a href="login.php">Войти</a>
                    <a href="register.php">Регистрация</a>
                <?php endif; ?>
            </div>
        </div>
        
        <div class="back-link">
            <a href="index.php">← Назад к списку фильмов</a>
        </div>
        
        <div class="movie-details">
            <div class="movie-header">
                <div class="movie-poster">
                    <img src="<?php echo htmlspecialchars($movie['poster_url']); ?>" 
                         alt="<?php echo htmlspecialchars($movie['title']); ?>">
                </div>
                <div class="movie-info">
                    <h1 class="movie-title"><?php echo htmlspecialchars($movie['title']); ?></h1>
                    <div class="movie-meta">
                        <span>Длительность: <?php echo floor($movie['duration'] / 60); ?>ч <?php echo $movie['duration'] % 60; ?>мин</span>
                        <span>Рейтинг: 12+</span>
                    </div>
                    <p class="movie-description">
                        <?php echo nl2br(htmlspecialchars($movie['description'])); ?>
                    </p>
                </div>
            </div>
        </div>
        
        <div class="sessions-section">
            <h2 class="section-title">Расписание сеансов</h2>
            
            <?php if (empty($sessionsByDay)): ?>
                <div class="no-sessions">
                    <p>На ближайшие дни сеансов нет</p>
                    <p style="margin-top: 10px;">Пожалуйста, выберите другой фильм</p>
                </div>
            <?php else: ?>
                <?php foreach ($sessionsByDay as $date => $daySessions): ?>
                    <div class="day-sessions">
                        <h3 class="day-title">
                            📅 <?php echo date('d.m.Y (l)', strtotime($date)); ?>
                        </h3>
                        <div class="sessions-grid">
                            <?php foreach ($daySessions as $session): 
                                $time = date('H:i', strtotime($session['start_time']));
                                $price = number_format($session['price'], 0, '.', ' ');
                            ?>
                                <div class="session-card">
                                    <div class="session-time"><?php echo $time; ?></div>
                                    <div class="session-price"><?php echo $price; ?> ₽</div>
                                    <a href="booking.php?session=<?php echo $session['id']; ?>" 
                                       class="book-button">
                                        Выбрать места
                                    </a>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
            
            <div class="info-box">
                <h4>ℹ️ Важная информация:</h4>
                <p>• Пожалуйста, приходите за 15 минут до начала сеанса</p>
                <p>• Бронирование действует 15 минут</p>
                <p>• Максимум 5 билетов на одного пользователя</p>
            </div>
        </div>
        
        <div class="footer">
            <p>CinemaX работает ежедневно с 08:00 до 23:00</p>
            <p>© <?php echo date('Y'); ?> CinemaX. Все права защищены.</p>
        </div>
    </div>
</body>
</html>