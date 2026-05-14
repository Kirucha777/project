<?php
// Конфигурация базы данных
define('DB_HOST', 'MySQL-8.0');
define('DB_NAME', 'kino');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_CHARSET', 'utf8mb4');

// Настройки приложения
define('CINEMA_OPEN', 8);
define('CINEMA_CLOSE', 23);
define('SESSION_DURATION', 3);
define('MAX_SEATS', 80);
define('MAX_TICKETS_PER_USER', 5);
define('CACHE_TIME', 1800); // 30 минут в секундах

// Статусы билетов
define('TICKET_ACTIVE', 1);
define('TICKET_INACTIVE', 0);

// Пути
define('BASE_URL', 'http://' . $_SERVER['HTTP_HOST'] . '/');

// Старт сессии
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Автозагрузка классов
spl_autoload_register(function ($class) {
    require_once $class . '.php';
});

// Подключение к базе данных
function getDB() {
    static $pdo = null;
    
    if ($pdo === null) {
        try {
            $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
            $pdo = new PDO($dsn, DB_USER, DB_PASS);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            die("Connection failed: " . $e->getMessage());
        }
    }
    
    return $pdo;
}

// Функция для работы с кешем
function getCache($key) {
    $cacheFile = "cache/" . md5($key) . ".cache";
    if (file_exists($cacheFile) && (time() - filemtime($cacheFile)) < CACHE_TIME) {
        return unserialize(file_get_contents($cacheFile));
    }
    return false;
}

function setCache($key, $data) {
    if (!is_dir('cache')) mkdir('cache');
    $cacheFile = "cache/" . md5($key) . ".cache";
    file_put_contents($cacheFile, serialize($data));
}

function clearCache($key = null) {
    if ($key) {
        $cacheFile = "cache/" . md5($key) . ".cache";
        if (file_exists($cacheFile)) unlink($cacheFile);
    } else {
        array_map('unlink', glob("cache/*.cache"));
    }
}
?>