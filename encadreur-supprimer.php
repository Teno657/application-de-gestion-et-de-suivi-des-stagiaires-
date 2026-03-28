<?php
/**
 * Supprimer un encadreur (Admin)
 */

require_once '../../includes/config.php';
require_once '../../includes/functions.php';
require_once '../../includes/auth.php';

// Vérifier le rôle
require_role('admin');

$id = (int)($_GET['id'] ?? 0);

if (!$id) {
    $_SESSION['flash'] = ['type' => 'danger', 'message' => 'ID encadreur manquant'];
    redirect('encadreurs.php');
}

// Vérifier le token CSRF
$csrf_token = $_GET['csrf_token'] ?? '';
if (!verify_csrf_token($csrf_token)) {
    $_SESSION['flash'] = ['type' => 'danger', 'message' => 'Token de sécurité invalide'];
    redirect('encadreurs.php');
}

try {
    // Récupérer les informations de l'encadreur avant suppression
    $stmt = $pdo->prepare("
        SELECT u.*, e.* 
        FROM utilisateurs u 
        JOIN encadreurs e ON u.id_utilisateur = e.id_encadreur 
        WHERE u.id_utilisateur = ?
    ");
    $stmt->execute([$id]);
    $encadreur = $stmt->fetch();

    if (!$encadreur) {
        $_SESSION['flash'] = ['type' => 'danger', 'message' => 'Encadreur non trouvé'];
        redirect('encadreurs.php');
    }

    // Vérifier si l'encadreur a des stagiaires
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM stagiaires WHERE id_encadreur = ?");
    $stmt->execute([$id]);
    $nb_stagiaires = $stmt->fetchColumn();

    if ($nb_stagiaires > 0) {
        $_SESSION['flash'] = [
            'type' => 'danger', 
            'message' => "Impossible de supprimer cet encadreur car il encadre $nb_stagiaires stagiaire(s). Veuillez d'abord réaffecter ses stagiaires."
        ];
        redirect('encadreurs.php');
    }

    // Démarrer une transaction
    $pdo->beginTransaction();

    // Supprimer les tâches liées à cet encadreur
    $stmt = $pdo->prepare("DELETE FROM taches WHERE id_encadreur = ?");
    $stmt->execute([$id]);

    // Supprimer les rendez-vous liés à cet encadreur
    $stmt = $pdo->prepare("DELETE FROM rendez_vous WHERE id_encadreur = ?");
    $stmt->execute([$id]);

    // Supprimer l'encadreur de la table encadreurs
    $stmt = $pdo->prepare("DELETE FROM encadreurs WHERE id_encadreur = ?");
    $stmt->execute([$id]);

    // Supprimer l'utilisateur de la table utilisateurs
    $stmt = $pdo->prepare("DELETE FROM utilisateurs WHERE id_utilisateur = ?");
    $stmt->execute([$id]);

    // Journaliser l'action
    log_action(
        $_SESSION['user_id'], 
        'DELETE_ENCADREUR', 
        "Suppression de l'encadreur: {$encadreur['prenom']} {$encadreur['nom']} (ID: $id)", 
        'suppression', 
        'encadreurs', 
        $id
    );

    // Valider la transaction
    $pdo->commit();

    // Supprimer la photo si elle existe et n'est pas l'image par défaut
    if (!empty($encadreur['photo']) && $encadreur['photo'] !== 'default-avatar.png') {
        $photo_path = $_SERVER['DOCUMENT_ROOT'] . '/school-connection/' . $encadreur['photo'];
        if (file_exists($photo_path)) {
            unlink($photo_path);
        }
    }

    $_SESSION['flash'] = [
        'type' => 'success', 
        'message' => "L'encadreur {$encadreur['prenom']} {$encadreur['nom']} a été supprimé avec succès"
    ];

} catch (PDOException $e) {
    // Annuler la transaction en cas d'erreur
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    
    $_SESSION['flash'] = [
        'type' => 'danger', 
        'message' => 'Erreur lors de la suppression : ' . $e->getMessage()
    ];
}

redirect('encadreurs.php');
?>