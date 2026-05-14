<?php
require_once 'config.php';

echo "<h2>Обновление постеров фильмов</h2>";

$pdo = getDB();

// Маппинг ID фильмов к файлам постеров
$posterMapping = [
    1 => '1.webp',
    2 => '2.webp',
    3 => '3.webp',
    4 => '4.webp',
    7 => '5.webp', // Обратите внимание: ID 7, а не 5!
    8 => '6.jpg'
];

// Проверяем существование файлов
foreach ($posterMapping as $movieId => $posterFile) {
    $filePath = __DIR__ . '/media/posters/' . $posterFile;
    
    if (!file_exists($filePath)) {
        echo "<div style='color: red; padding: 10px; background: #ffe6e6; border-radius: 5px; margin: 5px;'>
              ❌ Файл не найден: {$posterFile}</div>";
    }
}

echo "<h3>Обновление базы данных:</h3>";

// Обновляем записи в базе
foreach ($posterMapping as $movieId => $posterFile) {
    $posterUrl = '/media/posters/' . $posterFile;
    
    $stmt = $pdo->prepare("UPDATE movies SET poster_url = ? WHERE id = ?");
    $stmt->execute([$posterUrl, $movieId]);
    
    // Получаем название фильма для отчета
    $stmt2 = $pdo->prepare("SELECT title FROM movies WHERE id = ?");
    $stmt2->execute([$movieId]);
    $movieTitle = $stmt2->fetchColumn();
    
    echo "<div style='color: green; padding: 10px; background: #e6ffe6; border-radius: 5px; margin: 5px;'>
          ✅ Обновлен: {$movieTitle} (ID: {$movieId}) → {$posterUrl}</div>";
}

// Очищаем кэш
clearCache();

echo "<hr><h3>Проверка результатов:</h3>";

// Показываем обновленные данные
$stmt = $pdo->query("SELECT id, title, poster_url FROM movies WHERE poster_url LIKE '/media/posters/%' ORDER BY id");
$updatedMovies = $stmt->fetchAll();

if (count($updatedMovies) > 0) {
    echo "<table border='1' cellpadding='10' style='border-collapse: collapse; width: 100%;'>";
    echo "<tr style='background: #f0f0f0;'><th>ID</th><th>Название</th><th>Постер</th></tr>";
    
    foreach ($updatedMovies as $movie) {
        echo "<tr>";
        echo "<td>{$movie['id']}</td>";
        echo "<td><strong>{$movie['title']}</strong></td>";
        echo "<td>";
        echo "{$movie['poster_url']}<br>";
        if (file_exists(__DIR__ . $movie['poster_url'])) {
            echo "<small style='color: green;'>✓ Файл существует</small>";
        } else {
            echo "<small style='color: red;'>✗ Файл отсутствует</small>";
        }
        echo "</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "<p>Ни один постер не был обновлен.</p>";
}

echo "<hr>";
echo "<a href='index.php' style='display: inline-block; padding: 10px 20px; background: #6a11cb; color: white; text-decoration: none; border-radius: 5px;'>Вернуться на главную</a>";
echo " | ";
echo "<a href='movie.php?id=1' style='display: inline-block; padding: 10px 20px; background: #2575fc; color: white; text-decoration: none; border-radius: 5px;'>Проверить фильм Интерстеллар</a>";
?>