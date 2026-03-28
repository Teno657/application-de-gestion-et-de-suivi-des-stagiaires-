<?php
/**
 * Voir les détails de l'encadreur (Stagiaire)
 */

require_once '../../includes/config.php';
require_once '../../includes/functions.php';
require_once '../../includes/auth.php';

// Vérifier le rôle
require_role('stagiaire');

$page_title = "Mon encadreur - School-Connection";
$user_id = $_SESSION['user_id'];

// 🔧 CORRECTION : Récupérer les informations de l'encadreur directement depuis la table stagiaires
$stmt = $pdo->prepare("
    SELECT u.*, 
           e.profession, 
           e.specialite, 
           e.entreprise, 
           e.bio,
           e.annees_experience, 
           e.disponible,
           e.stagiaires_actuels,
           e.max_stagiaires
    FROM stagiaires s
    LEFT JOIN encadreurs e ON s.id_encadreur = e.id_encadreur
    LEFT JOIN utilisateurs u ON e.id_encadreur = u.id_utilisateur
    WHERE s.id_stagiaire = ?
");
$stmt->execute([$user_id]);
$encadreur = $stmt->fetch();

// Si aucun encadreur trouvé
if (!$encadreur) {
    $no_encadreur = true;
}

// Statistiques des tâches avec cet encadreur
$stats = ['total' => 0, 'termine' => 0, 'en_cours' => 0];
if ($encadreur && isset($encadreur['id_utilisateur'])) {
    $stmt = $pdo->prepare("
        SELECT 
            COUNT(*) as total,
            SUM(CASE WHEN statut = 'termine' THEN 1 ELSE 0 END) as termine,
            SUM(CASE WHEN statut != 'termine' AND statut != 'annule' THEN 1 ELSE 0 END) as en_cours
        FROM taches
        WHERE id_stagiaire = ? AND id_encadreur = ?
    ");
    $stmt->execute([$user_id, $encadreur['id_utilisateur']]);
    $stats = $stmt->fetch();
    
    if (!$stats) {
        $stats = ['total' => 0, 'termine' => 0, 'en_cours' => 0];
    }
}

include '../../includes/header.php';
?>

<style>
    .hero-encadreur {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        border-radius: 25px;
        padding: 40px;
        margin-bottom: 30px;
    }
    
    .profile-card {
        background: white;
        border-radius: 20px;
        overflow: hidden;
        box-shadow: 0 10px 30px rgba(0,0,0,0.1);
        transition: transform 0.3s ease;
    }
    
    .profile-card:hover {
        transform: translateY(-5px);
    }
    
    .profile-header {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        height: 100px;
    }
    
    .profile-avatar {
        width: 120px;
        height: 120px;
        border-radius: 50%;
        border: 4px solid white;
        margin-top: -60px;
        margin-bottom: 15px;
        object-fit: cover;
        background: white;
    }
    
    .stat-card {
        background: white;
        border-radius: 15px;
        padding: 20px;
        text-align: center;
        transition: all 0.3s ease;
        border: 1px solid rgba(0,0,0,0.05);
    }
    
    .stat-card:hover {
        transform: translateY(-3px);
        box-shadow: 0 10px 25px rgba(0,0,0,0.1);
    }
    
    .btn-quick {
        padding: 15px;
        border-radius: 12px;
        transition: all 0.3s ease;
    }
    
    .btn-quick:hover {
        transform: translateY(-3px);
    }
    
    .availability-badge {
        display: inline-block;
        padding: 5px 15px;
        border-radius: 20px;
        font-size: 0.8rem;
        font-weight: 600;
    }
    
    .availability-yes {
        background: #d1fae5;
        color: #065f46;
    }
    
    .availability-no {
        background: #fee2e2;
        color: #991b1b;
    }
</style>

<div class="container-fluid">
    <!-- Hero Section -->
    <div class="hero-encadreur">
        <div class="row align-items-center">
            <div class="col-md-8">
                <h1 class="display-4 fw-bold text-white mb-3">
                    <i class="fas fa-chalkboard-teacher me-3"></i>
                    Mon encadreur
                </h1>
                <p class="lead text-white-50 mb-0">
                    Informations sur votre encadreur de stage
                </p>
            </div>
            <?php if ($encadreur && isset($encadreur['id_utilisateur'])): ?>
            <div class="col-md-4 text-md-end mt-3 mt-md-0">
                <!-- ✅ CORRECTION : Chemin corrigé vers la messagerie -->
                <a href="../../messagerie/nouvelle.php?destinataire=<?= $encadreur['id_utilisateur'] ?>" 
                   class="btn btn-light btn-lg rounded-pill px-4">
                    <i class="fas fa-envelope me-2"></i>
                    Contacter mon encadreur
                </a>
            </div>
            <?php endif; ?>
        </div>
    </div>
    
    <?php if ($encadreur && isset($encadreur['id_utilisateur'])): ?>
    <div class="row g-4">
        <!-- Colonne gauche - Profil -->
        <div class="col-md-4">
            <div class="profile-card text-center">
                <div class="profile-header"></div>
                <div class="card-body">
                    <img src="<?= getPhotoUrl($encadreur['photo'] ?? '') ?>" 
                         alt="Photo" class="profile-avatar">
                    
                    <h4 class="mb-1"><?= e($encadreur['prenom'] . ' ' . $encadreur['nom']) ?></h4>
                    <p class="text-primary mb-2"><?= e($encadreur['profession'] ?? 'Encadreur') ?></p>
                    
                    <div class="mb-3">
                        <?php if ($encadreur['disponible']): ?>
                            <span class="availability-badge availability-yes">
                                <i class="fas fa-check-circle me-1"></i>Disponible
                            </span>
                        <?php else: ?>
                            <span class="availability-badge availability-no">
                                <i class="fas fa-clock me-1"></i>Non disponible
                            </span>
                        <?php endif; ?>
                    </div>
                    
                    <hr>
                    
                    <div class="text-start">
                        <p><i class="fas fa-envelope text-primary me-2"></i> <?= e($encadreur['email']) ?></p>
                        <p><i class="fas fa-phone text-primary me-2"></i> <?= e($encadreur['telephone'] ?? 'Non renseigné') ?></p>
                        <p><i class="fas fa-building text-primary me-2"></i> <?= e($encadreur['entreprise'] ?? 'Non renseignée') ?></p>
                        <p><i class="fas fa-tag text-primary me-2"></i> <?= e($encadreur['specialite'] ?? 'Non spécifiée') ?></p>
                        <p><i class="fas fa-briefcase text-primary me-2"></i> <?= $encadreur['annees_experience'] ?? 0 ?> ans d'expérience</p>
                        <?php if ($encadreur['stagiaires_actuels'] !== null): ?>
                        <p><i class="fas fa-users text-primary me-2"></i> <?= $encadreur['stagiaires_actuels'] ?> / <?= $encadreur['max_stagiaires'] ?? '?' ?> stagiaires</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Colonne droite - Informations détaillées -->
        <div class="col-md-8">
            <!-- Biographie -->
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white py-3">
                    <h6 class="card-title mb-0">
                        <i class="fas fa-user-circle me-2 text-primary"></i>Biographie
                    </h6>
                </div>
                <div class="card-body">
                    <?php if (!empty($encadreur['bio'])): ?>
                        <?= nl2br(e($encadreur['bio'])) ?>
                    <?php else: ?>
                        <p class="text-muted mb-0">Aucune biographie renseignée pour le moment.</p>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Statistiques -->
            <div class="row g-4 mb-4">
                <div class="col-md-4">
                    <div class="stat-card">
                        <i class="fas fa-tasks fa-2x text-primary mb-2"></i>
                        <h3 class="mb-0"><?= $stats['total'] ?? 0 ?></h3>
                        <small class="text-muted">Tâches totales</small>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="stat-card">
                        <i class="fas fa-check-circle fa-2x text-success mb-2"></i>
                        <h3 class="mb-0 text-success"><?= $stats['termine'] ?? 0 ?></h3>
                        <small class="text-muted">Tâches terminées</small>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="stat-card">
                        <i class="fas fa-spinner fa-2x text-warning mb-2"></i>
                        <h3 class="mb-0 text-warning"><?= $stats['en_cours'] ?? 0 ?></h3>
                        <small class="text-muted">Tâches en cours</small>
                    </div>
                </div>
            </div>
            
            <!-- Actions rapides -->
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white py-3">
                    <h6 class="card-title mb-0">
                        <i class="fas fa-bolt me-2 text-primary"></i>Actions rapides
                    </h6>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-4">
                            <!-- ✅ CORRECTION : Chemin corrigé vers la messagerie -->
                            <a href="../../messagerie/nouvelle.php?destinataire=<?= $encadreur['id_utilisateur'] ?>" 
                               class="btn btn-outline-primary btn-quick w-100">
                                <i class="fas fa-envelope fa-2x mb-2 d-block"></i>
                                Envoyer un message
                            </a>
                        </div>
                        <div class="col-md-4">
                            <a href="rendez-vous-demander.php" 
                               class="btn btn-outline-success btn-quick w-100">
                                <i class="fas fa-calendar-plus fa-2x mb-2 d-block"></i>
                                Demander rendez-vous
                            </a>
                        </div>
                        <div class="col-md-4">
                            <a href="taches.php" 
                               class="btn btn-outline-warning btn-quick w-100">
                                <i class="fas fa-tasks fa-2x mb-2 d-block"></i>
                                Voir mes tâches
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <?php else: ?>
    <!-- Message si aucun encadreur -->
    <div class="row">
        <div class="col-12">
            <div class="card border-0 shadow-sm text-center p-5">
                <div class="card-body py-5">
                    <i class="fas fa-chalkboard-teacher fa-5x text-muted mb-4"></i>
                    <h3 class="mb-3">Aucun encadreur assigné</h3>
                    <p class="text-muted mb-4">
                        Vous n'avez pas encore d'encadreur assigné pour votre stage.
                        <br>Veuillez contacter la secrétaire pour qu'un encadreur vous soit attribué.
                    </p>
                    <!-- ✅ CORRECTION : Chemin corrigé vers la messagerie -->
                    <a href="../../messagerie/nouvelle.php?destinataire=secretaire" class="btn btn-primary btn-lg rounded-pill px-5">
                        <i class="fas fa-envelope me-2"></i>
                        Contacter la secrétaire
                    </a>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>

<?php include '../../includes/footer.php'; ?>