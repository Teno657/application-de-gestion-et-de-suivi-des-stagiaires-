<?php
/**
 * API - Gestion des messages
 */

require_once 'config.php';

// Vérifier la clé API
$user = checkApiKey();

// Récupérer la méthode HTTP
$method = $_SERVER['REQUEST_METHOD'];

switch ($method) {
    case 'GET':
        // Récupérer les messages
        if (isset($_GET['conversation_id'])) {
            getMessages($_GET['conversation_id']);
        } elseif (isset($_GET['user_id'])) {
            getConversations($_GET['user_id']);
        } else {
            apiError('Paramètre conversation_id ou user_id requis', 400);
        }
        break;
        
    case 'POST':
        // Envoyer un message
        sendMessage();
        break;
        
    case 'PUT':
        // Marquer comme lu
        if (isset($_GET['id'])) {
            markAsRead($_GET['id']);
        } else {
            apiError('ID message requis', 400);
        }
        break;
        
    default:
        apiError('Méthode non autorisée', 405);
}

/**
 * Récupérer les messages d'une conversation
 */
function getMessages($conversation_id) {
    global $pdo;
    
    $limit = (int)($_GET['limit'] ?? 50);
    $before_id = isset($_GET['before_id']) ? (int)$_GET['before_id'] : null;
    
    $where = "m.id_conversation = ?";
    $params = [$conversation_id];
    
    if ($before_id) {
        $where .= " AND m.id_message < ?";
        $params[] = $before_id;
    }
    
    $sql = "
        SELECT 
            m.*,
            u.nom as expediteur_nom,
            u.prenom as expediteur_prenom,
            u.photo as expediteur_photo,
            u.role as expediteur_role
        FROM messages m
        JOIN utilisateurs u ON m.id_expediteur = u.id_utilisateur
        WHERE $where
        ORDER BY m.date_envoi DESC
        LIMIT ?
    ";
    
    $params[] = $limit;
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $messages = $stmt->fetchAll();
    
    // Formater les dates
    foreach ($messages as &$msg) {
        $msg['date_formatted'] = format_datetime($msg['date_envoi']);
        $msg['date_lecture_formatted'] = $msg['date_lecture'] ? format_datetime($msg['date_lecture']) : null;
    }
    
    apiResponse([
        'success' => true,
        'data' => $messages,
        'has_more' => count($messages) == $limit
    ]);
}

/**
 * Récupérer les conversations d'un utilisateur
 */
function getConversations($user_id) {
    global $pdo;
    
    $stmt = $pdo->prepare("
        SELECT DISTINCT 
            c.id_conversation,
            c.type_conversation,
            c.titre as conversation_titre,
            c.date_dernier_message,
            (
                SELECT m2.contenu 
                FROM messages m2 
                WHERE m2.id_conversation = c.id_conversation 
                ORDER BY m2.date_envoi DESC 
                LIMIT 1
            ) as dernier_message,
            (
                SELECT COUNT(*) 
                FROM messages m3 
                WHERE m3.id_conversation = c.id_conversation 
                AND m3.id_destinataire = ? 
                AND m3.est_lu = 0
            ) as non_lus,
            (
                SELECT JSON_OBJECT(
                    'id', u2.id_utilisateur,
                    'nom', u2.nom,
                    'prenom', u2.prenom,
                    'photo', u2.photo,
                    'role', u2.role
                )
                FROM participants_conversation p2
                JOIN utilisateurs u2 ON p2.id_utilisateur = u2.id_utilisateur
                WHERE p2.id_conversation = c.id_conversation 
                AND p2.id_utilisateur != ?
                LIMIT 1
            ) as autre_participant
        FROM conversations c
        JOIN participants_conversation p ON c.id_conversation = p.id_conversation
        WHERE p.id_utilisateur = ?
        ORDER BY c.date_dernier_message DESC
    ");
    $stmt->execute([$user_id, $user_id, $user_id]);
    $conversations = $stmt->fetchAll();
    
    // Décoder le JSON
    foreach ($conversations as &$conv) {
        if ($conv['autre_participant']) {
            $conv['autre_participant'] = json_decode($conv['autre_participant'], true);
            $conv['autre_participant']['photo_url'] = getPhotoUrl($conv['autre_participant']['photo'] ?? '');
        }
    }
    
    apiResponse([
        'success' => true,
        'data' => $conversations
    ]);
}

/**
 * Envoyer un message
 */
function sendMessage() {
    global $pdo;
    
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (!$data) {
        apiError('Données invalides', 400);
    }
    
    validateRequired($data, ['id_expediteur', 'id_destinataire', 'contenu']);
    
    try {
        $pdo->beginTransaction();
        
        // Vérifier s'il existe déjà une conversation entre ces deux utilisateurs
        $conversation_id = $data['conversation_id'] ?? null;
        
        if (!$conversation_id) {
            $stmt = $pdo->prepare("
                SELECT c.id_conversation
                FROM conversations c
                JOIN participants_conversation p1 ON c.id_conversation = p1.id_conversation
                JOIN participants_conversation p2 ON c.id_conversation = p2.id_conversation
                WHERE c.type_conversation = 'individuelle'
                AND p1.id_utilisateur = ? AND p2.id_utilisateur = ?
                AND c.id_conversation IN (
                    SELECT id_conversation FROM participants_conversation 
                    GROUP BY id_conversation HAVING COUNT(*) = 2
                )
            ");
            $stmt->execute([$data['id_expediteur'], $data['id_destinataire']]);
            $existing = $stmt->fetch();
            
            if ($existing) {
                $conversation_id = $existing['id_conversation'];
            } else {
                // Créer une nouvelle conversation
                $stmt = $pdo->prepare("
                    INSERT INTO conversations (type_conversation, date_creation)
                    VALUES ('individuelle', NOW())
                ");
                $stmt->execute();
                $conversation_id = $pdo->lastInsertId();
                
                // Ajouter les participants
                $stmt = $pdo->prepare("
                    INSERT INTO participants_conversation (id_conversation, id_utilisateur)
                    VALUES (?, ?), (?, ?)
                ");
                $stmt->execute([
                    $conversation_id, $data['id_expediteur'],
                    $conversation_id, $data['id_destinataire']
                ]);
            }
        }
        
        // Insérer le message
        $stmt = $pdo->prepare("
            INSERT INTO messages (id_conversation, id_expediteur, id_destinataire, contenu, date_envoi)
            VALUES (?, ?, ?, ?, NOW())
        ");
        $stmt->execute([
            $conversation_id,
            $data['id_expediteur'],
            $data['id_destinataire'],
            $data['contenu']
        ]);
        
        $message_id = $pdo->lastInsertId();
        
        // Mettre à jour la date du dernier message
        $pdo->prepare("
            UPDATE conversations SET date_dernier_message = NOW() WHERE id_conversation = ?
        ")->execute([$conversation_id]);
        
        // Créer une notification
        create_notification(
            $data['id_destinataire'],
            'Nouveau message',
            "Vous avez reçu un nouveau message",
            'info',
            "/messagerie/conversation.php?id=$conversation_id"
        );
        
        $pdo->commit();
        
        // Récupérer le message créé
        $stmt = $pdo->prepare("
            SELECT m.*, u.nom, u.prenom, u.photo
            FROM messages m
            JOIN utilisateurs u ON m.id_expediteur = u.id_utilisateur
            WHERE m.id_message = ?
        ");
        $stmt->execute([$message_id]);
        $message = $stmt->fetch();
        
        $message['date_formatted'] = format_datetime($message['date_envoi']);
        $message['expediteur_photo_url'] = getPhotoUrl($message['photo'] ?? '');
        
        apiResponse([
            'success' => true,
            'message' => 'Message envoyé avec succès',
            'data' => [
                'message_id' => $message_id,
                'conversation_id' => $conversation_id,
                'message' => $message
            ]
        ], 201);
        
    } catch (Exception $e) {
        $pdo->rollBack();
        apiError('Erreur lors de l\'envoi : ' . $e->getMessage(), 500);
    }
}

/**
 * Marquer un message comme lu
 */
function markAsRead($id) {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("
            UPDATE messages 
            SET est_lu = 1, date_lecture = NOW() 
            WHERE id_message = ?
        ");
        $stmt->execute([$id]);
        
        apiResponse([
            'success' => true,
            'message' => 'Message marqué comme lu'
        ]);
        
    } catch (Exception $e) {
        apiError('Erreur : ' . $e->getMessage(), 500);
    }
}
?>