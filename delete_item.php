<?php
/**
 * AJAX: Supprimer un élément (utilisateur, tâche, document, etc.)
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

$type = $_POST['type'] ?? '';
$id = (int)($_POST['id'] ?? 0);

if (!$type || !$id) {
    http_response_code(400);
    echo json_encode(['error' => 'Paramètres manquants']);
    exit;
}

try {
    $pdo->beginTransaction();
    $message = '';
    
    switch ($type) {
        case 'stagiaire':
            // Vérifier les permissions
            if (!has_role('admin') && !has_role('secretaire')) {
                throw new Exception('Non autorisé');
            }
            
            // Supprimer les documents associés
            $stmt = $pdo->prepare("SELECT chemin FROM documents WHERE id_stagiaire = ?");
            $stmt->execute([$id]);
            $documents = $stmt->fetchAll();
            
            foreach ($documents as $doc) {
                if (file_exists($doc['chemin'])) {
                    unlink($doc['chemin']);
                }
            }
            
            // Supprimer le stagiaire (les relations en cascade feront le reste)
            $stmt = $pdo->prepare("DELETE FROM utilisateurs WHERE id_utilisateur = ? AND role = 'stagiaire'");
            $stmt->execute([$id]);
            
            $message = 'Stagiaire supprimé avec succès';
            break;
            
        case 'encadreur':
            if (!has_role('admin')) {
                throw new Exception('Non autorisé');
            }
            
            // Vérifier s'il a des stagiaires actifs
            $stmt = $pdo->prepare("
                SELECT COUNT(*) FROM relations_encadrement 
                WHERE id_encadreur = ? AND statut = 'active'
            ");
            $stmt->execute([$id]);
            if ($stmt->fetchColumn() > 0) {
                throw new Exception('Cet encadreur a encore des stagiaires actifs');
            }
            
            $stmt = $pdo->prepare("DELETE FROM utilisateurs WHERE id_utilisateur = ? AND role LIKE 'encadreur_%'");
            $stmt->execute([$id]);
            
            $message = 'Encadreur supprimé avec succès';
            break;
            
        case 'tache':
            // Vérifier les permissions
            if (has_role('stagiaire')) {
                $stmt = $pdo->prepare("SELECT id_tache FROM taches WHERE id_tache = ? AND id_stagiaire = ?");
                $stmt->execute([$id, $_SESSION['user_id']]);
                if (!$stmt->fetch()) {
                    throw new Exception('Non autorisé');
                }
            } elseif (has_role('encadreur_pro') || has_role('encadreur_acro')) {
                $stmt = $pdo->prepare("SELECT id_tache FROM taches WHERE id_tache = ? AND id_encadreur = ?");
                $stmt->execute([$id, $_SESSION['user_id']]);
                if (!$stmt->fetch()) {
                    throw new Exception('Non autorisé');
                }
            } elseif (!has_role('admin') && !has_role('secretaire')) {
                throw new Exception('Non autorisé');
            }
            
            $stmt = $pdo->prepare("DELETE FROM taches WHERE id_tache = ?");
            $stmt->execute([$id]);
            
            $message = 'Tâche supprimée avec succès';
            break;
            
        case 'document':
            // Récupérer le chemin du fichier
            $stmt = $pdo->prepare("SELECT chemin FROM documents WHERE id_document = ?");
            $stmt->execute([$id]);
            $doc = $stmt->fetch();
            
            if ($doc && file_exists($doc['chemin'])) {
                unlink($doc['chemin']);
            }
            
            $stmt = $pdo->prepare("DELETE FROM documents WHERE id_document = ?");
            $stmt->execute([$id]);
            
            $message = 'Document supprimé avec succès';
            break;
            
        case 'message':
            // Marquer comme supprimé au lieu de vraiment supprimer
            $stmt = $pdo->prepare("
                UPDATE messages 
                SET est_supprime_exp = CASE WHEN id_expediteur = ? THEN 1 ELSE est_supprime_exp END,
                    est_supprime_dest = CASE WHEN id_destinataire = ? THEN 1 ELSE est_supprime_dest END
                WHERE id_message = ?
            ");
            $stmt->execute([$_SESSION['user_id'], $_SESSION['user_id'], $id]);
            
            $message = 'Message supprimé avec succès';
            break;
            
        default:
            throw new Exception('Type non supporté');
    }
    
    // Journaliser l'action
    log_action(
        $_SESSION['user_id'],
        'DELETE_' . strtoupper($type),
        "Suppression de $type #$id",
        'suppression',
        $type,
        $id
    );
    
    $pdo->commit();
    
    echo json_encode([
        'success' => true,
        'message' => $message
    ]);
    
} catch (Exception $e) {
    $pdo->rollBack();
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>