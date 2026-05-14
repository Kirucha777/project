<?php
require_once 'config.php';

$pdo = getDB();

// Создаем таблицы
$sql = [];

$sql[] = "CREATE TABLE IF NOT EXISTS users (
    id INT PRIMARY KEY AUTO_INCREMENT,
    email VARCHAR(255) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    full_name VARCHAR(255) NOT NULL,
    role ENUM('user', 'admin') DEFAULT 'user',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)";

$sql[] = "CREATE TABLE IF NOT EXISTS movies (
    id INT PRIMARY KEY AUTO_INCREMENT,
    title VARCHAR(255) NOT NULL,
    description TEXT,
    duration INT NOT NULL COMMENT 'Длительность в минутах',
    poster_url VARCHAR(500),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)";

$sql[] = "CREATE TABLE IF NOT EXISTS sessions (
    id INT PRIMARY KEY AUTO_INCREMENT,
    movie_id INT NOT NULL,
    start_time DATETIME NOT NULL,
    hall_number INT DEFAULT 1,
    price DECIMAL(10,2) NOT NULL,
    FOREIGN KEY (movie_id) REFERENCES movies(id) ON DELETE CASCADE,
    INDEX idx_start_time (start_time)
)";

$sql[] = "CREATE TABLE IF NOT EXISTS seats (
    id INT PRIMARY KEY AUTO_INCREMENT,
    session_id INT NOT NULL,
    row_number INT NOT NULL,
    seat_number INT NOT NULL,
    section INT NOT NULL COMMENT 'Секция 1-4',
    status ENUM('free', 'booked', 'sold') DEFAULT 'free',
    booked_by INT NULL,
    booked_until DATETIME NULL,
    FOREIGN KEY (session_id) REFERENCES sessions(id) ON DELETE CASCADE,
    FOREIGN KEY (booked_by) REFERENCES users(id) ON DELETE SET NULL,
    UNIQUE KEY unique_seat (session_id, row_number, seat_number)
)";

$sql[] = "CREATE TABLE IF NOT EXISTS tickets (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    session_id INT NOT NULL,
    seat_id INT NOT NULL,
    ticket_number VARCHAR(20) UNIQUE NOT NULL,
    price DECIMAL(10,2) NOT NULL,
    status ENUM('active', 'cancelled', 'used') DEFAULT 'active',
    purchase_time TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    qr_code TEXT,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (session_id) REFERENCES sessions(id) ON DELETE CASCADE,
    FOREIGN KEY (seat_id) REFERENCES seats(id) ON DELETE CASCADE
)";

$sql[] = "CREATE TABLE IF NOT EXISTS jobs (
    id INT PRIMARY KEY AUTO_INCREMENT,
    queue VARCHAR(255) NOT NULL,
    payload LONGTEXT NOT NULL,
    attempts INT DEFAULT 0,
    reserved_at TIMESTAMP NULL,
    available_at TIMESTAMP NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_queue_reserved (queue, reserved_at)
)";

// Выполняем SQL
foreach ($sql as $query) {
    try {
        $pdo->exec($query);
        echo "Таблица создана успешно<br>";
    } catch (PDOException $e) {
        die("Ошибка: " . $e->getMessage());
    }
}

// Добавляем больше фильмов
$movies = [
    ['Интерстеллар', 'Эпическая научно-фантастическая драма о путешествиях через червоточину в поисках нового дома для человечества.', 169, 'https://via.placeholder.com/400x600/6a11cb/ffffff?text=Interstellar'],
    ['Начало', 'Триллер о внедрении в сны с целью кражи идей.', 148, 'https://via.placeholder.com/400x600/2575fc/ffffff?text=Inception'],
    ['Крестный отец', 'Эпическая криминальная драма о семье Корлеоне.', 175, 'https://via.placeholder.com/400x600/ff0080/ffffff?text=Godfather'],
    ['Побег из Шоушенка', 'Драма о надежде и дружбе в тюрьме.', 142, 'https://via.placeholder.com/400x600/00b09b/ffffff?text=Shawshank'],
    ['Темный рыцарь', 'Бэтмен противостоит Джокеру в Готэм-сити.', 152, 'https://via.placeholder.com/400x600/333333/ffffff?text=Dark+Knight'],
    ['Форрест Гамп', 'История человека с добрым сердцем, ставшего частью важных исторических событий.', 142, 'https://via.placeholder.com/400x600/96c93d/ffffff?text=Forrest+Gump'],
    ['Пираты Карибского моря', 'Приключения капитана Джека Воробья.', 143, 'https://via.placeholder.com/400x600/ff7e5f/ffffff?text=Pirates'],
    ['Аватар', 'Фантастический фильм о планете Пандора.', 162, 'https://via.placeholder.com/400x600/00c9ff/ffffff?text=Avatar']
];

foreach ($movies as $movie) {
    $stmt = $pdo->prepare("INSERT IGNORE INTO movies (title, description, duration, poster_url) 
                           VALUES (?, ?, ?, ?)");
    $stmt->execute($movie);
}

// Создаем сеансы на ближайшие 7 дней
$movieIds = $pdo->query("SELECT id FROM movies")->fetchAll(PDO::FETCH_COLUMN);

foreach ($movieIds as $movieId) {
    // Создаем по 2-3 сеанса в день на неделю вперед
    for ($day = 0; $day < 7; $day++) {
        $date = date('Y-m-d', strtotime("+$day days"));
        
        // Утренний сеанс
        $startTime = $date . ' ' . sprintf('%02d:00', rand(10, 12));
        $price = rand(300, 500);
        
        $stmt = $pdo->prepare("INSERT IGNORE INTO sessions (movie_id, start_time, price) 
                               VALUES (?, ?, ?)");
        $stmt->execute([$movieId, $startTime, $price]);
        
        // Дневной сеанс
        $startTime = $date . ' ' . sprintf('%02d:00', rand(14, 16));
        $price = rand(400, 600);
        $stmt->execute([$movieId, $startTime, $price]);
        
        // Вечерний сеанс
        $startTime = $date . ' ' . sprintf('%02d:00', rand(18, 20));
        $price = rand(500, 700);
        $stmt->execute([$movieId, $startTime, $price]);
    }
}

echo "Установка завершена успешно!<br>";
echo "<h3>Тестовые пользователи:</h3>";
echo "<strong>Администратор:</strong><br>";
echo "Email: admin@kino.ru<br>";
echo "Пароль: admin123<br><br>";
echo "<strong>Обычный пользователь:</strong><br>";
echo "Email: user@kino.ru<br>";
echo "Пароль: user123<br><br>";
echo '<a href="index.php" style="padding: 10px 20px; background: #6a11cb; color: white; text-decoration: none; border-radius: 5px;">Перейти на главную</a>';
?>