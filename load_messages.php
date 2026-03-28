<?php
/**
 * AJAX: Charger les messages d'une conversation
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

$conversation_id = (int)($_GET['conversation_id'] ?? 0);
$last_id = (int)($_GET['last_id'] ?? 0);
$limit = (int)($_GET['limit'] ?? 50);

if (!$conversation_id) {
    http_response_code(400);
    echo json_encode(['error' => 'ID conversation manquant']);
    exit;
}

try {
    // Vérifier que l'utilisateur participe à la conversation
    $stmt = $pdo->prepare("
        SELECT COUNT(*) FROM participants_conversation 
        WHERE id_conversation = ? AND id_utilisateur = ?
    ");
    $stmt->execute([$conversation_id, $_SESSION['user_id']]);
    
    if (!$stmt->fetchColumn()) {
        http_response_code(403);
        echo json_encode(['error' => 'Non autorisé']);
        exit;
    }
    
    // Charger les messages
    $query = "
        SELECT m.*, 
               u.nom as expediteur_nom, u.prenom as expediteur_prenom, u.photo as expediteur_photo
        FROM messages m
        JOIN utilisateurs u ON m.id_expediteur = u.id_utilisateur
        WHERE m.id_conversation = ?
    ";
    
    $params = [$conversation_id];
    
    if ($last_id > 0) {
        $query .= " AND m.id_message > ?";
        $params[] = $last_id;
    }
    
    $query .= " ORDER BY m.date_envoi DESC LIMIT ?";
    $params[] = $limit;
    
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $messages = $stmt->fetchAll();
    
    // Marquer les messages comme lus
    $stmt = $pdo->prepare("
        UPDATE messages 
        SET est_lu = 1, date_lecture = NOW() 
        WHERE id_conversation = ? AND id_destinataire = ? AND est_lu = 0
    ");
    $stmt->execute([$conversation_id, $_SESSION['user_id']]);
    
    // Formater les messages
    foreach ($messages as &$message) {
        $message['date_formatted'] = format_datetime($message['date_envoi'], 'd/m/Y H:i');
        $message['time_ago'] = time_elapsed_string($message['date_envoi']);
        $message['expediteur_photo_url'] = getPhotoUrl($message['expediteur_photo']);
        $message['est_moi'] = ($message['id_expediteur'] == $_SESSION['user_id']);
    }
    
    echo json_encode([
        'success' => true,
        'data' => $messages,
        'has_more' => count($messages) >= $limit
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Erreur lors du chargement des messages'
    ]);
}
?>