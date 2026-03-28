<?php
/**
 * Gestion des secrétaires (Admin)
 * Version avec design moderne, animations et interface élégante
 */

require_once '../../includes/config.php';
require_once '../../includes/functions.php';
require_once '../../includes/auth.php';

// Vérifier le rôle
require_role('admin');

$page_title = "Gestion des secrétaires - School-Connection";

// Filtres
$search = $_GET['search'] ?? '';
$statut = $_GET['statut'] ?? '';
$service = $_GET['service'] ?? '';

// Pagination
$page = (int)($_GET['page'] ?? 1);
$perPage = 12;
$offset = ($page - 1) * $perPage;

// Construction de la requête
$where = ["u.role = 'secretaire'"];
$params = [];

if (!empty($search)) {
    $where[] = "(u.nom LIKE ? OR u.prenom LIKE ? OR u.email LIKE ? OR s.service LIKE ? OR s.matricule LIKE ?)";
    $searchTerm = "%$search%";
    for ($i = 0; $i < 5; $i++) $params[] = $searchTerm;
}

if ($statut === 'actif') {
    $where[] = "u.est_actif = 1";
} elseif ($statut === 'inactif') {
    $where[] = "u.est_actif = 0";
}

if (!empty($service)) {
    $where[] = "s.service = ?";
    $params[] = $service;
}

// Comptage total
$countSql = "SELECT COUNT(*) FROM secretaires s JOIN utilisateurs u ON s.id_secretaire = u.id_utilisateur WHERE " . implode(' AND ', $where);
$stmt = $pdo->prepare($countSql);
$stmt->execute($params);
$total = $stmt->fetchColumn();
$totalPages = ceil($total / $perPage);

// Récupération des secrétaires
$sql = "
    SELECT u.*, s.service, s.matricule, s.date_embauche, s.niveau_acces, s.permissions,
           (SELECT COUNT(*) FROM logs_activite WHERE id_utilisateur = u.id_utilisateur AND date_action >= DATE_SUB(NOW(), INTERVAL 7 DAY)) as activites_recentes,
           (SELECT COUNT(*) FROM logs_activite WHERE id_utilisateur = u.id_utilisateur AND date_action >= DATE_SUB(NOW(), INTERVAL 30 DAY)) as activites_mensuelles
    FROM secretaires s
    JOIN utilisateurs u ON s.id_secretaire = u.id_utilisateur
    WHERE " . implode(' AND ', $where) . "
    ORDER BY u.date_creation DESC
    LIMIT ? OFFSET ?
";

$params[] = $perPage;
$params[] = $offset;

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$secretaires = $stmt->fetchAll();

// Récupérer la liste des services uniques pour le filtre
$services = $pdo->query("SELECT DISTINCT service FROM secretaires WHERE service IS NOT NULL AND service != ''")->fetchAll();

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
            transform: translateX(-30px);
        }
        to {
            opacity: 1;
            transform: translateX(0);
        }
    }
    
    @keyframes pulse {
        0%, 100% { transform: scale(1); }
        50% { transform: scale(1.05); }
    }
    
    .animate-fadeInUp {
        animation: fadeInUp 0.6s ease-out forwards;
        opacity: 0;
    }
    
    .animate-scaleIn {
        animation: scaleIn 0.5s ease-out forwards;
    }
    
    .animate-slideInLeft {
        animation: slideInLeft 0.6s ease-out forwards;
        opacity: 0;
    }
    
    .hero-secretaires {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        border-radius: 30px;
        padding: 50px;
        margin-bottom: 40px;
        position: relative;
        overflow: hidden;
    }
    
    .hero-secretaires::before {
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
    
    .filter-card {
        background: white;
        border-radius: 20px;
        padding: 25px;
        margin-bottom: 30px;
        box-shadow: 0 5px 20px rgba(0,0,0,0.08);
    }
    
    .secretaire-card {
        background: white;
        border-radius: 24px;
        overflow: hidden;
        transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
        box-shadow: 0 10px 30px rgba(0,0,0,0.08);
        height: 100%;
        position: relative;
        animation: fadeInUp 0.6s ease-out forwards;
        opacity: 0;
    }
    
    .secretaire-card:hover {
        transform: translateY(-10px);
        box-shadow: 0 25px 50px rgba(0,0,0,0.15);
    }
    
    .card-header-secretaire {
        background: linear-gradient(135deg, #667eea, #764ba2);
        padding: 20px;
        position: relative;
        color: white;
    }
    
    .card-header-secretaire::after {
        content: '';
        position: absolute;
        bottom: -15px;
        left: 20px;
        width: 30px;
        height: 30px;
        background: white;
        transform: rotate(45deg);
    }
    
    .secretaire-avatar {
        width: 80px;
        height: 80px;
        border-radius: 50%;
        object-fit: cover;
        border: 4px solid white;
        margin-top: -40px;
        margin-bottom: 15px;
        box-shadow: 0 5px 15px rgba(0,0,0,0.2);
        transition: transform 0.3s ease;
    }
    
    .secretaire-card:hover .secretaire-avatar {
        transform: scale(1.05);
    }
    
    .stat-badge {
        display: inline-flex;
        align-items: center;
        gap: 5px;
        padding: 5px 12px;
        border-radius: 20px;
        font-size: 0.7rem;
        font-weight: 600;
    }
    
    .badge-superieur {
        background: linear-gradient(135deg, #f59e0b, #d97706);
        color: white;
    }
    
    .badge-standard {
        background: #e5e7eb;
        color: #4b5563;
    }
    
    .activity-bar {
        height: 4px;
        background: #e5e7eb;
        border-radius: 10px;
        overflow: hidden;
        margin: 10px 0;
    }
    
    .activity-fill {
        height: 100%;
        border-radius: 10px;
        background: linear-gradient(90deg, #10b981, #34d399);
        width: 0%;
        transition: width 1s ease;
    }
    
    .btn-icon {
        width: 35px;
        height: 35px;
        border-radius: 12px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        transition: all 0.3s ease;
        background: transparent;
        border: none;
    }
    
    .btn-icon:hover {
        transform: translateY(-2px);
    }
    
    .btn-view { color: #3b82f6; }
    .btn-view:hover { background: rgba(59,130,246,0.1); }
    .btn-edit { color: #f59e0b; }
    .btn-edit:hover { background: rgba(245,158,11,0.1); }
    .btn-delete { color: #ef4444; }
    .btn-delete:hover { background: rgba(239,68,68,0.1); }
    
    .empty-state {
        text-align: center;
        padding: 80px 20px;
        background: linear-gradient(135deg, #f8f9ff, #f0f2ff);
        border-radius: 30px;
    }
    
    @media (max-width: 768px) {
        .hero-secretaires { padding: 30px; }
        .secretaire-card { margin-bottom: 20px; }
    }
</style>

<div class="container-fluid">
    <!-- Hero Section -->
    <div class="hero-secretaires animate-scaleIn">
        <div class="row align-items-center">
            <div class="col-md-8">
                <h1 class="display-4 fw-bold text-white mb-3">
                    <i class="fas fa-user-tie me-3"></i>
                    Gestion des secrétaires
                </h1>
                <p class="lead text-white-50 mb-0">
                    Gérez les secrétaires de la plateforme et leurs accès
                </p>
            </div>
            <div class="col-md-4 text-md-end mt-3 mt-md-0">
                <a href="secretaire-ajouter.php" class="btn btn-light btn-lg rounded-pill px-4 shadow-sm">
                    <i class="fas fa-plus me-2"></i>
                    Nouvelle secrétaire
                </a>
            </div>
        </div>
    </div>
    
    <!-- Filtres -->
    <div class="filter-card animate-slideInLeft">
        <form method="GET" class="row g-3">
            <div class="col-md-4">
                <label class="form-label fw-semibold">
                    <i class="fas fa-search text-primary me-1"></i>Rechercher
                </label>
                <input type="text" class="form-control rounded-pill" name="search" 
                       value="<?= e($search) ?>" placeholder="Nom, email, service, matricule...">
            </div>
            <div class="col-md-3">
                <label class="form-label fw-semibold">
                    <i class="fas fa-filter text-primary me-1"></i>Statut
                </label>
                <select class="form-select rounded-pill" name="statut">
                    <option value="">Tous</option>
                    <option value="actif" <?= $statut === 'actif' ? 'selected' : '' ?>>Actifs</option>
                    <option value="inactif" <?= $statut === 'inactif' ? 'selected' : '' ?>>Inactifs</option>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label fw-semibold">
                    <i class="fas fa-building text-primary me-1"></i>Service
                </label>
                <select class="form-select rounded-pill" name="service">
                    <option value="">Tous les services</option>
                    <?php foreach ($services as $s): ?>
                        <option value="<?= e($s['service']) ?>" <?= $service == $s['service'] ? 'selected' : '' ?>>
                            <?= e($s['service']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2 d-flex align-items-end">
                <button type="submit" class="btn btn-primary w-100 rounded-pill">
                    <i class="fas fa-search me-2"></i>Filtrer
                </button>
            </div>
        </form>
    </div>
    
    <!-- Statistiques rapides -->
    <div class="row g-4 mb-4">
        <?php
        $total_actifs = count(array_filter($secretaires, function($s) { return $s['est_actif']; }));
        $total_inactifs = count($secretaires) - $total_actifs;
        $total_activites = array_sum(array_column($secretaires, 'activites_recentes'));
        ?>
        <div class="col-md-3">
            <div class="card border-0 shadow-sm text-center p-3">
                <i class="fas fa-users fa-2x text-primary mb-2"></i>
                <h3 class="mb-0"><?= count($secretaires) ?></h3>
                <small class="text-muted">Secrétaires</small>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 shadow-sm text-center p-3">
                <i class="fas fa-check-circle fa-2x text-success mb-2"></i>
                <h3 class="mb-0"><?= $total_actifs ?></h3>
                <small class="text-muted">Actifs</small>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 shadow-sm text-center p-3">
                <i class="fas fa-chart-line fa-2x text-info mb-2"></i>
                <h3 class="mb-0"><?= $total_activites ?></h3>
                <small class="text-muted">Actions (7j)</small>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 shadow-sm text-center p-3">
                <i class="fas fa-building fa-2x text-warning mb-2"></i>
                <h3 class="mb-0"><?= count($services) ?></h3>
                <small class="text-muted">Services</small>
            </div>
        </div>
    </div>
    
    <!-- Liste des secrétaires -->
    <div class="row g-4">
        <?php if ($secretaires): ?>
            <?php foreach ($secretaires as $index => $s): 
                $delay = $index * 0.05;
                $activite_pourcentage = min(100, round(($s['activites_recentes'] / 100) * 100));
                $niveau_acces = $s['niveau_acces'] ?? 'standard';
            ?>
            <div class="col-md-6 col-lg-4" style="animation-delay: <?= $delay ?>s">
                <div class="secretaire-card">
                    <div class="card-header-secretaire">
                        <div class="d-flex justify-content-between align-items-center">
                            <small class="opacity-75">
                                <i class="fas fa-calendar-alt me-1"></i>
                                <?= format_date($s['date_embauche'] ?? $s['date_creation'], 'd/m/Y') ?>
                            </small>
                            <?php if ($s['est_actif']): ?>
                                <span class="badge bg-success rounded-pill">
                                    <i class="fas fa-circle me-1" style="font-size: 8px;"></i>Actif
                                </span>
                            <?php else: ?>
                                <span class="badge bg-secondary rounded-pill">
                                    <i class="fas fa-circle me-1" style="font-size: 8px;"></i>Inactif
                                </span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="text-center px-4">
                        <img src="<?= getPhotoUrl($s['photo'] ?? '') ?>" 
                             alt="Photo" class="secretaire-avatar">
                        <h5 class="mb-1 fw-bold"><?= e($s['prenom'] . ' ' . $s['nom']) ?></h5>
                        <p class="text-muted small mb-2">
                            <i class="fas fa-envelope me-1"></i> <?= e($s['email']) ?>
                        </p>
                        <?php if ($s['telephone']): ?>
                            <p class="text-muted small mb-2">
                                <i class="fas fa-phone me-1"></i> <?= e($s['telephone']) ?>
                            </p>
                        <?php endif; ?>
                        <div class="mb-2">
                            <span class="stat-badge <?= $niveau_acces === 'superieur' ? 'badge-superieur' : 'badge-standard' ?>">
                                <i class="fas fa-<?= $niveau_acces === 'superieur' ? 'crown' : 'user-check' ?> me-1"></i>
                                <?= $niveau_acces === 'superieur' ? 'Accès supérieur' : 'Accès standard' ?>
                            </span>
                        </div>
                    </div>
                    <div class="p-4 pt-0">
                        <div class="d-flex justify-content-between mb-2">
                            <span class="text-muted small">
                                <i class="fas fa-building me-1"></i>Service
                            </span>
                            <span class="fw-semibold"><?= e($s['service'] ?? '—') ?></span>
                        </div>
                        <div class="d-flex justify-content-between mb-2">
                            <span class="text-muted small">
                                <i class="fas fa-id-card me-1"></i>Matricule
                            </span>
                            <span class="fw-semibold"><?= e($s['matricule'] ?? '—') ?></span>
                        </div>
                        <div class="d-flex justify-content-between mb-2">
                            <span class="text-muted small">
                                <i class="fas fa-chart-line me-1"></i>Activité (7j)
                            </span>
                            <span class="fw-semibold text-primary"><?= $s['activites_recentes'] ?? 0 ?> actions</span>
                        </div>
                        <div class="activity-bar">
                            <div class="activity-fill" style="width: <?= $activite_pourcentage ?>%"></div>
                        </div>
                        <div class="d-flex justify-content-center gap-2 mt-3">
                            <a href="secretaire-voir.php?id=<?= $s['id_utilisateur'] ?>" 
                               class="btn-icon btn-view" title="Voir">
                                <i class="fas fa-eye fa-lg"></i>
                            </a>
                            <a href="secretaire-modifier.php?id=<?= $s['id_utilisateur'] ?>" 
                               class="btn-icon btn-edit" title="Modifier">
                                <i class="fas fa-edit fa-lg"></i>
                            </a>
                            <?php if ($s['id_utilisateur'] != $_SESSION['user_id']): ?>
                               <a href="secretaire-supprimer.php?id=<?= $s['id_utilisateur'] ?>&csrf_token=<?= generate_csrf_token() ?>" 
   class="btn-icon btn-delete" 
   onclick="return confirm('Êtes-vous sûr de vouloir supprimer <?= e(addslashes($s['prenom'] . ' ' . $s['nom'])) ?> ?')"
   title="Supprimer">
    <i class="fas fa-trash-alt fa-lg"></i>
</a>
                            <?php else: ?>
                                <button type="button" 
                                        class="btn-icon" 
                                        style="color: #9ca3af; cursor: not-allowed;" 
                                        disabled
                                        title="Vous ne pouvez pas supprimer votre propre compte">
                                    <i class="fas fa-trash-alt fa-lg"></i>
                                </button>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="col-12">
                <div class="empty-state">
                    <i class="fas fa-user-tie fa-5x text-primary mb-4 opacity-50"></i>
                    <h3>Aucun secrétaire trouvé</h3>
                    <p class="text-muted mb-4">
                        <?= !empty($search) ? 'Aucun résultat pour "' . e($search) . '"' : 'Commencez par ajouter votre premier secrétaire.' ?>
                    </p>
                    <?php if (!empty($search)): ?>
                        <a href="secretaires.php" class="btn btn-outline-primary rounded-pill px-4">
                            <i class="fas fa-times me-2"></i>Effacer les filtres
                        </a>
                    <?php else: ?>
                        <a href="secretaire-ajouter.php" class="btn btn-primary btn-lg rounded-pill px-5">
                            <i class="fas fa-plus me-2"></i>Ajouter un secrétaire
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>
    
    <!-- Pagination -->
    <?php if ($totalPages > 1): ?>
    <div class="row mt-5">
        <div class="col-12">
            <nav>
                <ul class="pagination justify-content-center">
                    <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                        <a class="page-link rounded-pill" href="?page=<?= $page - 1 ?>&search=<?= urlencode($search) ?>&statut=<?= urlencode($statut) ?>&service=<?= urlencode($service) ?>">
                            <i class="fas fa-chevron-left me-1"></i> Précédent
                        </a>
                    </li>
                    
                    <?php 
                    $start = max(1, $page - 2);
                    $end = min($totalPages, $page + 2);
                    for ($i = $start; $i <= $end; $i++): 
                    ?>
                        <li class="page-item <?= $i === $page ? 'active' : '' ?>">
                            <a class="page-link rounded-pill" href="?page=<?= $i ?>&search=<?= urlencode($search) ?>&statut=<?= urlencode($statut) ?>&service=<?= urlencode($service) ?>">
                                <?= $i ?>
                            </a>
                        </li>
                    <?php endfor; ?>
                    
                    <li class="page-item <?= $page >= $totalPages ? 'disabled' : '' ?>">
                        <a class="page-link rounded-pill" href="?page=<?= $page + 1 ?>&search=<?= urlencode($search) ?>&statut=<?= urlencode($statut) ?>&service=<?= urlencode($service) ?>">
                            Suivant <i class="fas fa-chevron-right ms-1"></i>
                        </a>
                    </li>
                </ul>
            </nav>
        </div>
    </div>
    <?php endif; ?>
</div>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
    function deleteSecretaire(id, name) {
        Swal.fire({
            title: 'Confirmer la suppression',
            html: `Êtes-vous sûr de vouloir supprimer <strong>${name}</strong> ?<br><br>
                   <span class="text-danger"><i class="fas fa-exclamation-triangle me-1"></i>Cette action est irréversible.</span>`,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#ef4444',
            cancelButtonColor: '#6c757d',
            confirmButtonText: '<i class="fas fa-trash-alt me-2"></i>Oui, supprimer',
            cancelButtonText: '<i class="fas fa-times me-2"></i>Annuler'
        }).then((result) => {
            if (result.isConfirmed) {
                window.location.href = 'secretaire-supprimer.php?id=' + id;
            }
        });
    }
    
    // Animation des barres d'activité
    document.addEventListener('DOMContentLoaded', function() {
        const activityBars = document.querySelectorAll('.activity-fill');
        activityBars.forEach(bar => {
            const width = bar.style.width;
            bar.style.width = '0%';
            setTimeout(() => {
                bar.style.width = width;
            }, 300);
        });
        
        // Animation des cartes
        const cards = document.querySelectorAll('.secretaire-card');
        cards.forEach((card, index) => {
            card.style.animationDelay = (index * 0.05) + 's';
            card.style.opacity = '1';
        });
    });
</script>

<?php include '../../includes/footer.php'; ?>