<?php
/**
 * Supprimer un utilisateur (Admin) - Gère tous les types d'utilisateurs
 */

require_once '../../includes/config.php';
require_once '../../includes/functions.php';
require_once '../../includes/auth.php';

// Vérifier le rôle
require_role('admin');

$user_id = (int)($_GET['id'] ?? 0);
$type = $_GET['type'] ?? '';
$confirm = isset($_GET['confirm']);

if (!$user_id || !$type) {
    $_SESSION['flash']['danger'] = "Paramètres manquants";
    redirect('utilisateurs.php');
}

// Empêcher la suppression de son propre compte
if ($user_id === $_SESSION['user_id']) {
    $_SESSION['flash']['danger'] = "Vous ne pouvez pas supprimer votre propre compte";
    redirect('utilisateurs.php');
}

// Vérifier le type
if (!in_array($type, ['stagiaire', 'encadreur', 'secretaire'])) {
    $_SESSION['flash']['danger'] = "Type d'utilisateur invalide";
    redirect('utilisateurs.php');
}

// Récupérer les informations de l'utilisateur
$stmt = $pdo->prepare("SELECT nom, prenom, email, role, photo FROM utilisateurs WHERE id_utilisateur = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();

if (!$user) {
    $_SESSION['flash']['danger'] = "Utilisateur non trouvé";
    redirect('utilisateurs.php');
}

// Vérifier la cohérence du type
if ($type === 'stagiaire' && $user['role'] !== 'stagiaire') {
    $_SESSION['flash']['danger'] = "L'utilisateur sélectionné n'est pas un stagiaire";
    redirect('stagiaires.php');
}
if ($type === 'encadreur' && strpos($user['role'], 'encadreur') === false) {
    $_SESSION['flash']['danger'] = "L'utilisateur sélectionné n'est pas un encadreur";
    redirect('encadreurs.php');
}
if ($type === 'secretaire' && $user['role'] !== 'secretaire') {
    $_SESSION['flash']['danger'] = "L'utilisateur sélectionné n'est pas un secrétaire";
    redirect('secretaires.php');
}

// Traitement de la suppression
if ($confirm) {
    try {
        $pdo->beginTransaction();
        
        // Récupérer le chemin de la photo pour la supprimer
        $photo_path = $user['photo'];
        
        // Supprimer les fichiers (documents, photos)
        if ($type === 'stagiaire') {
            // Supprimer les documents du stagiaire
            $stmt = $pdo->prepare("SELECT chemin FROM documents WHERE id_stagiaire = ?");
            $stmt->execute([$user_id]);
            $documents = $stmt->fetchAll();
            
            foreach ($documents as $doc) {
                if (file_exists(ROOT_PATH . $doc['chemin'])) {
                    unlink(ROOT_PATH . $doc['chemin']);
                }
            }
            
            // Supprimer les relations d'encadrement
            $pdo->prepare("DELETE FROM relations_encadrement WHERE id_stagiaire = ?")->execute([$user_id]);
            
            // Supprimer les rendez-vous
            $pdo->prepare("DELETE FROM rendez_vous WHERE id_stagiaire = ?")->execute([$user_id]);
            
            // Supprimer les tâches
            $pdo->prepare("DELETE FROM taches WHERE id_stagiaire = ?")->execute([$user_id]);
            
            // Supprimer les évaluations
            $pdo->prepare("DELETE FROM evaluations WHERE id_stagiaire = ?")->execute([$user_id]);
            
            // Supprimer le stagiaire
            $pdo->prepare("DELETE FROM stagiaires WHERE id_stagiaire = ?")->execute([$user_id]);
        }
        
        if ($type === 'encadreur') {
            // Vérifier s'il a des stagiaires actifs
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM relations_encadrement WHERE id_encadreur = ? AND statut = 'active'");
            $stmt->execute([$user_id]);
            if ($stmt->fetchColumn() > 0) {
                throw new Exception("Cet encadreur a encore des stagiaires actifs. Veuillez d'abord réaffecter ses stagiaires.");
            }
            
            // Supprimer les relations d'encadrement
            $pdo->prepare("DELETE FROM relations_encadrement WHERE id_encadreur = ?")->execute([$user_id]);
            
            // Supprimer les rendez-vous
            $pdo->prepare("DELETE FROM rendez_vous WHERE id_encadreur = ?")->execute([$user_id]);
            
            // Supprimer les tâches
            $pdo->prepare("DELETE FROM taches WHERE id_encadreur = ?")->execute([$user_id]);
            
            // Supprimer les évaluations
            $pdo->prepare("DELETE FROM evaluations WHERE id_encadreur = ?")->execute([$user_id]);
            
            // Supprimer l'encadreur
            $pdo->prepare("DELETE FROM encadreurs WHERE id_encadreur = ?")->execute([$user_id]);
        }
        
        if ($type === 'secretaire') {
            // Supprimer la secrétaire
            $pdo->prepare("DELETE FROM secretaires WHERE id_secretaire = ?")->execute([$user_id]);
        }
        
        // Supprimer les notifications
        $pdo->prepare("DELETE FROM notifications WHERE id_utilisateur = ?")->execute([$user_id]);
        
        // Supprimer les messages (expéditeur ou destinataire)
        $pdo->prepare("DELETE FROM messages WHERE id_expediteur = ? OR id_destinataire = ?")->execute([$user_id, $user_id]);
        
        // Supprimer les participations aux conversations
        $pdo->prepare("DELETE FROM participants_conversation WHERE id_utilisateur = ?")->execute([$user_id]);
        
        // Supprimer les sessions
        $pdo->prepare("DELETE FROM sessions WHERE id_utilisateur = ?")->execute([$user_id]);
        
        // Supprimer les logs
        $pdo->prepare("DELETE FROM logs_activite WHERE id_utilisateur = ?")->execute([$user_id]);
        
        // Supprimer la photo si ce n'est pas la photo par défaut
        if (!empty($photo_path) && $photo_path !== 'default-avatar.png' && $photo_path !== 'uploads/photos/default-avatar.png') {
            $full_path = ROOT_PATH . $photo_path;
            if (file_exists($full_path)) {
                unlink($full_path);
            }
        }
        
        // Enfin, supprimer l'utilisateur
        $pdo->prepare("DELETE FROM utilisateurs WHERE id_utilisateur = ?")->execute([$user_id]);
        
        $pdo->commit();
        
        // Journaliser l'action
        log_action($_SESSION['user_id'], 'DELETE_USER', "Suppression de l'utilisateur: {$user['prenom']} {$user['nom']} ({$user['email']}) - Type: $type", 'suppression', 'utilisateurs', $user_id);
        
        $_SESSION['flash']['success'] = "L'utilisateur a été supprimé avec succès";
        
        // Rediriger selon le type
        switch ($type) {
            case 'stagiaire':
                redirect('stagiaires.php');
                break;
            case 'encadreur':
                redirect('encadreurs.php');
                break;
            case 'secretaire':
                redirect('secretaires.php');
                break;
            default:
                redirect('utilisateurs.php');
        }
        
    } catch (Exception $e) {
        $pdo->rollBack();
        $_SESSION['flash']['danger'] = "Erreur lors de la suppression : " . $e->getMessage();
        
        // Rediriger selon le type
        switch ($type) {
            case 'stagiaire':
                redirect('stagiaires.php');
                break;
            case 'encadreur':
                redirect('encadreurs.php');
                break;
            case 'secretaire':
                redirect('secretaires.php');
                break;
            default:
                redirect('utilisateurs.php');
        }
    }
}

// Page de confirmation
$page_title = "Confirmer la suppression - School-Connection";
include '../../includes/header.php';
?>

<div class="container-fluid">
    <div class="row justify-content-center">
        <div class="col-md-6">
            <div class="card border-0 shadow-lg mt-5">
                <div class="card-header bg-danger text-white text-center py-4">
                    <i class="fas fa-exclamation-triangle fa-4x mb-3"></i>
                    <h3 class="mb-0">Confirmer la suppression</h3>
                </div>
                <div class="card-body p-5 text-center">
                    <h5 class="mb-4">Êtes-vous sûr de vouloir supprimer :</h5>
                    
                    <div class="d-flex align-items-center justify-content-center mb-4">
                        <img src="<?= getPhotoUrl($user['photo'] ?? '') ?>" 
                             alt="" class="rounded-circle me-3" width="60" height="60" style="object-fit: cover;">
                        <div class="text-start">
                            <h4 class="mb-1"><?= e($user['prenom'] . ' ' . $user['nom']) ?></h4>
                            <p class="mb-0 text-muted">
                                <?= e($user['email']) ?><br>
                                <span class="badge bg-<?= 
                                    $type === 'stagiaire' ? 'primary' : 
                                    ($type === 'encadreur' ? 'info' : 'warning') 
                                ?>">
                                    <?= $type === 'stagiaire' ? 'Stagiaire' : ($type === 'encadreur' ? 'Encadreur' : 'Secrétaire') ?>
                                </span>
                            </p>
                        </div>
                    </div>
                    
                    <div class="alert alert-warning">
                        <i class="fas fa-exclamation-circle me-2"></i>
                        <strong>Attention !</strong> Cette action est irréversible.
                        Toutes les données associées à cet utilisateur seront définitivement supprimées.
                    </div>
                    
                    <?php if ($type === 'encadreur'): 
                        // Vérifier s'il a des stagiaires actifs
                        $stmt = $pdo->prepare("SELECT COUNT(*) FROM relations_encadrement WHERE id_encadreur = ? AND statut = 'active'");
                        $stmt->execute([$user_id]);
                        $stagiaires_actifs = $stmt->fetchColumn();
                        if ($stagiaires_actifs > 0):
                    ?>
                        <div class="alert alert-danger">
                            <i class="fas fa-users me-2"></i>
                            <strong>Cet encadreur a encore <?= $stagiaires_actifs ?> stagiaire(s) actif(s).</strong><br>
                            Vous devez d'abord réaffecter ses stagiaires avant de pouvoir le supprimer.
                        </div>
                        <a href="encadreurs.php" class="btn btn-secondary btn-lg">
                            <i class="fas fa-arrow-left me-2"></i>Retour
                        </a>
                    <?php else: ?>
                        <div class="d-flex justify-content-between">
                            <a href="encadreurs.php" class="btn btn-outline-secondary btn-lg">
                                <i class="fas fa-times me-2"></i>Annuler
                            </a>
                            <a href="?id=<?= $user_id ?>&type=<?= $type ?>&confirm=1" 
                               class="btn btn-danger btn-lg">
                                <i class="fas fa-trash me-2"></i>Confirmer la suppression
                            </a>
                        </div>
                    <?php endif; ?>
                    
                    <?php elseif ($type === 'stagiaire'): ?>
                        <div class="d-flex justify-content-between">
                            <a href="stagiaires.php" class="btn btn-outline-secondary btn-lg">
                                <i class="fas fa-times me-2"></i>Annuler
                            </a>
                            <a href="?id=<?= $user_id ?>&type=<?= $type ?>&confirm=1" 
                               class="btn btn-danger btn-lg">
                                <i class="fas fa-trash me-2"></i>Confirmer la suppression
                            </a>
                        </div>
                    <?php else: ?>
                        <div class="d-flex justify-content-between">
                            <a href="secretaires.php" class="btn btn-outline-secondary btn-lg">
                                <i class="fas fa-times me-2"></i>Annuler
                            </a>
                            <a href="?id=<?= $user_id ?>&type=<?= $type ?>&confirm=1" 
                               class="btn btn-danger btn-lg">
                                <i class="fas fa-trash me-2"></i>Confirmer la suppression
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>