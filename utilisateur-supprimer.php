<?php
/**
 * Supprimer un utilisateur (Admin)
 */

require_once '../../includes/config.php';
require_once '../../includes/functions.php';
require_once '../../includes/auth.php';

// Vérifier le rôle
require_role('admin');

$id = (int)($_GET['id'] ?? 0);

if (!$id) {
    $_SESSION['flash']['danger'] = "ID utilisateur manquant";
    redirect('utilisateurs.php');
}

// Empêcher la suppression de son propre compte
if ($id === $_SESSION['user_id']) {
    $_SESSION['flash']['danger'] = "Vous ne pouvez pas supprimer votre propre compte";
    redirect('utilisateurs.php');
}

try {
    $pdo->beginTransaction();
    
    // Récupérer les informations de l'utilisateur
    $stmt = $pdo->prepare("SELECT nom, prenom, email, role, photo FROM utilisateurs WHERE id_utilisateur = ?");
    $stmt->execute([$id]);
    $user = $stmt->fetch();
    
    if (!$user) {
        throw new Exception("Utilisateur non trouvé");
    }
    
    // Supprimer les fichiers (documents, photos)
    if ($user['role'] === 'stagiaire') {
        // Supprimer les documents
        $stmt = $pdo->prepare("SELECT chemin FROM documents WHERE id_stagiaire = ?");
        $stmt->execute([$id]);
        $documents = $stmt->fetchAll();
        
        foreach ($documents as $doc) {
            if (file_exists(ROOT_PATH . $doc['chemin'])) {
                unlink(ROOT_PATH . $doc['chemin']);
            }
        }
    }
    
    // Supprimer la photo si ce n'est pas la photo par défaut
    if (!empty($user['photo']) && $user['photo'] !== 'default-avatar.png') {
        if (file_exists(ROOT_PATH . $user['photo'])) {
            unlink(ROOT_PATH . $user['photo']);
        }
    }
    
    // Supprimer l'utilisateur (les contraintes CASCADE feront le reste)
    $stmt = $pdo->prepare("DELETE FROM utilisateurs WHERE id_utilisateur = ?");
    $stmt->execute([$id]);
    
    // Journaliser l'action
    log_action($_SESSION['user_id'], 'DELETE_USER', "Suppression de l'utilisateur: {$user['prenom']} {$user['nom']} ({$user['email']})", 'suppression', 'utilisateurs', $id);
    
    $pdo->commit();
    
    $_SESSION['flash']['success'] = "L'utilisateur a été supprimé avec succès";
    
} catch (Exception $e) {
    $pdo->rollBack();
    $_SESSION['flash']['danger'] = "Erreur lors de la suppression : " . $e->getMessage();
}

redirect('utilisateurs.php');
?>