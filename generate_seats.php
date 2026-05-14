<?php
require_once 'config.php';

function generateSeatsForSession($sessionId) {
    $pdo = getDB();
    
    // Удаляем старые места (если есть)
    $stmt = $pdo->prepare("DELETE FROM seats WHERE session_id = ?");
    $stmt->execute([$sessionId]);
    
    // Создаем 80 мест: 10 рядов, 8 мест в ряду
    for ($row = 1; $row <= 10; $row++) {
        for ($seat = 1; $seat <= 8; $seat++) {
            // Определяем секцию (1-4) с правильными скобками
            if ($row <= 5 && $seat <= 4) {
                $section = 1;
            } elseif ($row <= 5 && $seat > 4) {
                $section = 2;
            } elseif ($row > 5 && $seat <= 4) {
                $section = 3;
            } else {
                $section = 4;
            }
            
            $stmt = $pdo->prepare("
                INSERT INTO seats (session_id, row_num, seat_num, section) 
                VALUES (?, ?, ?, ?)
            ");
            $stmt->execute([$sessionId, $row, $seat, $section]);
        }
    }
    
    return true;
}

// Функция для создания мест для всех сеансов
function generateSeatsForAllSessions() {
    $pdo = getDB();
    
    // Получаем все сеансы
    $stmt = $pdo->query("SELECT id FROM sessions");
    $sessions = $stmt->fetchAll();
    
    foreach ($sessions as $session) {
        generateSeatsForSession($session['id']);
        echo "Созданы места для сеанса ID: " . $session['id'] . "<br>";
    }
    
    return true;
}

// Если файл вызван напрямую
if (basename($_SERVER['PHP_SELF']) == 'generate_seats.php') {
    echo "<h2>Генерация мест</h2>";
    
    if (isset($_GET['session_id'])) {
        $sessionId = intval($_GET['session_id']);
        if (generateSeatsForSession($sessionId)) {
            echo "✅ Места для сеанса $sessionId успешно созданы";
        } else {
            echo "❌ Ошибка при создании мест";
        }
    } elseif (isset($_GET['all'])) {
        if (generateSeatsForAllSessions()) {
            echo "✅ Места для всех сеансов успешно созданы";
        } else {
            echo "❌ Ошибка при создании мест";
        }
    } else {
        echo "<p>Использование:</p>";
        echo "<ul>";
        echo "<li><a href='?session_id=1'>Создать места для сеанса 1</a></li>";
        echo "<li><a href='?all=1'>Создать места для всех сеансов</a></li>";
        echo "</ul>";
    }
    
    echo "<hr><a href='index.php'>На главную</a>";
}
?>