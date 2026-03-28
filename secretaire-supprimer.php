<?php
/**
 * Supprimer un secrétaire (Admin)
 */

require_once '../../includes/config.php';
require_once '../../includes/functions.php';
require_once '../../includes/auth.php';

// Vérifier le rôle
require_role('admin');

$id = (int)($_GET['id'] ?? 0);

if (!$id) {
    $_SESSION['flash'] = ['type' => 'danger', 'message' => 'ID secrétaire manquant'];
    redirect('secretaires.php');
}

// Vérifier le token CSRF
if (!isset($_GET['csrf_token']) || !verify_csrf_token($_GET['csrf_token'])) {
    $_SESSION['flash'] = ['type' => 'danger', 'message' => 'Token de sécurité invalide'];
    redirect('secretaires.php');
}

// Vérifier que ce n'est pas le compte de l'utilisateur connecté
if ($id == $_SESSION['user_id']) {
    $_SESSION['flash'] = ['type' => 'danger', 'message' => 'Vous ne pouvez pas supprimer votre propre compte'];
    redirect('secretaires.php');
}

try {
    // Récupérer les informations du secrétaire avant suppression
    $stmt = $pdo->prepare("
        SELECT u.*, s.* 
        FROM utilisateurs u 
        JOIN secretaires s ON u.id_utilisateur = s.id_secretaire 
        WHERE u.id_utilisateur = ?
    ");
    $stmt->execute([$id]);
    $secretaire = $stmt->fetch();

    if (!$secretaire) {
        $_SESSION['flash'] = ['type' => 'danger', 'message' => 'Secrétaire non trouvé'];
        redirect('secretaires.php');
    }

    // Vérifier si le secrétaire a des actions dans les logs (optionnel)
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM logs_activite WHERE id_utilisateur = ?");
    $stmt->execute([$id]);
    $nb_logs = $stmt->fetchColumn();

    // Démarrer une transaction
    $pdo->beginTransaction();

    // Supprimer les logs associés (optionnel - si vous voulez garder les logs, commentez cette ligne)
    // $stmt = $pdo->prepare("DELETE FROM logs_activite WHERE id_utilisateur = ?");
    // $stmt->execute([$id]);

    // Supprimer le secrétaire de la table secretaires
    $stmt = $pdo->prepare("DELETE FROM secretaires WHERE id_secretaire = ?");
    $stmt->execute([$id]);

    // Supprimer l'utilisateur de la table utilisateurs
    $stmt = $pdo->prepare("DELETE FROM utilisateurs WHERE id_utilisateur = ?");
    $stmt->execute([$id]);

    // Journaliser l'action
    log_action(
        $_SESSION['user_id'], 
        'DELETE_SECRETAIRE', 
        "Suppression du secrétaire: {$secretaire['prenom']} {$secretaire['nom']} (ID: $id)", 
        'suppression', 
        'secretaires', 
        $id
    );

    // Valider la transaction
    $pdo->commit();

    // Supprimer la photo si elle existe et n'est pas l'image par défaut
    if (!empty($secretaire['photo']) && $secretaire['photo'] !== 'default-avatar.png') {
        $photo_path = ROOT_PATH . $secretaire['photo'];
        if (file_exists($photo_path)) {
            unlink($photo_path);
        }
    }

    $_SESSION['flash'] = [
        'type' => 'success', 
        'message' => "Le secrétaire {$secretaire['prenom']} {$secretaire['nom']} a été supprimé avec succès"
    ];

} catch (PDOException $e) {
    // Annuler la transaction en cas d'erreur
    $pdo->rollBack();
    
    $_SESSION['flash'] = [
        'type' => 'danger', 
        'message' => 'Erreur lors de la suppression : ' . $e->getMessage()
    ];
}

redirect('secretaires.php');
?>