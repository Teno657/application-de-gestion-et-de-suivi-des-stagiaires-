<?php
/**
 * AJAX: Envoyer un message
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

// Vérifier le token CSRF
if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
    http_response_code(403);
    echo json_encode(['error' => 'Token CSRF invalide']);
    exit;
}

$conversation_id = (int)($_POST['conversation_id'] ?? 0);
$destinataire_id = (int)($_POST['destinataire_id'] ?? 0);
$message = trim($_POST['message'] ?? '');
$sujet = trim($_POST['sujet'] ?? '');

if (empty($message)) {
    http_response_code(400);
    echo json_encode(['error' => 'Message vide']);
    exit;
}

try {
    $pdo->beginTransaction();
    
    // Si pas de conversation_id, créer une nouvelle conversation
    if (!$conversation_id && $destinataire_id) {
        // Vérifier si une conversation existe déjà
        $stmt = $pdo->prepare("
            SELECT c.id_conversation 
            FROM conversations c
            JOIN participants_conversation p1 ON c.id_conversation = p1.id_conversation
            JOIN participants_conversation p2 ON c.id_conversation = p2.id_conversation
            WHERE c.type_conversation = 'individuelle'
            AND p1.id_utilisateur = ? 
            AND p2.id_utilisateur = ?
        ");
        $stmt->execute([$_SESSION['user_id'], $destinataire_id]);
        $existing = $stmt->fetch();
        
        if ($existing) {
            $conversation_id = $existing['id_conversation'];
        } else {
            // Créer nouvelle conversation
            $stmt = $pdo->prepare("INSERT INTO conversations (type_conversation, titre) VALUES ('individuelle', ?)");
            $stmt->execute(["Conversation entre " . $_SESSION['user_id'] . " et " . $destinataire_id]);
            $conversation_id = $pdo->lastInsertId();
            
            // Ajouter les participants
            $stmt = $pdo->prepare("INSERT INTO participants_conversation (id_conversation, id_utilisateur) VALUES (?, ?), (?, ?)");
            $stmt->execute([$conversation_id, $_SESSION['user_id'], $conversation_id, $destinataire_id]);
        }
    }
    
    if (!$conversation_id) {
        throw new Exception('Impossible de déterminer la conversation');
    }
    
    // Insérer le message
    $stmt = $pdo->prepare("
        INSERT INTO messages (id_conversation, id_expediteur, id_destinataire, sujet, contenu) 
        VALUES (?, ?, ?, ?, ?)
    ");
    $stmt->execute([$conversation_id, $_SESSION['user_id'], $destinataire_id, $sujet, $message]);
    $message_id = $pdo->lastInsertId();
    
    // Mettre à jour la date du dernier message dans la conversation
    $stmt = $pdo->prepare("UPDATE conversations SET date_dernier_message = NOW() WHERE id_conversation = ?");
    $stmt->execute([$conversation_id]);
    
    // Créer une notification pour le destinataire
    create_notification(
        $destinataire_id,
        'Nouveau message',
        "Vous avez reçu un nouveau message de " . get_current_user()['prenom'],
        'info',
        "/messagerie/conversation.php?id=$conversation_id"
    );
    
    $pdo->commit();
    
    // Récupérer le message inséré
    $stmt = $pdo->prepare("
        SELECT m.*, u.nom, u.prenom, u.photo 
        FROM messages m
        JOIN utilisateurs u ON m.id_expediteur = u.id_utilisateur
        WHERE m.id_message = ?
    ");
    $stmt->execute([$message_id]);
    $newMessage = $stmt->fetch();
    
    $newMessage['date_formatted'] = format_datetime($newMessage['date_envoi'], 'd/m/Y H:i');
    $newMessage['expediteur_photo_url'] = getPhotoUrl($newMessage['photo']);
    $newMessage['est_moi'] = true;
    
    echo json_encode([
        'success' => true,
        'message' => $newMessage,
        'conversation_id' => $conversation_id
    ]);
    
} catch (Exception $e) {
    $pdo->rollBack();
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Erreur lors de l\'envoi du message'
    ]);
}
?>