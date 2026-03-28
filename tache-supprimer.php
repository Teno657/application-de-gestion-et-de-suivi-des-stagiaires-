<?php
/**
 * Supprimer une tâche - Encadreur
 */

require_once '../../includes/config.php';
require_once '../../includes/functions.php';
require_once '../../includes/auth.php';

require_any_role(['encadreur_pro', 'encadreur_acro']);

$user_id = $_SESSION['user_id'];
$id_tache = (int)($_GET['id'] ?? 0);

if (!$id_tache) {
    redirect('taches.php');
}

// Vérifier que la tâche appartient à l'encadreur
$stmt = $pdo->prepare("
    SELECT id_stagiaire, titre FROM taches 
    WHERE id_tache = ? AND id_encadreur = ?
");
$stmt->execute([$id_tache, $user_id]);
$tache = $stmt->fetch();

if (!$tache) {
    $_SESSION['flash']['danger'] = "Tâche non trouvée";
    redirect('taches.php');
}

try {
    // Supprimer la tâche
    $stmt = $pdo->prepare("DELETE FROM taches WHERE id_tache = ? AND id_encadreur = ?");
    $stmt->execute([$id_tache, $user_id]);
    
    // Notification au stagiaire
    create_notification(
        $tache['id_stagiaire'],
        '🗑️ Tâche supprimée',
        "La tâche **{$tache['titre']}** a été supprimée par votre encadreur.",
        'danger',
        "/dashboard/stagiaire/taches.php"
    );
    
    $_SESSION['flash']['success'] = "Tâche supprimée avec succès !";
    
} catch (Exception $e) {
    $_SESSION['flash']['danger'] = "Erreur lors de la suppression";
}

redirect('taches.php');
?>