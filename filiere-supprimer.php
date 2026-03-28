<?php
/**
 * Supprimer une filière (Admin)
 */

require_once '../../includes/config.php';
require_once '../../includes/functions.php';
require_once '../../includes/auth.php';

require_role('admin');

$id = (int)($_GET['id'] ?? 0);

if (!$id) {
    $_SESSION['flash'] = ['type' => 'danger', 'message' => 'ID filière manquant'];
    redirect('filieres.php');
}

// Vérifier le token CSRF
if (!verify_csrf_token($_GET['csrf_token'] ?? '')) {
    $_SESSION['flash'] = ['type' => 'danger', 'message' => 'Token de sécurité invalide'];
    redirect('filieres.php');
}

try {
    // Vérifier si des stagiaires sont liés
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM stagiaires WHERE id_filiere = ?");
    $stmt->execute([$id]);
    $count = $stmt->fetchColumn();
    
    if ($count > 0) {
        $_SESSION['flash'] = ['type' => 'danger', 'message' => "Impossible de supprimer cette filière car $count stagiaire(s) y sont rattachés"];
    } else {
        $stmt = $pdo->prepare("DELETE FROM filieres WHERE id_filiere = ?");
        $stmt->execute([$id]);
        $_SESSION['flash'] = ['type' => 'success', 'message' => 'Filière supprimée avec succès'];
    }
} catch (PDOException $e) {
    $_SESSION['flash'] = ['type' => 'danger', 'message' => 'Erreur: ' . $e->getMessage()];
}

redirect('filieres.php');
?>