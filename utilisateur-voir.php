<?php
/**
 * Voir les détails d'un utilisateur (Admin)
 */

require_once '../../includes/config.php';
require_once '../../includes/functions.php';
require_once '../../includes/auth.php';

// Vérifier le rôle
require_role('admin');

$page_title = "Détails utilisateur - School-Connection";
$id = (int)($_GET['id'] ?? 0);

if (!$id) {
    $_SESSION['flash']['danger'] = "ID utilisateur manquant";
    redirect('utilisateurs.php');
}

// Récupérer les informations de l'utilisateur
$stmt = $pdo->prepare("
    SELECT u.*, 
           s.filiere, s.niveau_etude, s.etablissement, s.theme_stage, s.date_debut, s.date_fin, s.statut_inscription,
           e.profession, e.specialite, e.entreprise, e.bio, e.disponible, e.stagiaires_actuels, e.max_stagiaires,
           sec.service, sec.matricule
    FROM utilisateurs u
    LEFT JOIN stagiaires s ON u.id_utilisateur = s.id_stagiaire AND u.role = 'stagiaire'
    LEFT JOIN encadreurs e ON u.id_utilisateur = e.id_encadreur AND u.role LIKE 'encadreur_%'
    LEFT JOIN secretaires sec ON u.id_utilisateur = sec.id_secretaire AND u.role = 'secretaire'
    WHERE u.id_utilisateur = ?
");
$stmt->execute([$id]);
$user = $stmt->fetch();

if (!$user) {
    $_SESSION['flash']['danger'] = "Utilisateur non trouvé";
    redirect('utilisateurs.php');
}

// Statistiques supplémentaires
$stats = [];

if ($user['role'] === 'stagiaire') {
    // Tâches du stagiaire
    $stats['taches'] = $pdo->prepare("
        SELECT COUNT(*) as total,
               SUM(CASE WHEN statut = 'termine' THEN 1 ELSE 0 END) as terminees,
               SUM(CASE WHEN statut NOT IN ('termine', 'annule') THEN 1 ELSE 0 END) as en_cours
        FROM taches 
        WHERE id_stagiaire = ?
    ")->execute([$id])->fetch();
    
    // Documents du stagiaire
    $stats['documents'] = $pdo->prepare("
        SELECT COUNT(*) FROM documents WHERE id_stagiaire = ?
    ")->execute([$id])->fetchColumn();
    
    // Encadreur assigné
    $stats['encadreur'] = $pdo->prepare("
        SELECT u.nom, u.prenom, u.email, e.profession
        FROM relations_encadrement r
        JOIN encadreurs e ON r.id_encadreur = e.id_encadreur
        JOIN utilisateurs u ON e.id_encadreur = u.id_utilisateur
        WHERE r.id_stagiaire = ? AND r.statut = 'active'
    ")->execute([$id])->fetch();
    
} elseif (strpos($user['role'], 'encadreur') !== false) {
    // Stagiaires de l'encadreur
    $stats['stagiaires'] = $pdo->prepare("
        SELECT COUNT(*) FROM relations_encadrement 
        WHERE id_encadreur = ? AND statut = 'active'
    ")->execute([$id])->fetchColumn();
    
    // Tâches créées
    $stats['taches'] = $pdo->prepare("
        SELECT COUNT(*) FROM taches WHERE id_encadreur = ?
    ")->execute([$id])->fetchColumn();
    
    // Liste des stagiaires
    $stats['liste_stagiaires'] = $pdo->prepare("
        SELECT u.id_utilisateur, u.nom, u.prenom, s.theme_stage
        FROM relations_encadrement r
        JOIN stagiaires s ON r.id_stagiaire = s.id_stagiaire
        JOIN utilisateurs u ON s.id_stagiaire = u.id_utilisateur
        WHERE r.id_encadreur = ? AND r.statut = 'active'
    ")->execute([$id])->fetchAll();
}

// Dernières connexions
$logs = $pdo->prepare("
    SELECT * FROM logs_activite 
    WHERE id_utilisateur = ? 
    ORDER BY date_action DESC 
    LIMIT 10
");
$logs->execute([$id]);
$recent_logs = $logs->fetchAll();

include '../../includes/header.php';
?>

<div class="container-fluid">
    <!-- En-tête -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h1 class="h3">Détails de l'utilisateur</h1>
                    <p class="text-muted">Informations complètes et activités</p>
                </div>
                <div>
                    <a href="utilisateurs.php" class="btn btn-outline-secondary me-2">
                        <i class="fas fa-arrow-left"></i> Retour
                    </a>
                    <a href="utilisateur-modifier.php?id=<?= $id ?>" class="btn btn-warning">
                        <i class="fas fa-edit"></i> Modifier
                    </a>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Profil utilisateur -->
    <div class="row g-4">
        <div class="col-md-4">
            <div class="card border-0 shadow-sm">
                <div class="card-body text-center">
                    <img src="<?= getPhotoUrl($user['photo']) ?>" 
                         alt="Photo de profil" 
                         class="rounded-circle img-thumbnail mb-3" 
                         style="width: 150px; height: 150px; object-fit: cover;">
                    
                    <h4><?= e($user['prenom'] . ' ' . $user['nom']) ?></h4>
                    <p class="text-muted mb-2">
                        <span class="badge bg-<?= $user['role'] === 'admin' ? 'danger' : 
                            ($user['role'] === 'secretaire' ? 'warning' : 
                            (strpos($user['role'], 'encadreur') !== false ? 'info' : 'primary')) ?> fs-6">
                            <?= get_role_label($user['role']) ?>
                        </span>
                    </p>
                    
                    <div class="text-start mt-3">
                        <p><i class="fas fa-envelope text-primary me-2"></i> <?= e($user['email']) ?></p>
                        <p><i class="fas fa-phone text-primary me-2"></i> <?= e($user['telephone'] ?? 'Non renseigné') ?></p>
                        <p><i class="fas fa-map-marker-alt text-primary me-2"></i> <?= e($user['adresse'] ?? 'Non renseignée') ?></p>
                        <p><i class="fas fa-calendar text-primary me-2"></i> Inscrit le <?= format_date($user['date_creation'], 'd/m/Y') ?></p>
                    </div>
                    
                    <div class="mt-3">
                        <?php if ($user['est_actif']): ?>
                            <span class="badge bg-success">Compte actif</span>
                        <?php else: ?>
                            <span class="badge bg-secondary">Compte inactif</span>
                        <?php endif; ?>
                        
                        <?php if ($user['est_bloque']): ?>
                            <span class="badge bg-danger">Bloqué</span>
                            <p class="text-danger small mt-2">Raison : <?= e($user['raison_blocage']) ?></p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-md-8">
            <!-- Informations spécifiques selon le rôle -->
            <?php if ($user['role'] === 'stagiaire'): ?>
                <div class="card border-0 shadow-sm mb-4">
                    <div class="card-header bg-white">
                        <h6 class="card-title mb-0">Informations de stage</h6>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <p><strong>Filière :</strong> <?= e($user['filiere'] ?? '—') ?></p>
                                <p><strong>Niveau :</strong> <?= e($user['niveau_etude'] ?? '—') ?></p>
                                <p><strong>Établissement :</strong> <?= e($user['etablissement'] ?? '—') ?></p>
                            </div>
                            <div class="col-md-6">
                                <p><strong>Date début :</strong> <?= format_date($user['date_debut']) ?></p>
                                <p><strong>Date fin :</strong> <?= format_date($user['date_fin']) ?></p>
                                <p><strong>Statut :</strong> 
                                    <span class="badge bg-<?= get_status_badge($user['statut_inscription']) ?>">
                                        <?= $user['statut_inscription'] ?>
                                    </span>
                                </p>
                            </div>
                            <div class="col-12">
                                <p><strong>Thème du stage :</strong></p>
                                <p class="text-muted"><?= nl2br(e($user['theme_stage'] ?? '')) ?></p>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="row g-4">
                    <div class="col-md-6">
                        <div class="card border-0 shadow-sm">
                            <div class="card-header bg-white">
                                <h6 class="card-title mb-0">Tâches</h6>
                            </div>
                            <div class="card-body">
                                <div class="d-flex justify-content-between mb-3">
                                    <div>
                                        <h3 class="mb-0"><?= $stats['taches']['total'] ?? 0 ?></h3>
                                        <small class="text-muted">Total</small>
                                    </div>
                                    <div>
                                        <h3 class="mb-0 text-success"><?= $stats['taches']['terminees'] ?? 0 ?></h3>
                                        <small class="text-muted">Terminées</small>
                                    </div>
                                    <div>
                                        <h3 class="mb-0 text-warning"><?= $stats['taches']['en_cours'] ?? 0 ?></h3>
                                        <small class="text-muted">En cours</small>
                                    </div>
                                </div>
                                <a href="../stagiaire/taches.php?user=<?= $id ?>" class="btn btn-sm btn-outline-primary w-100">
                                    Voir les tâches
                                </a>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-6">
                        <div class="card border-0 shadow-sm">
                            <div class="card-header bg-white">
                                <h6 class="card-title mb-0">Documents</h6>
                            </div>
                            <div class="card-body">
                                <div class="text-center mb-3">
                                    <h3 class="mb-0"><?= $stats['documents'] ?? 0 ?></h3>
                                    <small class="text-muted">Documents uploadés</small>
                                </div>
                                <a href="../stagiaire/documents.php?user=<?= $id ?>" class="btn btn-sm btn-outline-primary w-100">
                                    Voir les documents
                                </a>
                            </div>
                        </div>
                    </div>
                    
                    <?php if ($stats['encadreur']): ?>
                    <div class="col-12">
                        <div class="card border-0 shadow-sm">
                            <div class="card-header bg-white">
                                <h6 class="card-title mb-0">Encadreur assigné</h6>
                            </div>
                            <div class="card-body">
                                <div class="d-flex align-items-center">
                                    <div>
                                        <strong><?= e($stats['encadreur']['prenom'] . ' ' . $stats['encadreur']['nom']) ?></strong>
                                        <br>
                                        <small class="text-muted"><?= e($stats['encadreur']['profession']) ?></small>
                                        <br>
                                        <small><?= e($stats['encadreur']['email']) ?></small>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
                
            <?php elseif (strpos($user['role'], 'encadreur') !== false): ?>
                <div class="card border-0 shadow-sm mb-4">
                    <div class="card-header bg-white">
                        <h6 class="card-title mb-0">Informations professionnelles</h6>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <p><strong>Profession :</strong> <?= e($user['profession'] ?? '—') ?></p>
                                <p><strong>Spécialité :</strong> <?= e($user['specialite'] ?? '—') ?></p>
                                <p><strong>Entreprise :</strong> <?= e($user['entreprise'] ?? '—') ?></p>
                            </div>
                            <div class="col-md-6">
                                <p><strong>Disponible :</strong> 
                                    <?php if ($user['disponible']): ?>
                                        <span class="badge bg-success">Oui</span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary">Non</span>
                                    <?php endif; ?>
                                </p>
                                <p><strong>Stagiaires :</strong> <?= $user['stagiaires_actuels'] ?> / <?= $user['max_stagiaires'] ?></p>
                            </div>
                            <div class="col-12">
                                <p><strong>Biographie :</strong></p>
                                <p class="text-muted"><?= nl2br(e($user['bio'] ?? '')) ?></p>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="row g-4">
                    <div class="col-md-6">
                        <div class="card border-0 shadow-sm">
                            <div class="card-header bg-white">
                                <h6 class="card-title mb-0">Statistiques</h6>
                            </div>
                            <div class="card-body">
                                <div class="d-flex justify-content-between mb-3">
                                    <div>
                                        <h3 class="mb-0"><?= $stats['stagiaires'] ?? 0 ?></h3>
                                        <small class="text-muted">Stagiaires actifs</small>
                                    </div>
                                    <div>
                                        <h3 class="mb-0"><?= $stats['taches'] ?? 0 ?></h3>
                                        <small class="text-muted">Tâches créées</small>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <?php if (!empty($stats['liste_stagiaires'])): ?>
                    <div class="col-md-6">
                        <div class="card border-0 shadow-sm">
                            <div class="card-header bg-white">
                                <h6 class="card-title mb-0">Stagiaires encadrés</h6>
                            </div>
                            <div class="card-body p-0">
                                <ul class="list-group list-group-flush">
                                    <?php foreach ($stats['liste_stagiaires'] as $s): ?>
                                    <li class="list-group-item">
                                        <a href="stagiaire-voir.php?id=<?= $s['id_utilisateur'] ?>" class="text-decoration-none">
                                            <?= e($s['prenom'] . ' ' . $s['nom']) ?>
                                            <small class="d-block text-muted"><?= e($s['theme_stage']) ?></small>
                                        </a>
                                    </li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
                
            <?php elseif ($user['role'] === 'secretaire'): ?>
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-white">
                        <h6 class="card-title mb-0">Informations secrétariat</h6>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <p><strong>Service :</strong> <?= e($user['service'] ?? '—') ?></p>
                                <p><strong>Matricule :</strong> <?= e($user['matricule'] ?? '—') ?></p>
                            </div>
                        </div>
                    </div>
                </div>
                
            <?php elseif ($user['role'] === 'admin'): ?>
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-white">
                        <h6 class="card-title mb-0">Informations administrateur</h6>
                    </div>
                    <div class="card-body">
                        <p><strong>Niveau d'accès :</strong> <?= e($user['niveau_acces'] ?? 'admin') ?></p>
                    </div>
                </div>
            <?php endif; ?>
            
            <!-- Dernières activités -->
            <div class="card border-0 shadow-sm mt-4">
                <div class="card-header bg-white">
                    <h6 class="card-title mb-0">Dernières activités</h6>
                </div>
                <div class="card-body p-0">
                    <?php if ($recent_logs): ?>
                        <ul class="list-group list-group-flush">
                            <?php foreach ($recent_logs as $log): ?>
                            <li class="list-group-item">
                                <div class="d-flex justify-content-between">
                                    <div>
                                        <strong><?= e($log['action']) ?></strong>
                                        <br>
                                        <small class="text-muted"><?= e($log['description']) ?></small>
                                    </div>
                                    <small class="text-muted">
                                        <?= format_datetime($log['date_action'], 'd/m/Y H:i') ?>
                                    </small>
                                </div>
                            </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php else: ?>
                        <p class="text-muted text-center py-3">Aucune activité récente</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>