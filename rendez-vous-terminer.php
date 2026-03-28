<?php
/**
 * Marquer un rendez-vous comme terminé (Encadreur)
 */

require_once '../../includes/config.php';
require_once '../../includes/functions.php';
require_once '../../includes/auth.php';

// Vérifier le rôle
require_any_role(['encadreur_pro', 'encadreur_acro']);

$user_id = $_SESSION['user_id'];

// Vérifier le token CSRF
if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
    $_SESSION['flash']['danger'] = 'Token de sécurité invalide';
    redirect('rendez-vous.php');
}

$id_rdv = (int)($_POST['id_rdv'] ?? 0);
$notes = cleanInput($_POST['notes'] ?? '');

if (!$id_rdv) {
    $_SESSION['flash']['danger'] = "ID rendez-vous manquant";
    redirect('rendez-vous.php');
}

try {
    $pdo->beginTransaction();
    
    // Récupérer les informations du rendez-vous
    $stmt = $pdo->prepare("
        SELECT r.*, u.nom, u.prenom 
        FROM rendez_vous r
        JOIN stagiaires s ON r.id_stagiaire = s.id_stagiaire
        JOIN utilisateurs u ON s.id_stagiaire = u.id_utilisateur
        WHERE r.id_rdv = ? AND r.id_encadreur = ?
    ");
    $stmt->execute([$id_rdv, $user_id]);
    $rdv = $stmt->fetch();
    
    if (!$rdv) {
        throw new Exception("Rendez-vous non trouvé");
    }
    
    // Mettre à jour le statut
    $stmt = $pdo->prepare("
        UPDATE rendez_vous 
        SET statut = 'termine', notes = CONCAT(notes, ?)
        WHERE id_rdv = ?
    ");
    $notes_text = $notes ? "\n\nNotes de fin: " . date('d/m/Y H:i') . " - " . $notes : "";
    $stmt->execute([$notes_text, $id_rdv]);
    
    // Créer une notification pour le stagiaire
    create_notification(
        $rdv['id_stagiaire'],
        'Rendez-vous terminé',
        "Le rendez-vous '{$rdv['titre']}' a été marqué comme terminé.",
        'info',
        "/dashboard/stagiaire/rendez-vous.php"
    );
    
    // Journaliser l'action
    log_action($user_id, 'COMPLETE_RDV', "Rendez-vous #$id_rdv marqué comme terminé", 'modification', 'rendez_vous', $id_rdv);
    
    $pdo->commit();
    
    $_SESSION['flash']['success'] = "Rendez-vous marqué comme terminé";
    
} catch (Exception $e) {
    $pdo->rollBack();
    $_SESSION['flash']['danger'] = "Erreur : " . $e->getMessage();
}

redirect('rendez-vous.php');
?>