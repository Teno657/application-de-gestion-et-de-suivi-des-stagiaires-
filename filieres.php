<?php
/**
 * Gestion des filières (Admin)
 * Avec suppression et bouton "Voir"
 */

require_once '../../includes/config.php';
require_once '../../includes/functions.php';
require_once '../../includes/auth.php';

// Vérifier le rôle
require_role('admin');

$page_title = "Gestion des filières - School-Connection";

// Traitement de la suppression
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $id_filiere = (int)$_GET['delete'];
    
    if (!verify_csrf_token($_GET['csrf_token'] ?? '')) {
        $_SESSION['flash'] = ['type' => 'danger', 'message' => 'Token de sécurité invalide'];
    } else {
        try {
            // Vérifier si des stagiaires sont liés
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM stagiaires WHERE id_filiere = ?");
            $stmt->execute([$id_filiere]);
            $count = $stmt->fetchColumn();
            
            if ($count > 0) {
                $_SESSION['flash'] = ['type' => 'danger', 'message' => "Impossible de supprimer cette filière car $count stagiaire(s) y sont rattachés"];
            } else {
                $stmt = $pdo->prepare("DELETE FROM filieres WHERE id_filiere = ?");
                $stmt->execute([$id_filiere]);
                $_SESSION['flash'] = ['type' => 'success', 'message' => 'Filière supprimée avec succès'];
            }
        } catch (PDOException $e) {
            $_SESSION['flash'] = ['type' => 'danger', 'message' => 'Erreur: ' . $e->getMessage()];
        }
    }
    redirect('filieres.php');
}

// Récupérer TOUTES les filières de la table filieres
$filieres = $pdo->query("
    SELECT f.*, 
           (SELECT COUNT(*) FROM stagiaires WHERE id_filiere = f.id_filiere) as nb_stagiaires,
           (SELECT COUNT(*) FROM stagiaires WHERE id_filiere = f.id_filiere AND statut_inscription = 'actif') as nb_actifs,
           (SELECT COUNT(*) FROM stagiaires WHERE id_filiere = f.id_filiere AND statut_inscription = 'termine') as nb_termines,
           (SELECT COUNT(*) FROM stagiaires WHERE id_filiere = f.id_filiere AND statut_inscription = 'en_attente') as nb_attente
    FROM filieres f
    ORDER BY f.nom_filiere
")->fetchAll();

$total_stagiaires = array_sum(array_column($filieres, 'nb_stagiaires'));

include '../../includes/header.php';
?>

<style>
    @keyframes fadeInUp {
        from {
            opacity: 0;
            transform: translateY(50px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }
    
    @keyframes scaleIn {
        from {
            opacity: 0;
            transform: scale(0.9);
        }
        to {
            opacity: 1;
            transform: scale(1);
        }
    }
    
    @keyframes slideInLeft {
        from {
            opacity: 0;
            transform: translateX(-40px);
        }
        to {
            opacity: 1;
            transform: translateX(0);
        }
    }
    
    @keyframes float {
        0%, 100% { transform: translateY(0); }
        50% { transform: translateY(-8px); }
    }
    
    @keyframes pulseRed {
        0%, 100% {
            box-shadow: 0 0 0 0 rgba(239, 68, 68, 0.4);
        }
        50% {
            box-shadow: 0 0 0 10px rgba(239, 68, 68, 0);
        }
    }
    
    .animate-fadeInUp {
        animation: fadeInUp 0.7s ease-out forwards;
        opacity: 0;
    }
    
    .animate-scaleIn {
        animation: scaleIn 0.5s ease-out forwards;
    }
    
    .animate-slideInLeft {
        animation: slideInLeft 0.6s ease-out forwards;
        opacity: 0;
    }
    
    .hero-filieres {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        border-radius: 35px;
        padding: 50px;
        margin-bottom: 40px;
        position: relative;
        overflow: hidden;
    }
    
    .hero-filieres::before {
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
    
    .filiere-card {
        background: white;
        border-radius: 28px;
        overflow: hidden;
        transition: all 0.5s cubic-bezier(0.175, 0.885, 0.32, 1.275);
        box-shadow: 0 15px 35px rgba(0,0,0,0.1);
        position: relative;
        height: 100%;
        animation: fadeInUp 0.6s ease-out forwards;
        opacity: 0;
        cursor: pointer;
    }
    
    .filiere-card:hover {
        transform: translateY(-15px) scale(1.02);
        box-shadow: 0 30px 60px rgba(0,0,0,0.2);
    }
    
    .card-gradient {
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        height: 120px;
        background: linear-gradient(135deg, #667eea, #764ba2);
        opacity: 0.9;
        transition: all 0.4s ease;
    }
    
    .filiere-card:hover .card-gradient {
        height: 100%;
        opacity: 0.95;
    }
    
    .card-content {
        position: relative;
        z-index: 2;
        padding: 30px;
        transition: all 0.4s ease;
    }
    
    .filiere-icon {
        width: 70px;
        height: 70px;
        background: linear-gradient(135deg, #667eea, #764ba2);
        border-radius: 20px;
        display: flex;
        align-items: center;
        justify-content: center;
        margin-bottom: 20px;
        transition: all 0.4s ease;
        animation: float 3s ease-in-out infinite;
    }
    
    .filiere-card:hover .filiere-icon {
        transform: rotate(10deg) scale(1.1);
        background: white;
    }
    
    .filiere-card:hover .filiere-icon i {
        color: #667eea;
    }
    
    .filiere-icon i {
        font-size: 2rem;
        color: white;
        transition: all 0.4s ease;
    }
    
    .filiere-title {
        font-size: 1.6rem;
        font-weight: 800;
        margin-bottom: 10px;
        transition: all 0.4s ease;
    }
    
    .filiere-card:hover .filiere-title {
        color: white;
    }
    
    .filiere-code {
        font-size: 0.7rem;
        background: rgba(0,0,0,0.1);
        padding: 3px 8px;
        border-radius: 20px;
        display: inline-block;
        margin-top: 5px;
    }
    
    .filiere-card:hover .filiere-code {
        background: rgba(255,255,255,0.2);
        color: white;
    }
    
    .filiere-desc {
        color: #666;
        font-size: 0.85rem;
        margin-bottom: 20px;
        transition: all 0.4s ease;
    }
    
    .filiere-card:hover .filiere-desc {
        color: rgba(255,255,255,0.8);
    }
    
    .stat-grid {
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        gap: 15px;
        margin: 20px 0;
        padding: 15px 0;
        border-top: 1px solid #f0f0f0;
        border-bottom: 1px solid #f0f0f0;
        transition: all 0.4s ease;
    }
    
    .filiere-card:hover .stat-grid {
        border-top-color: rgba(255,255,255,0.2);
        border-bottom-color: rgba(255,255,255,0.2);
    }
    
    .stat-item {
        text-align: center;
        transition: all 0.4s ease;
    }
    
    .stat-number {
        font-size: 1.8rem;
        font-weight: 800;
        line-height: 1;
        transition: all 0.4s ease;
    }
    
    .stat-label {
        font-size: 0.7rem;
        color: #888;
        margin-top: 5px;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        transition: all 0.4s ease;
    }
    
    .filiere-card:hover .stat-number {
        color: white;
    }
    
    .filiere-card:hover .stat-label {
        color: rgba(255,255,255,0.7);
    }
    
    .empty-filiere-badge {
        background: rgba(0,0,0,0.05);
        border-radius: 50px;
        padding: 5px 12px;
        font-size: 0.7rem;
        color: #888;
        display: inline-block;
    }
    
    .filiere-card:hover .empty-filiere-badge {
        background: rgba(255,255,255,0.2);
        color: white;
    }
    
    .btn-actions {
        display: flex;
        gap: 10px;
        margin-top: 15px;
    }
    
    .btn-detail {
        flex: 1;
        display: inline-block;
        padding: 10px 20px;
        background: rgba(102,126,234,0.1);
        border-radius: 50px;
        color: #667eea;
        text-decoration: none;
        font-weight: 500;
        font-size: 0.85rem;
        transition: all 0.4s ease;
        text-align: center;
    }
    
    .filiere-card:hover .btn-detail {
        background: white;
        color: #667eea;
        transform: translateY(-3px);
    }
    
    .btn-delete {
        flex: 0 0 auto;
        display: inline-block;
        padding: 10px 15px;
        background: rgba(239,68,68,0.1);
        border-radius: 50px;
        color: #ef4444;
        text-decoration: none;
        font-weight: 500;
        font-size: 0.85rem;
        transition: all 0.4s ease;
        text-align: center;
    }
    
    .btn-delete:hover {
        background: #ef4444;
        color: white;
        transform: translateY(-3px);
        animation: pulseRed 0.5s ease-out;
    }
    
    .filiere-card:hover .btn-delete {
        background: rgba(255,255,255,0.2);
        color: white;
    }
    
    .filiere-card:hover .btn-delete:hover {
        background: #ef4444;
        color: white;
    }
    
    @media (max-width: 768px) {
        .hero-filieres { padding: 30px; }
        .filiere-title { font-size: 1.3rem; }
        .stat-number { font-size: 1.3rem; }
        .stat-grid { gap: 10px; }
        .btn-actions { flex-direction: column; }
    }
</style>

<div class="container-fluid">
    <!-- Hero Section -->
    <div class="hero-filieres">
        <div class="row align-items-center">
            <div class="col-md-8">
                <h1 class="display-4 fw-bold text-white mb-3 animate-scaleIn">
                    <i class="fas fa-graduation-cap me-3"></i>
                    Gestion des filières
                </h1>
                <p class="lead text-white-50 mb-0 animate-slideInLeft">
                    <i class="fas fa-chart-line me-2"></i>
                    <?= count($filieres) ?> filière(s) | <?= $total_stagiaires ?> stagiaire(s) au total
                </p>
            </div>
            <div class="col-md-4 text-md-end mt-3 mt-md-0">
                <a href="filiere-ajouter.php" class="btn btn-light btn-lg rounded-pill px-4 shadow-sm animate-scaleIn">
                    <i class="fas fa-plus me-2"></i>
                    Nouvelle filière
                </a>
            </div>
        </div>
    </div>
    
    <!-- Messages flash -->
    <?php if (isset($_SESSION['flash'])): ?>
        <div class="alert alert-<?= $_SESSION['flash']['type'] ?> alert-dismissible fade show animate-slideInLeft" role="alert">
            <i class="fas fa-<?= $_SESSION['flash']['type'] == 'success' ? 'check-circle' : 'exclamation-circle' ?> me-2"></i>
            <?= $_SESSION['flash']['message'] ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php unset($_SESSION['flash']); ?>
    <?php endif; ?>
    
    <!-- Liste des filières -->
    <div class="row g-4">
        <?php if ($filieres): ?>
            <?php foreach ($filieres as $index => $f): 
                $delay = $index * 0.1;
                // Valeurs par défaut
                $couleur = !empty($f['couleur']) ? $f['couleur'] : '#667eea';
                $code_filiere = $f['code_filiere'] ?? '';
                $description = $f['description'] ?? '';
                $nb_stagiaires = (int)($f['nb_stagiaires'] ?? 0);
                $nb_actifs = (int)($f['nb_actifs'] ?? 0);
                $nb_termines = (int)($f['nb_termines'] ?? 0);
                $taux_succes = $nb_stagiaires > 0 ? round(($nb_termines / $nb_stagiaires) * 100) : 0;
                $a_des_stagiaires = $nb_stagiaires > 0;
                
                // Icône basée sur le nom de la filière
                $icon = 'fa-graduation-cap';
                $nom_lower = strtolower($f['nom_filiere']);
                if (strpos($nom_lower, 'informatique') !== false) $icon = 'fa-laptop-code';
                elseif (strpos($nom_lower, 'infographie') !== false) $icon = 'fa-palette';
                elseif (strpos($nom_lower, 'gestion') !== false) $icon = 'fa-chart-line';
                elseif (strpos($nom_lower, 'reseau') !== false) $icon = 'fa-network-wired';
                elseif (strpos($nom_lower, 'marketing') !== false) $icon = 'fa-chart-simple';
                elseif (strpos($nom_lower, 'droit') !== false) $icon = 'fa-gavel';
                elseif (strpos($nom_lower, 'communication') !== false) $icon = 'fa-comments';
                elseif (strpos($nom_lower, 'comptabilite') !== false) $icon = 'fa-calculator';
                elseif (strpos($nom_lower, 'architecture') !== false) $icon = 'fa-draw-polygon';
                elseif (strpos($nom_lower, 'medecine') !== false) $icon = 'fa-stethoscope';
            ?>
            <div class="col-md-6 col-lg-4" style="animation-delay: <?= $delay ?>s">
                <div class="filiere-card">
                    <div class="card-gradient" style="background: linear-gradient(135deg, <?= $couleur ?>, <?= $couleur ?>cc);"></div>
                    <div class="card-content">
                        <div class="filiere-icon" style="background: linear-gradient(135deg, <?= $couleur ?>, <?= $couleur ?>cc);">
                            <i class="fas <?= $icon ?>"></i>
                        </div>
                        <h3 class="filiere-title"><?= e($f['nom_filiere']) ?></h3>
                        <?php if ($code_filiere): ?>
                            <div class="filiere-code"><?= e($code_filiere) ?></div>
                        <?php endif; ?>
                        
                        <?php if ($description): ?>
                            <p class="filiere-desc mt-2"><?= e(truncate($description, 80)) ?></p>
                        <?php endif; ?>
                        
                        <!-- Statistiques exactes -->
                        <div class="stat-grid">
                            <div class="stat-item">
                                <div class="stat-number"><?= $nb_stagiaires ?></div>
                                <div class="stat-label">Total</div>
                            </div>
                            <div class="stat-item">
                                <div class="stat-number" style="color: #10b981"><?= $nb_actifs ?></div>
                                <div class="stat-label">Actifs</div>
                            </div>
                            <div class="stat-item">
                                <div class="stat-number" style="color: #3b82f6"><?= $nb_termines ?></div>
                                <div class="stat-label">Terminés</div>
                            </div>
                        </div>
                        
                        <!-- Barre de progression ou badge "Aucun stagiaire" -->
                        <?php if ($a_des_stagiaires): ?>
                            <div class="progress-ring">
                                <div class="progress-bar-custom">
                                    <div class="progress-fill" data-target="<?= $taux_succes ?>" style="background: <?= $couleur ?>"></div>
                                </div>
                                <div class="d-flex justify-content-between mt-2">
                                    <small class="text-muted">Taux de réussite</small>
                                    <small class="fw-bold" style="color: <?= $couleur ?>"><?= $taux_succes ?>%</small>
                                </div>
                            </div>
                        <?php else: ?>
                            <div class="text-center mt-3">
                                <span class="empty-filiere-badge">
                                    <i class="fas fa-info-circle me-1"></i>Aucun stagiaire pour le moment
                                </span>
                            </div>
                        <?php endif; ?>
                        
                        <!-- Boutons d'action : Voir et Supprimer -->
                        <div class="btn-actions">
                            <a href="filiere-voir.php?id=<?= $f['id_filiere'] ?>" class="btn-detail">
                                <i class="fas fa-eye me-2"></i>Voir
                            </a>
                            <a href="?delete=<?= $f['id_filiere'] ?>&csrf_token=<?= generate_csrf_token() ?>" 
                               class="btn-delete" 
                               onclick="return confirm('Êtes-vous sûr de vouloir supprimer cette filière ?\n\n<?= $nb_stagiaires > 0 ? '⚠️ Attention : ' . $nb_stagiaires . ' stagiaire(s) y sont rattachés. La suppression est impossible tant que des stagiaires sont inscrits.' : 'Aucun stagiaire n\'est rattaché à cette filière.' ?>')">
                                <i class="fas fa-trash-alt me-1"></i> Supprimer
                            </a>
                        </div>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="col-12">
                <div class="empty-state">
                    <i class="fas fa-graduation-cap fa-5x text-primary mb-4 opacity-50"></i>
                    <h3>Aucune filière</h3>
                    <p class="text-muted mb-4">Commencez par créer votre première filière.</p>
                    <a href="filiere-ajouter.php" class="btn btn-primary btn-lg rounded-pill px-5">
                        <i class="fas fa-plus me-2"></i>Créer une filière
                    </a>
                </div>
            </div>
        <?php endif; ?>
    </div>
    
    <!-- Résumé global -->
    <?php if ($filieres): ?>
    <div class="row mt-5">
        <div class="col-12">
            <div class="text-center">
                <div class="stat-total-badge" style="background: linear-gradient(135deg, #667eea, #764ba2); border-radius: 50px; padding: 12px 30px; display: inline-block;">
                    <i class="fas fa-chart-pie me-2"></i>
                    Total stagiaires toutes filières : <strong><?= $total_stagiaires ?></strong>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Animation des barres de progression
        const progressBars = document.querySelectorAll('.progress-fill');
        progressBars.forEach(bar => {
            const target = bar.dataset.target;
            setTimeout(() => {
                bar.style.width = target + '%';
            }, 500);
        });
        
        // Animation des cartes
        const cards = document.querySelectorAll('.filiere-card');
        cards.forEach((card, index) => {
            card.style.animationDelay = (index * 0.1) + 's';
            card.style.opacity = '1';
        });
        
        // Animation des nombres (count up)
        const statNumbers = document.querySelectorAll('.stat-number');
        statNumbers.forEach(num => {
            const finalValue = parseInt(num.innerText);
            let currentValue = 0;
            const duration = 1000;
            const step = finalValue / (duration / 30);
            
            const counter = setInterval(() => {
                currentValue += step;
                if (currentValue >= finalValue) {
                    num.innerText = finalValue;
                    clearInterval(counter);
                } else {
                    num.innerText = Math.floor(currentValue);
                }
            }, 30);
        });
    });
</script>

<?php include '../../includes/footer.php'; ?>