<?php
/**
 * Supprimer un stagiaire (Admin)
 */

require_once '../../includes/config.php';
require_once '../../includes/functions.php';
require_once '../../includes/auth.php';

require_role('admin');

$id = (int)($_GET['id'] ?? 0);

if (!$id) {
    $_SESSION['flash'] = ['type' => 'danger', 'message' => 'ID stagiaire manquant'];
    header('Location: stagiaires.php');
    exit;
}

$csrf_token = $_GET['csrf_token'] ?? '';
if (!verify_csrf_token($csrf_token)) {
    $_SESSION['flash'] = ['type' => 'danger', 'message' => 'Token de sécurité invalide'];
    header('Location: stagiaires.php');
    exit;
}

try {
    $pdo->beginTransaction();
    
    // Supprimer les tâches
    $pdo->prepare("DELETE FROM taches WHERE id_stagiaire = ?")->execute([$id]);
    
    // Supprimer les rendez-vous
    $pdo->prepare("DELETE FROM rendez_vous WHERE id_stagiaire = ?")->execute([$id]);
    
    // Supprimer les documents
    $pdo->prepare("DELETE FROM documents WHERE id_utilisateur = ?")->execute([$id]);
    
    // Supprimer les notifications
    $pdo->prepare("DELETE FROM notifications WHERE id_utilisateur = ?")->execute([$id]);
    
    // Supprimer le stagiaire
    $pdo->prepare("DELETE FROM stagiaires WHERE id_stagiaire = ?")->execute([$id]);
    
    // Supprimer l'utilisateur
    $pdo->prepare("DELETE FROM utilisateurs WHERE id_utilisateur = ?")->execute([$id]);
    
    $pdo->commit();
    
    $_SESSION['flash'] = ['type' => 'success', 'message' => 'Stagiaire supprimé avec succès'];
    
} catch (PDOException $e) {
    $pdo->rollBack();
    $_SESSION['flash'] = ['type' => 'danger', 'message' => 'Erreur: ' . $e->getMessage()];
}

header('Location: stagiaires.php');
exit;
?>