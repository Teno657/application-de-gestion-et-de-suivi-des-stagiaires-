<?php
/**
 * AJAX: Vérifier les nouvelles notifications
 */

require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';

// Vérifier la connexion
if (!is_logged_in()) {
    echo json_encode(['count' => 0, 'recent' => []]);
    exit;
}

try {
    // Compter les notifications non lues
    $stmt = $pdo->prepare("
        SELECT COUNT(*) 
        FROM notifications 
        WHERE id_utilisateur = ? AND est_lue = 0
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $count = $stmt->fetchColumn();
    
    // Récupérer les 5 dernières notifications (non lues en priorité)
    $stmt = $pdo->prepare("
        SELECT id_notification, titre, message, type_notification, lien, date_creation, est_lue
        FROM notifications 
        WHERE id_utilisateur = ? 
        ORDER BY est_lue ASC, date_creation DESC 
        LIMIT 5
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $recent = $stmt->fetchAll();
    
    foreach ($recent as &$notif) {
        $notif['date_formatted'] = format_datetime($notif['date_creation'], 'd/m/Y H:i');
        $notif['time_ago'] = time_elapsed_string($notif['date_creation']);
        $notif['message_truncated'] = truncate($notif['message'], 60);
    }
    
    echo json_encode([
        'count' => (int)$count,
        'recent' => $recent
    ]);
    
} catch (Exception $e) {
    echo json_encode(['count' => 0, 'recent' => []]);
}
?>