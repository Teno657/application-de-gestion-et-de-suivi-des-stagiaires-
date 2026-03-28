<?php
/**
 * AJAX: Mettre à jour le statut d'une tâche
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

$task_id = (int)($_POST['task_id'] ?? 0);
$status = $_POST['status'] ?? '';
$progression = (int)($_POST['progression'] ?? 0);
$comment = $_POST['comment'] ?? '';

if (!$task_id || !in_array($status, ['a_faire', 'en_cours', 'termine', 'annule', 'a_revoir'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Paramètres invalides']);
    exit;
}

try {
    // Vérifier les permissions
    if (has_role('stagiaire')) {
        // Le stagiaire ne peut modifier que ses propres tâches
        $stmt = $pdo->prepare("
            SELECT id_tache FROM taches 
            WHERE id_tache = ? AND id_stagiaire = ?
        ");
        $stmt->execute([$task_id, $_SESSION['user_id']]);
        if (!$stmt->fetch()) {
            http_response_code(403);
            echo json_encode(['error' => 'Non autorisé']);
            exit;
        }
    } elseif (has_role('encadreur_pro') || has_role('encadreur_acro')) {
        // L'encadreur ne peut modifier que les tâches qu'il a créées
        $stmt = $pdo->prepare("
            SELECT id_tache FROM taches 
            WHERE id_tache = ? AND id_encadreur = ?
        ");
        $stmt->execute([$task_id, $_SESSION['user_id']]);
        if (!$stmt->fetch()) {
            http_response_code(403);
            echo json_encode(['error' => 'Non autorisé']);
            exit;
        }
    }
    
    // Mettre à jour la tâche
    $updates = ['statut = ?'];
    $params = [$status];
    
    if ($progression > 0) {
        $updates[] = 'progression = ?';
        $params[] = $progression;
    }
    
    if ($status === 'termine') {
        $updates[] = 'date_realisation = NOW()';
    }
    
    if (!empty($comment)) {
        $updates[] = 'commentaires = CONCAT(commentaires, ?, "\n")';
        $params[] = date('d/m/Y H:i') . " - " . $comment;
    }
    
    $sql = "UPDATE taches SET " . implode(', ', $updates) . " WHERE id_tache = ?";
    $params[] = $task_id;
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    
    // Créer une notification pour le stagiaire ou l'encadreur
    if (has_role('stagiaire')) {
        // Notifier l'encadreur
        $task = $pdo->prepare("SELECT id_encadreur, titre FROM taches WHERE id_tache = ?");
        $task->execute([$task_id]);
        $taskInfo = $task->fetch();
        
        create_notification(
            $taskInfo['id_encadreur'],
            'Mise à jour de tâche',
            "Le stagiaire a mis à jour la tâche '{$taskInfo['titre']}' (statut: $status)",
            'info',
            "/dashboard/encadreur/tache-voir.php?id=$task_id"
        );
    } else {
        // Notifier le stagiaire
        $task = $pdo->prepare("SELECT id_stagiaire, titre FROM taches WHERE id_tache = ?");
        $task->execute([$task_id]);
        $taskInfo = $task->fetch();
        
        create_notification(
            $taskInfo['id_stagiaire'],
            'Mise à jour de tâche',
            "L'encadreur a mis à jour la tâche '{$taskInfo['titre']}' (statut: $status)",
            'info',
            "/dashboard/stagiaire/tache-voir.php?id=$task_id"
        );
    }
    
    // Journaliser l'action
    log_action(
        $_SESSION['user_id'],
        'UPDATE_TASK',
        "Mise à jour de la tâche #$task_id vers le statut $status",
        'modification',
        'taches',
        $task_id
    );
    
    echo json_encode([
        'success' => true,
        'message' => 'Tâche mise à jour avec succès'
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Erreur lors de la mise à jour'
    ]);
}
?>