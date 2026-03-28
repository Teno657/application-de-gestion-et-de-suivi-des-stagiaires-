<?php
/**
 * Voir les détails d'un stagiaire (Secrétaire)
 */

require_once '../../includes/config.php';
require_once '../../includes/functions.php';
require_once '../../includes/auth.php';

// Vérifier le rôle
require_role('secretaire');

$page_title = "Détails stagiaire - School-Connection";
$id = (int)($_GET['id'] ?? 0);

if (!$id) {
    $_SESSION['flash']['danger'] = "ID stagiaire manquant";
    redirect('stagiaires.php');
}

// Récupérer les informations du stagiaire
$stmt = $pdo->prepare("
    SELECT u.*, s.*,
           (SELECT COUNT(*) FROM taches WHERE id_stagiaire = ?) as nb_taches,
           (SELECT COUNT(*) FROM taches WHERE id_stagiaire = ? AND statut = 'termine') as taches_terminees,
           (SELECT COUNT(*) FROM taches WHERE id_stagiaire = ? AND statut NOT IN ('termine', 'annule')) as taches_en_cours,
           (SELECT COUNT(*) FROM documents WHERE id_stagiaire = ?) as nb_documents,
           (SELECT COUNT(*) FROM rendez_vous WHERE id_stagiaire = ?) as nb_rdv,
           e.id_encadreur, eu.nom as encadreur_nom, eu.prenom as encadreur_prenom,
           eu.email as encadreur_email, eu.photo as encadreur_photo,
           e.profession, e.specialite, e.entreprise
    FROM utilisateurs u
    JOIN stagiaires s ON u.id_utilisateur = s.id_stagiaire
    LEFT JOIN encadreurs e ON s.id_encadreur = e.id_encadreur
    LEFT JOIN utilisateurs eu ON e.id_encadreur = eu.id_utilisateur
    WHERE u.id_utilisateur = ?
");
$stmt->execute([$id, $id, $id, $id, $id, $id]);
$stagiaire = $stmt->fetch();

if (!$stagiaire) {
    $_SESSION['flash']['danger'] = "Stagiaire non trouvé";
    redirect('stagiaires.php');
}

// Récupérer les documents
$documents = $pdo->prepare("
    SELECT * FROM documents 
    WHERE id_stagiaire = ? 
    ORDER BY date_upload DESC
");
$documents->execute([$id]);
$docs = $documents->fetchAll();

// Récupérer les dernières tâches
$taches = $pdo->prepare("
    SELECT t.*, u.nom as encadreur_nom, u.prenom as encadreur_prenom
    FROM taches t
    LEFT JOIN encadreurs e ON t.id_encadreur = e.id_encadreur
    LEFT JOIN utilisateurs u ON e.id_encadreur = u.id_utilisateur
    WHERE t.id_stagiaire = ?
    ORDER BY t.date_creation DESC
    LIMIT 10
");
$taches->execute([$id]);
$recent_taches = $taches->fetchAll();

// Récupérer l'historique des attestations
$attestations = $pdo->prepare("
    SELECT * FROM attestations 
    WHERE id_stagiaire = ? 
    ORDER BY date_emission DESC
");
$attestations->execute([$id]);
$attests = $attestations->fetchAll();

include '../../includes/header.php';
?>

<style>
    .hover-shadow {
        transition: all 0.3s ease;
    }
    .hover-shadow:hover {
        box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        transform: translateY(-2px);
        background-color: #f8f9ff;
    }
    .doc-card {
        transition: all 0.3s ease;
    }
    .doc-card:hover {
        transform: translateY(-3px);
    }
    .badge-stat {
        font-size: 0.8rem;
        padding: 8px 12px;
    }
</style>

<div class="container-fluid">
    <!-- En-tête -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h1 class="h3">Détails du stagiaire</h1>
                    <p class="text-muted">Informations complètes et activités</p>
                </div>
                <div>
                    <a href="stagiaires.php" class="btn btn-outline-secondary">
                        <i class="fas fa-arrow-left"></i> Retour
                    </a>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Profil -->
    <div class="row g-4">
        <div class="col-md-4">
            <div class="card border-0 shadow-sm">
                <div class="card-body text-center">
                    <img src="<?= getPhotoUrl($stagiaire['photo']) ?>" 
                         alt="Photo" class="rounded-circle img-thumbnail mb-3" 
                         style="width: 150px; height: 150px; object-fit: cover;">
                    
                    <h4><?= e($stagiaire['prenom'] . ' ' . $stagiaire['nom']) ?></h4>
                    
                    <div class="text-start mt-3">
                        <p><i class="fas fa-envelope text-primary me-2"></i> <?= e($stagiaire['email']) ?></p>
                        <p><i class="fas fa-phone text-primary me-2"></i> <?= e($stagiaire['telephone'] ?? 'Non renseigné') ?></p>
                        <p><i class="fas fa-map-marker-alt text-primary me-2"></i> <?= e($stagiaire['adresse'] ?? 'Non renseignée') ?></p>
                        <p><i class="fas fa-graduation-cap text-primary me-2"></i> <?= e($stagiaire['filiere']) ?> (<?= e($stagiaire['niveau_etude']) ?>)</p>
                        <p><i class="fas fa-building text-primary me-2"></i> <?= e($stagiaire['etablissement'] ?? 'Non renseigné') ?></p>
                    </div>
                    
                    <div class="mt-3">
                        <span class="badge bg-<?= get_status_badge($stagiaire['statut_inscription']) ?> fs-6">
                            <?= $stagiaire['statut_inscription'] ?>
                        </span>
                    </div>
                </div>
            </div>
            
            <!-- Encadreur -->
            <?php if ($stagiaire['id_encadreur']): ?>
            <div class="card border-0 shadow-sm mt-4">
                <div class="card-header bg-white">
                    <h6 class="card-title mb-0"><i class="fas fa-chalkboard-teacher me-2 text-primary"></i>Encadreur assigné</h6>
                </div>
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <img src="<?= getPhotoUrl($stagiaire['encadreur_photo'] ?? '') ?>" 
                             alt="" class="rounded-circle me-3" width="60" height="60" style="object-fit: cover;">
                        <div>
                            <h6 class="mb-1"><?= e($stagiaire['encadreur_prenom'] . ' ' . $stagiaire['encadreur_nom']) ?></h6>
                            <p class="text-muted small mb-1"><?= e($stagiaire['profession']) ?></p>
                            <p class="text-muted small mb-0"><?= e($stagiaire['entreprise']) ?></p>
                        </div>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </div>
        
        <div class="col-md-8">
            <!-- Statistiques -->
            <div class="row g-4 mb-4">
                <div class="col-md-3">
                    <div class="card border-0 shadow-sm text-center">
                        <div class="card-body">
                            <i class="fas fa-tasks fa-2x text-primary mb-2"></i>
                            <h3 class="mb-0"><?= $stagiaire['nb_taches'] ?></h3>
                            <small class="text-muted">Tâches totales</small>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card border-0 shadow-sm text-center">
                        <div class="card-body">
                            <i class="fas fa-check-circle fa-2x text-success mb-2"></i>
                            <h3 class="mb-0 text-success"><?= $stagiaire['taches_terminees'] ?></h3>
                            <small class="text-muted">Terminées</small>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card border-0 shadow-sm text-center">
                        <div class="card-body">
                            <i class="fas fa-spinner fa-2x text-warning mb-2"></i>
                            <h3 class="mb-0 text-warning"><?= $stagiaire['taches_en_cours'] ?></h3>
                            <small class="text-muted">En cours</small>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card border-0 shadow-sm text-center">
                        <div class="card-body">
                            <i class="fas fa-file-alt fa-2x text-info mb-2"></i>
                            <h3 class="mb-0"><?= $stagiaire['nb_documents'] ?></h3>
                            <small class="text-muted">Documents</small>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Thème du stage -->
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white">
                    <h6 class="card-title mb-0"><i class="fas fa-book-open me-2 text-primary"></i>Thème du stage</h6>
                </div>
                <div class="card-body">
                    <p class="mb-3"><?= nl2br(e($stagiaire['theme_stage'])) ?></p>
                    <hr>
                    <div class="row">
                        <div class="col-md-6">
                            <small class="text-muted">Date de début</small>
                            <p class="fw-bold mb-0"><?= format_date($stagiaire['date_debut'], 'd/m/Y') ?></p>
                        </div>
                        <div class="col-md-6">
                            <small class="text-muted">Date de fin</small>
                            <p class="fw-bold mb-0"><?= format_date($stagiaire['date_fin'], 'd/m/Y') ?></p>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Documents -->
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white d-flex justify-content-between align-items-center">
                    <h6 class="card-title mb-0"><i class="fas fa-file-alt me-2 text-primary"></i>Documents du stagiaire</h6>
                </div>
                <div class="card-body">
                    <?php if ($docs): ?>
                        <div class="row g-3">
                            <?php foreach ($docs as $doc): 
                                $file_ext = pathinfo($doc['nom_fichier'], PATHINFO_EXTENSION);
                                $icon = 'fa-file-alt';
                                $color = '#6c757d';
                                
                                if ($file_ext === 'pdf') {
                                    $icon = 'fa-file-pdf';
                                    $color = '#dc3545';
                                } elseif (in_array($file_ext, ['doc', 'docx'])) {
                                    $icon = 'fa-file-word';
                                    $color = '#0d6efd';
                                } elseif (in_array($file_ext, ['jpg', 'jpeg', 'png'])) {
                                    $icon = 'fa-file-image';
                                    $color = '#198754';
                                }
                            ?>
                            <div class="col-md-12">
                                <div class="d-flex align-items-center p-3 border rounded-3 hover-shadow">
                                    <i class="fas <?= $icon ?> fa-2x me-3" style="color: <?= $color ?>"></i>
                                    <div class="flex-grow-1">
                                        <div class="d-flex justify-content-between align-items-center flex-wrap">
                                            <div>
                                                <div class="fw-bold"><?= e(truncate($doc['nom_fichier'], 40)) ?></div>
                                                <small class="text-muted">
                                                    <i class="fas fa-calendar-alt me-1"></i> <?= format_date($doc['date_upload'], 'd/m/Y') ?>
                                                    <i class="fas fa-database ms-2 me-1"></i> <?= format_filesize($doc['taille']) ?>
                                                </small>
                                            </div>
                                            <div class="mt-2 mt-sm-0">
                                                <a href="../../ajax/download_document.php?id=<?= $doc['id_document'] ?>" 
                                                   class="btn btn-sm btn-outline-primary"
                                                   onclick="trackDownload(<?= $doc['id_document'] ?>)">
                                                    <i class="fas fa-download me-1"></i> Télécharger
                                                </a>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-4">
                            <i class="fas fa-folder-open fa-3x text-muted mb-3"></i>
                            <p class="text-muted mb-0">Aucun document uploadé par ce stagiaire</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Attestations -->
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white d-flex justify-content-between align-items-center">
                    <h6 class="card-title mb-0"><i class="fas fa-certificate me-2 text-primary"></i>Attestations</h6>
                    <a href="attestation-generer.php?stagiaire=<?= $id ?>" class="btn btn-sm btn-success">
                        <i class="fas fa-plus"></i> Générer attestation
                    </a>
                </div>
                <div class="card-body">
                    <?php if ($attests): ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead class="table-light">
                                    <tr>
                                        <th>N° Attestation</th>
                                        <th>Date d'émission</th>
                                        <th>Téléchargements</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($attests as $a): ?>
                                    <tr>
                                        <td><span class="fw-bold"><?= e($a['numero_attestation']) ?></span></td>
                                        <td><?= format_date($a['date_emission']) ?></td>
                                        <td><span class="badge bg-info"><?= $a['nb_telechargements'] ?></span></td>
                                        <td>
                                            <a href="../../ajax/download_attestation.php?id=<?= $a['id_attestation'] ?>" 
                                               class="btn btn-sm btn-outline-primary">
                                                <i class="fas fa-download"></i> Télécharger
                                            </a>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-4">
                            <i class="fas fa-file-pdf fa-3x text-muted mb-3"></i>
                            <p class="text-muted mb-0">Aucune attestation générée pour ce stagiaire</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Dernières tâches -->
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white">
                    <h6 class="card-title mb-0"><i class="fas fa-tasks me-2 text-primary"></i>Dernières tâches assignées</h6>
                </div>
                <div class="card-body p-0">
                    <?php if ($recent_taches): ?>
                        <div class="list-group list-group-flush">
                            <?php foreach ($recent_taches as $tache): ?>
                            <div class="list-group-item">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <strong><?= e($tache['titre']) ?></strong>
                                        <br>
                                        <small class="text-muted">
                                            <i class="fas fa-user me-1"></i>
                                            Par <?= e($tache['encadreur_prenom'] . ' ' . $tache['encadreur_nom']) ?>
                                            <i class="fas fa-calendar-alt ms-2 me-1"></i>
                                            Échéance: <?= format_date($tache['date_echeance'], 'd/m/Y') ?>
                                        </small>
                                    </div>
                                    <span class="badge bg-<?= get_status_badge($tache['statut']) ?>">
                                        <?= $tache['statut'] ?>
                                    </span>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-4">
                            <i class="fas fa-check-circle fa-3x text-muted mb-3"></i>
                            <p class="text-muted mb-0">Aucune tâche assignée</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    function trackDownload(documentId) {
        fetch('../../ajax/track_document_download.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({ id: documentId })
        }).catch(error => console.error('Erreur:', error));
    }
</script>

<?php include '../../includes/footer.php'; ?>