<?php
/**
 * Voir les stagiaires d'une filière (Admin)
 */

require_once '../../includes/config.php';
require_once '../../includes/functions.php';
require_once '../../includes/auth.php';

require_role('admin');

$id_filiere = (int)($_GET['id'] ?? 0);

if (!$id_filiere) {
    redirect('filieres.php');
}

// Récupérer les informations de la filière
$stmt = $pdo->prepare("SELECT * FROM filieres WHERE id_filiere = ?");
$stmt->execute([$id_filiere]);
$filiere = $stmt->fetch();

if (!$filiere) {
    $_SESSION['flash'] = ['type' => 'danger', 'message' => 'Filière non trouvée'];
    redirect('filieres.php');
}

// 🔧 CORRECTION : Jointure correcte avec encadreurs et utilisateurs
$stmt = $pdo->prepare("
    SELECT u.*, 
           s.*,
           eu.nom as encadreur_nom,
           eu.prenom as encadreur_prenom,
           eu.email as encadreur_email,
           e.profession as encadreur_profession
    FROM stagiaires s
    JOIN utilisateurs u ON s.id_stagiaire = u.id_utilisateur
    LEFT JOIN encadreurs e ON s.id_encadreur = e.id_encadreur
    LEFT JOIN utilisateurs eu ON e.id_encadreur = eu.id_utilisateur
    WHERE s.id_filiere = ?
    ORDER BY u.nom, u.prenom
");
$stmt->execute([$id_filiere]);
$stagiaires = $stmt->fetchAll();

$total = count($stagiaires);
$actifs = count(array_filter($stagiaires, function($s) { return $s['statut_inscription'] == 'actif'; }));
$termines = count(array_filter($stagiaires, function($s) { return $s['statut_inscription'] == 'termine'; }));
$en_attente = count(array_filter($stagiaires, function($s) { return $s['statut_inscription'] == 'en_attente'; }));

$page_title = "Stagiaires - " . $filiere['nom_filiere'] . " - School-Connection";
$couleur = $filiere['couleur'] ?? '#667eea';

include '../../includes/header.php';
?>

<style>
    @keyframes fadeInUp {
        from {
            opacity: 0;
            transform: translateY(30px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }
    
    .animate-fadeInUp {
        animation: fadeInUp 0.6s ease-out forwards;
    }
    
    .hero-filiere {
        background: linear-gradient(135deg, <?= $couleur ?> 0%, <?= $couleur ?>cc 100%);
        border-radius: 30px;
        padding: 40px;
        margin-bottom: 30px;
        position: relative;
        overflow: hidden;
    }
    
    .hero-filiere::before {
        content: '';
        position: absolute;
        top: -50%;
        right: -50%;
        width: 200%;
        height: 200%;
        background: radial-gradient(circle, rgba(255,255,255,0.1) 1%, transparent 1%);
        background-size: 50px 50px;
        animation: shine 20s linear infinite;
        pointer-events: none;
    }
    
    @keyframes shine {
        from { transform: translate(0, 0); }
        to { transform: translate(50px, 50px); }
    }
    
    .stagiaire-card {
        background: white;
        border-radius: 20px;
        padding: 20px;
        transition: all 0.3s ease;
        border: 1px solid rgba(0,0,0,0.05);
        height: 100%;
    }
    
    .stagiaire-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 15px 30px rgba(0,0,0,0.1);
    }
    
    .stat-card {
        background: white;
        border-radius: 20px;
        padding: 20px;
        text-align: center;
        transition: all 0.3s ease;
    }
    
    .stat-card:hover {
        transform: translateY(-3px);
        box-shadow: 0 10px 20px rgba(0,0,0,0.1);
    }
    
    .empty-state {
        text-align: center;
        padding: 60px 20px;
        background: linear-gradient(135deg, #f8f9ff, #f0f2ff);
        border-radius: 30px;
    }
    
    .stat-number {
        font-size: 2rem;
        font-weight: 800;
    }
    
    .btn-back {
        background: white;
        border-radius: 50px;
        padding: 10px 25px;
        transition: all 0.3s ease;
        color: <?= $couleur ?>;
        font-weight: 500;
        text-decoration: none;
        display: inline-block;
    }
    
    .btn-back:hover {
        transform: translateY(-2px);
        box-shadow: 0 5px 15px rgba(0,0,0,0.2);
        color: <?= $couleur ?>;
    }
    
    @media (max-width: 768px) {
        .hero-filiere { padding: 25px; }
        .stat-number { font-size: 1.5rem; }
    }
</style>

<div class="container-fluid">
    <div class="hero-filiere animate-fadeInUp">
        <div class="row align-items-center">
            <div class="col-md-8">
                <h1 class="display-4 fw-bold text-white mb-3">
                    <i class="fas fa-users me-3"></i>
                    <?= e($filiere['nom_filiere']) ?>
                    <?php if (!empty($filiere['code_filiere'])): ?>
                        <small class="text-white-50">(<?= e($filiere['code_filiere']) ?>)</small>
                    <?php endif; ?>
                </h1>
                <p class="lead text-white-50 mb-0">
                    <?= $total ?> stagiaire(s) dans cette filière
                </p>
            </div>
            <div class="col-md-4 text-md-end mt-3 mt-md-0">
                <a href="filieres.php" class="btn-back">
                    <i class="fas fa-arrow-left me-2"></i>Retour aux filières
                </a>
            </div>
        </div>
    </div>
    
    <!-- Statistiques -->
    <div class="row g-4 mb-4">
        <div class="col-md-3">
            <div class="stat-card animate-fadeInUp" style="animation-delay: 0s">
                <h3 class="stat-number" style="color: <?= $couleur ?>"><?= $total ?></h3>
                <small class="text-muted">Total stagiaires</small>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stat-card animate-fadeInUp" style="animation-delay: 0.1s">
                <h3 class="stat-number text-success"><?= $actifs ?></h3>
                <small class="text-muted">Actifs</small>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stat-card animate-fadeInUp" style="animation-delay: 0.2s">
                <h3 class="stat-number text-info"><?= $termines ?></h3>
                <small class="text-muted">Terminés</small>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stat-card animate-fadeInUp" style="animation-delay: 0.3s">
                <h3 class="stat-number text-warning"><?= $en_attente ?></h3>
                <small class="text-muted">En attente</small>
            </div>
        </div>
    </div>
    
    <!-- Description de la filière -->
    <?php if (!empty($filiere['description'])): ?>
    <div class="row mb-4">
        <div class="col-12">
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <h6 class="fw-bold mb-2"><i class="fas fa-info-circle me-2 text-primary"></i>Description</h6>
                    <p class="mb-0"><?= nl2br(e($filiere['description'])) ?></p>
                    <?php if (!empty($filiere['duree_mois'])): ?>
                        <small class="text-muted mt-2 d-block">
                            <i class="fas fa-clock me-1"></i>Durée: <?= $filiere['duree_mois'] ?> mois
                        </small>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>
    
    <!-- Liste des stagiaires -->
    <div class="row g-4">
        <?php if ($stagiaires): ?>
            <?php foreach ($stagiaires as $index => $s): 
                $delay = ($index % 3) * 0.1;
            ?>
            <div class="col-md-6 col-lg-4 animate-fadeInUp" style="animation-delay: <?= $delay ?>s">
                <div class="stagiaire-card">
                    <div class="d-flex align-items-center gap-3">
                        <img src="<?= getPhotoUrl($s['photo'] ?? '') ?>" 
                             alt="" class="rounded-circle" width="55" height="55" style="object-fit: cover; border: 2px solid <?= $couleur ?>;">
                        <div>
                            <h6 class="mb-0"><?= e($s['prenom'] . ' ' . $s['nom']) ?></h6>
                            <small class="text-muted"><?= e($s['email']) ?></small>
                        </div>
                    </div>
                    <hr>
                    <div class="small">
                        <div class="d-flex justify-content-between mb-2">
                            <span class="text-muted">Niveau:</span>
                            <span><?= e($s['niveau_etude'] ?? 'Non spécifié') ?></span>
                        </div>
                        <div class="d-flex justify-content-between mb-2">
                            <span class="text-muted">Établissement:</span>
                            <span><?= e($s['etablissement'] ?? 'Non spécifié') ?></span>
                        </div>
                        <div class="d-flex justify-content-between mb-2">
                            <span class="text-muted">Thème:</span>
                            <span><?= e(truncate($s['theme_stage'] ?? 'Non défini', 40)) ?></span>
                        </div>
                        <div class="d-flex justify-content-between">
                            <span class="text-muted">Période:</span>
                            <span><?= format_date($s['date_debut']) ?> - <?= format_date($s['date_fin']) ?></span>
                        </div>
                        <?php if (!empty($s['encadreur_nom'])): ?>
                        <div class="d-flex justify-content-between mt-2">
                            <span class="text-muted">Encadreur:</span>
                            <span><?= e($s['encadreur_prenom'] . ' ' . $s['encadreur_nom']) ?></span>
                        </div>
                        <?php endif; ?>
                    </div>
                    <div class="mt-3">
                        <span class="badge bg-<?= get_status_badge($s['statut_inscription']) ?>">
                            <i class="fas fa-<?= $s['statut_inscription'] == 'actif' ? 'check-circle' : ($s['statut_inscription'] == 'termine' ? 'flag-checkered' : 'clock') ?> me-1"></i>
                            <?= $s['statut_inscription'] ?>
                        </span>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="col-12">
                <div class="empty-state">
                    <i class="fas fa-user-graduate fa-5x text-muted mb-4"></i>
                    <h3>Aucun stagiaire</h3>
                    <p class="text-muted mb-4">Aucun stagiaire n'est inscrit dans cette filière pour le moment.</p>
                   
                    </a>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>