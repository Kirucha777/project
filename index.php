<?php
require_once 'config.php';

// Проверяем авторизацию
$isLoggedIn = isset($_SESSION['user_id']);
$userRole = $_SESSION['user_role'] ?? 'guest';

// Создаем подключение к БД
$pdo = getDB();

// Получаем фильмы на сегодня
$today = date('Y-m-d');
$cacheKey = "movies_today_$today";
$movies = getCache($cacheKey);

if (!$movies) {
    // Исправленный запрос - убираем DISTINCT или добавляем поле в SELECT
    $stmt = $pdo->prepare("
        SELECT m.*, MIN(s.start_time) as nearest_session
        FROM movies m 
        JOIN sessions s ON m.id = s.movie_id 
        WHERE DATE(s.start_time) = ? 
        AND s.start_time > NOW()
        GROUP BY m.id
        ORDER BY nearest_session
    ");
    $stmt->execute([$today]);
    $movies = $stmt->fetchAll();
    
    // Если нет фильмов на сегодня, показываем на ближайшие 3 дня
    if (empty($movies)) {
        $stmt = $pdo->prepare("
            SELECT m.*, MIN(s.start_time) as nearest_session
            FROM movies m 
            JOIN sessions s ON m.id = s.movie_id 
            WHERE s.start_time > NOW()
            AND s.start_time < DATE_ADD(NOW(), INTERVAL 3 DAY)
            GROUP BY m.id
            ORDER BY nearest_session
            LIMIT 10
        ");
        $stmt->execute();
        $movies = $stmt->fetchAll();
    }
    
    setCache($cacheKey, $movies);
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CinemaX - Главная</title>
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
        
        .logo::before {
            content: "🎬";
            font-size: 32px;
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
        
        .welcome {
            text-align: center;
            color: white;
            margin-bottom: 40px;
            padding: 40px;
            background: rgba(255,255,255,0.1);
            border-radius: 20px;
            backdrop-filter: blur(5px);
        }
        
        .welcome h1 {
            font-size: 48px;
            margin-bottom: 15px;
            text-shadow: 2px 2px 4px rgba(0,0,0,0.3);
            background: linear-gradient(135deg, #fff 0%, #f0f0f0 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }
        
        .welcome p {
            font-size: 20px;
            opacity: 0.9;
            margin-bottom: 10px;
        }
        
        .date-info {
            font-size: 18px;
            color: #ffd700;
            font-weight: bold;
        }
        
        .movies-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 30px;
            margin-bottom: 40px;
        }
        
        .movie-card {
            background: rgba(255, 255, 255, 0.95);
            border-radius: 20px;
            overflow: hidden;
            box-shadow: 0 15px 35px rgba(0,0,0,0.2);
            transition: all 0.4s ease;
            position: relative;
        }
        
        .movie-card:hover {
            transform: translateY(-15px);
            box-shadow: 0 20px 40px rgba(0,0,0,0.3);
        }
        
        .movie-poster-container {
            position: relative;
            width: 100%;
            height: 350px;
            overflow: hidden;
        }
        
        .movie-poster {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform 0.5s ease;
        }
        
        .movie-card:hover .movie-poster {
            transform: scale(1.05);
        }
        
        .movie-info {
            padding: 25px;
        }
        
        .movie-title {
            font-size: 22px;
            color: #333;
            margin-bottom: 10px;
            font-weight: 600;
        }
        
        .movie-duration {
            color: #666;
            margin-bottom: 15px;
            font-size: 14px;
        }
        
        .movie-description {
            color: #666;
            margin-bottom: 20px;
            line-height: 1.6;
            font-size: 14px;
            display: -webkit-box;
            -webkit-line-clamp: 3;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }
        
        .sessions {
            margin-top: 20px;
        }
        
        .sessions-title {
            font-size: 16px;
            color: #333;
            margin-bottom: 10px;
            font-weight: 600;
        }
        
        .session-times {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
        }
        
        .session-time {
            display: inline-block;
            background: linear-gradient(135deg, #6a11cb 0%, #2575fc 100%);
            color: white;
            padding: 8px 16px;
            border-radius: 20px;
            text-decoration: none;
            transition: all 0.3s ease;
            font-size: 14px;
            font-weight: 500;
        }
        
        .session-time:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(106, 17, 203, 0.4);
        }
        
        .no-movies {
            text-align: center;
            color: white;
            font-size: 24px;
            padding: 60px;
            background: rgba(255,255,255,0.1);
            border-radius: 20px;
            backdrop-filter: blur(5px);
            grid-column: 1 / -1;
        }
        
        .footer {
            text-align: center;
            color: white;
            margin-top: 50px;
            padding: 30px;
            opacity: 0.8;
            border-top: 1px solid rgba(255,255,255,0.2);
        }
        
        .info-panel {
            background: rgba(255,255,255,0.1);
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 30px;
            backdrop-filter: blur(5px);
            color: white;
        }
        
        .info-panel h3 {
            margin-bottom: 15px;
            font-size: 20px;
        }
        
        .info-panel ul {
            list-style: none;
            padding-left: 0;
        }
        
        .info-panel li {
            margin-bottom: 8px;
            padding-left: 20px;
            position: relative;
        }
        
        .info-panel li:before {
            content: "✓";
            position: absolute;
            left: 0;
            color: #ffd700;
        }
        
        @media (max-width: 768px) {
            .header {
                flex-direction: column;
                gap: 20px;
            }
            
            .nav {
                width: 100%;
                justify-content: center;
            }
            
            .welcome h1 {
                font-size: 36px;
            }
            
            .movies-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <a href="index.php" class="logo">CinemaX</a>
            <div class="nav">
                <a href="index.php" class="active">Главная</a>
                <?php if ($isLoggedIn): ?>
                    <a href="profile.php">Личный кабинет</a>
                    <?php if ($userRole === 'admin'): ?>
                        <a href="admin.php">Админ-панель</a>
                    <?php endif; ?>
                    <a href="logout.php">Выйти</a>
                <?php else: ?>
                    <a href="login.php">Войти</a>
                    <a href="register.php">Регистрация</a>
                <?php endif; ?>
            </div>
        </div>
        
        <div class="welcome">
            <h1>Добро пожаловать в CinemaX!</h1>
            <p>Лучшие фильмы в комфортной обстановке</p>
            <p class="date-info">Сегодня: <?php echo date('d.m.Y'); ?></p>
        </div>
        
        
        
        <?php if (empty($movies)): ?>
            <div class="no-movies">
                <p>К сожалению, на ближайшие дни сеансов нет</p>
                <p style="margin-top: 10px; font-size: 16px;">Пожалуйста, зайдите позже</p>
            </div>
        <?php else: ?>
            <div class="movies-grid">
                <?php foreach ($movies as $movie): ?>
                    <div class="movie-card">
                        <div class="movie-poster-container">
                            <img src="<?php echo htmlspecialchars($movie['poster_url'] ?? 'https://via.placeholder.com/400x600/6a11cb/ffffff?text=' . urlencode($movie['title'])); ?>" 
                                 alt="<?php echo htmlspecialchars($movie['title']); ?>" 
                                 class="movie-poster">
                        </div>
                        <div class="movie-info">
                            <h3 class="movie-title"><?php echo htmlspecialchars($movie['title']); ?></h3>
                            <div class="movie-duration">
                                Длительность: <?php echo floor($movie['duration'] / 60); ?> ч <?php echo $movie['duration'] % 60; ?> мин
                            </div>
                            <p class="movie-description">
                                <?php echo htmlspecialchars(mb_substr($movie['description'] ?? 'Описание отсутствует', 0, 120)) . '...'; ?>
                            </p>
                            <div class="sessions">
                                <div class="sessions-title">Ближайшие сеансы:</div>
                                <div class="session-times">
                                    <?php
                                    // Получаем сеансы для этого фильма на сегодня
                                    $sessionsCacheKey = "sessions_movie_{$movie['id']}_{$today}";
                                    $sessions = getCache($sessionsCacheKey);
                                    
                                    if (!$sessions) {
                                        $stmt = $pdo->prepare("
                                            SELECT * FROM sessions 
                                            WHERE movie_id = ? 
                                            AND DATE(start_time) = ? 
                                            AND start_time > NOW()
                                            ORDER BY start_time
                                            LIMIT 4
                                        ");
                                        $stmt->execute([$movie['id'], $today]);
                                        $sessions = $stmt->fetchAll();
                                        setCache($sessionsCacheKey, $sessions);
                                    }
                                    
                                    if (empty($sessions)) {
                                        // Ищем сеансы на ближайшие дни
                                        $stmt = $pdo->prepare("
                                            SELECT * FROM sessions 
                                            WHERE movie_id = ? 
                                            AND start_time > NOW()
                                            ORDER BY start_time
                                            LIMIT 3
                                        ");
                                        $stmt->execute([$movie['id']]);
                                        $sessions = $stmt->fetchAll();
                                    }
                                    
                                    foreach ($sessions as $session):
                                        $time = date('H:i', strtotime($session['start_time']));
                                        $date = date('d.m', strtotime($session['start_time']));
                                    ?>
                                        <a href="booking.php?session=<?php echo $session['id']; ?>" 
                                           class="session-time"
                                           title="Сеанс <?php echo $time; ?>">
                                            <?php echo $time; ?>
                                        </a>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
        <div class="info-panel">
            <h3>Информация:</h3>
            <ul>
                <li>CinemaX работает ежедневно с 08:00 до 23:00</li>
                <li>Длительность сеанса: 3 часа</li>
                <li>Максимум 5 билетов на одного пользователя</li>
                <li>Бронирование доступно только авторизованным пользователям</li>
            </ul>
        </div>
        <div class="footer">
            <p>CinemaX работает ежедневно с 08:00 до 23:00</p>
            <p>© <?php echo date('Y'); ?> CinemaX. Все права защищены.</p>
        </div>
    </div>
</body>
</html>