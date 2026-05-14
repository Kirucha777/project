<?php
require_once 'config.php';

// Путь к папке с постерами
$postersDir = __DIR__ . '/media/posters/';

// Проверяем существование папки
if (!is_dir($postersDir)) {
    mkdir($postersDir, 0777, true);
}

// Массив соответствия id фильма и имени файла
$moviesPosters = [
    1 => '1.webp',
    2 => '2.webp', 
    3 => '3.webp',
    4 => '4.webp',
    5 => '5.webp',
    6 => '6.webp'
    // Добавьте остальные по мере необходимости
];

$pdo = getDB();
$updated = 0;

foreach ($moviesPosters as $movieId => $posterFile) {
    $filePath = $postersDir . $posterFile;
    
    if (file_exists($filePath)) {
        // Формируем URL для базы данных
        $posterUrl = '/media/posters/' . $posterFile;
        
        // Обновляем запись в базе
        $stmt = $pdo->prepare("UPDATE movies SET poster_url = ? WHERE id = ?");
        $stmt->execute([$posterUrl, $movieId]);
        
        echo "Обновлен фильм ID {$movieId}: {$posterUrl}<br>";
        $updated++;
    } else {
        echo "Файл не найден: {$posterFile}<br>";
    }
}

echo "<br>Обновлено записей: {$updated}";

// Очищаем кэш фильмов
clearCache();
?>