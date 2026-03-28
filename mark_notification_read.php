<?php
/**
 * AJAX: Marquer une notification comme lue
 */

require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';

// Vérifier la connexion
if (!is_logged_in()) {
    http_response_code(401);
    echo json_encode(['error' => 'Non authentifié']);
    exit;
}

$notification_id = $_POST['notification_id'] ?? null;
$all = $_POST['all'] ?? false;

try {
    if ($all) {
        // Marquer toutes comme lues
        $stmt = $pdo->prepare("
            UPDATE notifications 
            SET est_lue = 1, date_lecture = NOW() 
            WHERE id_utilisateur = ? AND est_lue = 0
        ");
        $stmt->execute([$_SESSION['user_id']]);
        
        echo json_encode([
            'success' => true,
            'message' => 'Toutes les notifications ont été marquées comme lues'
        ]);
        
    } elseif ($notification_id) {
        // Marquer une seule notification
        $stmt = $pdo->prepare("
            UPDATE notifications 
            SET est_lue = 1, date_lecture = NOW() 
            WHERE id_notification = ? AND id_utilisateur = ?
        ");
        $stmt->execute([$notification_id, $_SESSION['user_id']]);
        
        if ($stmt->rowCount() > 0) {
            echo json_encode([
                'success' => true,
                'message' => 'Notification marquée comme lue'
            ]);
        } else {
            http_response_code(404);
            echo json_encode([
                'success' => false,
                'error' => 'Notification non trouvée'
            ]);
        }
    } else {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error' => 'ID notification manquant'
        ]);
    }
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Erreur lors de la mise à jour'
    ]);
}
?>