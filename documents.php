<?php
/**
 * Gestion des documents (Stagiaire)
 * Version avec validation par l'encadreur et notifications
 */

require_once '../../includes/config.php';
require_once '../../includes/functions.php';
require_once '../../includes/auth.php';

// Vérifier le rôle
require_role('stagiaire');

$page_title = "Mes documents - School-Connection";
$user_id = $_SESSION['user_id'];

// Récupérer les documents du stagiaire avec leur statut de validation
$documents = $pdo->prepare("
    SELECT d.*, 
           u.nom as encadreur_nom,
           u.prenom as encadreur_prenom
    FROM documents d
    LEFT JOIN utilisateurs u ON d.valide_par = u.id_utilisateur
    WHERE d.id_utilisateur = ? 
    ORDER BY 
        CASE WHEN d.est_valide = 0 THEN 1 ELSE 2 END,
        d.date_upload DESC
");
$documents->execute([$user_id]);
$docs = $documents->fetchAll();

include '../../includes/header.php';
?>

<style>
    :root {
        --primary: #667eea;
        --primary-dark: #5a67d8;
        --secondary: #764ba2;
        --success: #10b981;
        --warning: #f59e0b;
        --danger: #ef4444;
        --info: #3b82f6;
    }
    
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
        animation: fadeInUp 0.6s ease-out;
    }
    
    /* Hero Section */
    .hero-docs {
        background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
        border-radius: 25px;
        padding: 40px;
        margin-bottom: 30px;
        position: relative;
        overflow: hidden;
    }
    
    .hero-docs::before {
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
    
    /* Grille des documents */
    .docs-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
        gap: 25px;
        margin-top: 20px;
    }
    
    .doc-card {
        background: white;
        border-radius: 20px;
        overflow: hidden;
        transition: all 0.3s ease;
        border: 1px solid rgba(0,0,0,0.05);
        box-shadow: 0 2px 10px rgba(0,0,0,0.03);
        position: relative;
    }
    
    .doc-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 15px 35px rgba(0,0,0,0.1);
    }
    
    .doc-card-validated {
        border-left: 4px solid var(--success);
    }
    
    .doc-card-pending {
        border-left: 4px solid var(--warning);
    }
    
    .doc-icon {
        background: linear-gradient(135deg, #f8f9ff, #f0f2ff);
        padding: 30px;
        text-align: center;
        border-bottom: 1px solid #f0f0f0;
    }
    
    .doc-icon i {
        font-size: 3.5rem;
    }
    
    .doc-content {
        padding: 20px;
    }
    
    .doc-title {
        font-size: 1rem;
        font-weight: 600;
        margin-bottom: 10px;
        color: var(--dark);
        word-break: break-all;
    }
    
    .doc-meta {
        display: flex;
        justify-content: space-between;
        margin-bottom: 15px;
        font-size: 0.75rem;
        color: #6b7280;
    }
    
    .badge-status {
        padding: 5px 12px;
        border-radius: 20px;
        font-size: 0.7rem;
        font-weight: 600;
        display: inline-flex;
        align-items: center;
        gap: 5px;
    }
    
    .badge-validated {
        background: #d1fae5;
        color: #065f46;
    }
    
    .badge-pending {
        background: #fef3c7;
        color: #92400e;
    }
    
    .validation-info {
        border-radius: 12px;
        padding: 12px;
        margin-top: 15px;
    }
    
    .validation-validated {
        background: #d1fae5;
    }
    
    .validation-pending {
        background: #fef3c7;
    }
    
    .btn-download {
        width: 100%;
        padding: 10px;
        border-radius: 12px;
        font-weight: 500;
        font-size: 0.85rem;
        transition: all 0.3s ease;
        background: linear-gradient(135deg, var(--primary), var(--secondary));
        color: white;
        border: none;
        cursor: pointer;
        text-align: center;
        text-decoration: none;
        display: inline-block;
    }
    
    .btn-download:hover {
        transform: translateY(-2px);
        box-shadow: 0 5px 15px rgba(102,126,234,0.4);
    }
    
    .empty-state {
        text-align: center;
        padding: 60px 20px;
        background: linear-gradient(135deg, #f8f9ff, #f0f2ff);
        border-radius: 25px;
        margin-top: 20px;
    }
    
    .type-badge {
        padding: 4px 10px;
        border-radius: 12px;
        font-size: 0.7rem;
        font-weight: 500;
    }
    
    .type-cv { background: #e0e7ff; color: #3730a3; }
    .type-lettre { background: #d1fae5; color: #065f46; }
    .type-rapport { background: #fed7aa; color: #9b3412; }
    .type-autre { background: #e5e7eb; color: #4b5563; }
    
    @media (max-width: 768px) {
        .hero-docs { padding: 25px; }
        .docs-grid { grid-template-columns: 1fr; }
    }
</style>

<div class="container-fluid">
    <!-- Hero Section -->
    <div class="hero-docs animate-fadeInUp">
        <div class="row align-items-center">
            <div class="col-md-8">
                <h1 class="display-4 fw-bold text-white mb-3">
                    <i class="fas fa-folder-open me-3"></i>
                    Mes documents
                </h1>
                <p class="lead text-white-50 mb-0">
                    Gérez vos documents et suivez leur validation par votre encadreur
                </p>
            </div>
            <div class="col-md-4 text-md-end mt-3 mt-md-0">
                <a href="document-uploader.php" class="btn btn-light btn-lg rounded-pill px-4 shadow-sm" 
                   style="cursor: pointer; display: inline-block; position: relative; z-index: 10;">
                    <i class="fas fa-upload me-2"></i>
                    Uploader un document
                </a>
            </div>
        </div>
    </div>
    
    <!-- Documents -->
    <div class="animate-fadeInUp">
        <?php if ($docs): ?>
            <div class="docs-grid">
                <?php foreach ($docs as $d): 
                    $file_ext = strtolower(pathinfo($d['nom_fichier'], PATHINFO_EXTENSION));
                    $icon = 'fa-file-alt';
                    $color = '#667eea';
                    
                    if ($file_ext === 'pdf') {
                        $icon = 'fa-file-pdf';
                        $color = '#ef4444';
                    } elseif (in_array($file_ext, ['doc', 'docx'])) {
                        $icon = 'fa-file-word';
                        $color = '#3b82f6';
                    } elseif (in_array($file_ext, ['jpg', 'jpeg', 'png'])) {
                        $icon = 'fa-file-image';
                        $color = '#10b981';
                    } elseif (in_array($file_ext, ['xls', 'xlsx'])) {
                        $icon = 'fa-file-excel';
                        $color = '#22c55e';
                    }
                    
                    $typeClass = '';
                    $typeLabel = '';
                    switch($d['type_document']) {
                        case 'cv': $typeClass = 'type-cv'; $typeLabel = 'CV'; break;
                        case 'lettre_motivation': $typeClass = 'type-lettre'; $typeLabel = 'Lettre de motivation'; break;
                        case 'rapport_stage': $typeClass = 'type-rapport'; $typeLabel = 'Rapport de stage'; break;
                        default: $typeClass = 'type-autre'; $typeLabel = 'Autre';
                    }
                    
                    $cardClass = $d['est_valide'] ? 'doc-card-validated' : 'doc-card-pending';
                    $badgeClass = $d['est_valide'] ? 'badge-validated' : 'badge-pending';
                    $badgeIcon = $d['est_valide'] ? 'fa-check-circle' : 'fa-clock';
                    $badgeText = $d['est_valide'] ? 'Validé' : 'En attente de validation';
                    $validationClass = $d['est_valide'] ? 'validation-validated' : 'validation-pending';
                ?>
                <div class="doc-card <?= $cardClass ?>">
                    <div class="doc-icon">
                        <i class="fas <?= $icon ?>" style="color: <?= $color ?>; font-size: 3.5rem;"></i>
                    </div>
                    <div class="doc-content">
                        <div class="d-flex justify-content-between align-items-start mb-2">
                            <span class="type-badge <?= $typeClass ?>"><?= $typeLabel ?></span>
                            <span class="badge-status <?= $badgeClass ?>">
                                <i class="fas <?= $badgeIcon ?>"></i>
                                <?= $badgeText ?>
                            </span>
                        </div>
                        
                        <h6 class="doc-title" title="<?= e($d['nom_fichier']) ?>">
                            <?= e(truncate($d['nom_fichier'], 40)) ?>
                        </h6>
                        
                        <div class="doc-meta">
                            <span><i class="fas fa-calendar-alt me-1"></i> <?= format_date($d['date_upload'], 'd/m/Y') ?></span>
                            <span><i class="fas fa-database me-1"></i> <?= format_filesize($d['taille']) ?></span>
                        </div>
                        
                        <!-- Statut de validation avec commentaire -->
                        <?php if ($d['est_valide']): ?>
                            <div class="validation-info <?= $validationClass ?>">
                                <div class="d-flex align-items-start gap-2">
                                    <i class="fas fa-check-circle text-success mt-1"></i>
                                    <div>
                                        <small class="fw-semibold text-success-dark">
                                            Validé le <?= format_date($d['date_validation'], 'd/m/Y H:i') ?>
                                        </small>
                                        <?php if ($d['valide_par'] && ($d['encadreur_nom'] || $d['encadreur_prenom'])): ?>
                                            <br><small class="text-muted">
                                                <i class="fas fa-user-check me-1"></i>
                                                Par <?= e($d['encadreur_prenom'] . ' ' . $d['encadreur_nom']) ?>
                                            </small>
                                        <?php endif; ?>
                                        <?php if (!empty($d['commentaire_validation'])): ?>
                                            <br><small class="text-muted">
                                                <i class="fas fa-comment me-1"></i>
                                                Commentaire: <?= e($d['commentaire_validation']) ?>
                                            </small>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        <?php else: ?>
                            <div class="validation-info <?= $validationClass ?>">
                                <div class="d-flex align-items-center gap-2">
                                    <i class="fas fa-hourglass-half text-warning"></i>
                                    <small class="text-warning-dark">
                                        En attente de validation par votre encadreur
                                    </small>
                                </div>
                            </div>
                        <?php endif; ?>
                        
                        <div class="mt-3">
                            <a href="../../ajax/download_document.php?id=<?= $d['id_document'] ?>" 
                               class="btn-download"
                               onclick="trackDownload(<?= $d['id_document'] ?>)">
                                <i class="fas fa-download me-2"></i>
                                Télécharger le document
                            </a>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="empty-state">
                <i class="fas fa-folder-open fa-5x text-primary mb-4 opacity-50"></i>
                <h3 class="mb-3">Aucun document</h3>
                <p class="text-muted mb-4">
                    Vous n'avez pas encore uploadé de documents.
                    <br>Uploader vos documents pour que votre encadreur puisse les consulter.
                </p>
                <a href="document-uploader.php" class="btn btn-primary btn-lg rounded-pill px-5">
                    <i class="fas fa-upload me-2"></i>
                    Uploader mon premier document
                </a>
            </div>
        <?php endif; ?>
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