<?php
/**
 * Voir une attestation (Secrétaire) - Version avec suppression physique
 */

require_once '../../includes/config.php';
require_once '../../includes/functions.php';
require_once '../../includes/auth.php';

// Vérifier le rôle
require_role('secretaire');

$page_title = "Détails de l'attestation - School-Connection";
$id = (int)($_GET['id'] ?? 0);

if (!$id) {
    $_SESSION['flash']['danger'] = "ID attestation manquant";
    redirect('attestations.php');
}

// =============================================
// TRAITEMENT DE LA SUPPRESSION PHYSIQUE
// =============================================
if (isset($_GET['supprimer_fichier'])) {
    // Récupérer le chemin du fichier
    $stmt = $pdo->prepare("SELECT chemin_fichier, id_attestation FROM attestations WHERE id_attestation = ?");
    $stmt->execute([$id]);
    $att = $stmt->fetch();
    
    if ($att && !empty($att['chemin_fichier'])) {
        $fichier = ROOT_PATH . $att['chemin_fichier'];
        if (file_exists($fichier)) {
            unlink($fichier); // Supprime le fichier physique
            $_SESSION['flash']['warning'] = "Le fichier PDF a été supprimé du serveur. L'attestation reste dans la base de données.";
        } else {
            $_SESSION['flash']['info'] = "Le fichier n'existait déjà plus sur le serveur.";
        }
    }
    redirect("attestation-voir.php?id=$id");
}

// =============================================
// TRAITEMENT DU MASQUAGE (marquer comme supprimé logique)
// =============================================
if (isset($_GET['masquer'])) {
    $stmt = $pdo->prepare("UPDATE attestations SET est_supprimee = 1 WHERE id_attestation = ?");
    $stmt->execute([$id]);
    $_SESSION['flash']['info'] = "Attestation masquée (reste dans la base de données)";
    redirect('attestations.php');
}

// =============================================
// TRAITEMENT DE LA RESTAURATION (afficher à nouveau)
// =============================================
if (isset($_GET['restaurer'])) {
    $stmt = $pdo->prepare("UPDATE attestations SET est_supprimee = 0 WHERE id_attestation = ?");
    $stmt->execute([$id]);
    $_SESSION['flash']['success'] = "Attestation restaurée";
    redirect('attestations.php');
}

// Récupérer les informations de l'attestation
$stmt = $pdo->prepare("
    SELECT a.*, u.nom, u.prenom, u.email, u.photo,
           s.filiere, s.niveau_etude, s.theme_stage, s.date_debut, s.date_fin,
           e.id_encadreur, eu.nom as encadreur_nom, eu.prenom as encadreur_prenom,
           e.profession, e.entreprise
    FROM attestations a
    JOIN stagiaires s ON a.id_stagiaire = s.id_stagiaire
    JOIN utilisateurs u ON s.id_stagiaire = u.id_utilisateur
    LEFT JOIN relations_encadrement r ON s.id_stagiaire = r.id_stagiaire AND r.statut = 'active'
    LEFT JOIN encadreurs e ON r.id_encadreur = e.id_encadreur
    LEFT JOIN utilisateurs eu ON e.id_encadreur = eu.id_utilisateur
    WHERE a.id_attestation = ?
");
$stmt->execute([$id]);
$attestation = $stmt->fetch();

if (!$attestation) {
    $_SESSION['flash']['danger'] = "Attestation non trouvée";
    redirect('attestations.php');
}

// Vérifier si le fichier existe
$fichier_complet = ROOT_PATH . $attestation['chemin_fichier'];
$fichier_existe = file_exists($fichier_complet);

include '../../includes/header.php';
?>

<!-- ✅ CORRECTION : Espacement pour éviter que le contenu soit caché sous la navbar -->
<style>
    .container-fluid:first-of-type {
        margin-top: 20px;
        padding-top: 10px;
    }
    
    @media (max-width: 768px) {
        .container-fluid:first-of-type {
            margin-top: 10px;
        }
    }
    
    .attestation-preview {
        background: white;
        border-radius: 20px;
        box-shadow: 0 10px 30px rgba(0,0,0,0.1);
        overflow: hidden;
    }
    
    .preview-header {
        background: linear-gradient(135deg, #1e3c72, #2a5298);
        color: white;
        padding: 20px 30px;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }
    
    .preview-body {
        padding: 30px;
    }
    
    .attestation-iframe {
        width: 100%;
        height: 800px;
        border: none;
        border-radius: 15px;
        box-shadow: 0 5px 20px rgba(0,0,0,0.1);
    }
    
    .info-card {
        background: #f8f9fa;
        border-radius: 15px;
        padding: 20px;
        margin-bottom: 20px;
    }
    
    .btn-download {
        background: linear-gradient(135deg, #1e3c72, #2a5298);
        color: white;
        padding: 12px 30px;
        border-radius: 50px;
        font-weight: 600;
        transition: all 0.3s ease;
        display: inline-flex;
        align-items: center;
        gap: 10px;
    }
    
    .btn-download:hover {
        transform: translateY(-3px);
        box-shadow: 0 10px 25px rgba(30,60,114,0.3);
        color: white;
    }
    
    .btn-danger-outline {
        background: transparent;
        border: 2px solid #dc3545;
        color: #dc3545;
        padding: 12px 30px;
        border-radius: 50px;
        font-weight: 600;
        transition: all 0.3s ease;
        display: inline-flex;
        align-items: center;
        gap: 10px;
    }
    
    .btn-danger-outline:hover {
        background: #dc3545;
        color: white;
        transform: translateY(-3px);
    }
    
    .btn-warning-outline {
        background: transparent;
        border: 2px solid #ffc107;
        color: #ffc107;
        padding: 12px 30px;
        border-radius: 50px;
        font-weight: 600;
        transition: all 0.3s ease;
        display: inline-flex;
        align-items: center;
        gap: 10px;
    }
    
    .btn-warning-outline:hover {
        background: #ffc107;
        color: white;
        transform: translateY(-3px);
    }
    
    .alert-file-missing {
        background: #fff3cd;
        border: 1px solid #ffc107;
        border-radius: 15px;
        padding: 30px;
        text-align: center;
    }
    
    .file-path-info {
        background: #f0f0f0;
        padding: 10px;
        border-radius: 8px;
        font-family: monospace;
        margin: 15px 0;
        word-break: break-all;
    }
    
    .action-buttons {
        display: flex;
        gap: 15px;
        flex-wrap: wrap;
        margin-top: 20px;
    }
</style>

<div class="container-fluid">
    <div class="row mb-4">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
                <div>
                    <h1 class="h3">
                        <i class="fas fa-file-pdf text-danger me-2"></i>Attestation de stage
                    </h1>
                    <p class="text-muted">N° <?= e($attestation['numero_attestation']) ?></p>
                </div>
                <div class="action-buttons">
                    <a href="attestations.php" class="btn btn-outline-secondary">
                        <i class="fas fa-arrow-left"></i> Retour
                    </a>
                    
                    <?php if ($fichier_existe): ?>
                        <a href="<?= APP_URL ?>/<?= $attestation['chemin_fichier'] ?>" 
                           class="btn-download" 
                           download>
                            <i class="fas fa-download"></i> Télécharger PDF
                        </a>
                        
                        <button type="button" 
                                class="btn-danger-outline" 
                                onclick="supprimerFichier(<?= $id ?>)">
                            <i class="fas fa-trash-alt"></i> Supprimer le fichier
                        </button>
                    <?php endif; ?>
                    
                    <button type="button" 
                            class="btn-warning-outline" 
                            onclick="masquerAttestation(<?= $id ?>)">
                        <i class="fas fa-eye-slash"></i> Masquer
                    </button>
                </div>
            </div>
        </div>
    </div>
    
    <div class="row g-4">
        <div class="col-lg-4">
            <!-- Informations stagiaire -->
            <div class="info-card">
                <h5 class="fw-bold mb-3">
                    <i class="fas fa-user-graduate text-primary me-2"></i>Stagiaire
                </h5>
                <div class="d-flex align-items-center mb-3">
                    <img src="<?= getPhotoUrl($attestation['photo']) ?>" 
                         alt="" class="rounded-circle me-3" width="60" height="60">
                    <div>
                        <h6 class="mb-0"><?= e($attestation['prenom'] . ' ' . $attestation['nom']) ?></h6>
                        <small class="text-muted"><?= e($attestation['email']) ?></small>
                    </div>
                </div>
                <p><i class="fas fa-graduation-cap text-primary me-2"></i> <?= e($attestation['filiere']) ?> (<?= e($attestation['niveau_etude']) ?>)</p>
                <p><i class="fas fa-calendar text-primary me-2"></i> Stage: <?= format_date($attestation['date_debut']) ?> - <?= format_date($attestation['date_fin']) ?></p>
                <p><i class="fas fa-book text-primary me-2"></i> <?= e(truncate($attestation['theme_stage'], 80)) ?></p>
            </div>
            
            <!-- Informations attestation -->
            <div class="info-card">
                <h5 class="fw-bold mb-3">
                    <i class="fas fa-file-alt text-primary me-2"></i>Informations
                </h5>
                <p><strong>Numéro:</strong> <?= e($attestation['numero_attestation']) ?></p>
                <p><strong>Date d'émission:</strong> <?= format_date($attestation['date_emission'], 'd/m/Y') ?></p>
                <p><strong>Téléchargements:</strong> <?= $attestation['nb_telechargements'] ?> fois</p>
                <p><strong>Fichier:</strong> 
                    <?php if ($fichier_existe): ?>
                        <span class="badge bg-success">✓ Présent</span>
                    <?php else: ?>
                        <span class="badge bg-danger">✗ Manquant</span>
                    <?php endif; ?>
                </p>
                <?php if ($attestation['signe_par']): ?>
                    <p><strong>Signé par:</strong> Secrétaire (ID: <?= $attestation['signe_par'] ?>)</p>
                <?php endif; ?>
            </div>
            
            <!-- Encadreur -->
            <?php if ($attestation['id_encadreur']): ?>
            <div class="info-card">
                <h5 class="fw-bold mb-3">
                    <i class="fas fa-chalkboard-teacher text-primary me-2"></i>Encadreur
                </h5>
                <p><strong><?= e($attestation['encadreur_prenom'] . ' ' . $attestation['encadreur_nom']) ?></strong></p>
                <p class="mb-0"><?= e($attestation['profession']) ?></p>
                <p class="text-muted"><?= e($attestation['entreprise']) ?></p>
            </div>
            <?php endif; ?>
        </div>
        
        <div class="col-lg-8">
            <?php if ($fichier_existe): ?>
                <div class="attestation-preview">
                    <div class="preview-header">
                        <h5 class="mb-0">
                            <i class="fas fa-eye me-2"></i>Aperçu de l'attestation
                        </h5>
                        <a href="<?= APP_URL ?>/<?= $attestation['chemin_fichier'] ?>" 
                           class="btn btn-sm btn-light" target="_blank" download>
                            <i class="fas fa-download me-1"></i> Télécharger
                        </a>
                    </div>
                    <div class="preview-body">
                        <iframe src="<?= APP_URL ?>/<?= $attestation['chemin_fichier'] ?>" 
                                class="attestation-iframe">
                        </iframe>
                    </div>
                </div>
                
                <div class="mt-3 text-center">
                    <a href="<?= APP_URL ?>/<?= $attestation['chemin_fichier'] ?>" 
                       class="btn-download btn-lg px-5" 
                       download>
                        <i class="fas fa-download me-2"></i>Télécharger l'attestation (PDF)
                    </a>
                </div>
                
            <?php else: ?>
                <div class="alert-file-missing">
                    <i class="fas fa-file-pdf fa-4x text-danger mb-3"></i>
                    <h4 class="text-danger">❌ Fichier introuvable</h4>
                    <p>Le fichier PDF de cette attestation n'a pas été trouvé sur le serveur.</p>
                    
                    <div class="file-path-info">
                        <strong>Chemin recherché :</strong><br>
                        <?= ROOT_PATH . $attestation['chemin_fichier'] ?>
                    </div>
                    
                    <div class="mt-3">
                        <a href="attestation-generer.php?stagiaire=<?= $attestation['id_stagiaire'] ?>" 
                           class="btn btn-warning btn-lg mt-3">
                            <i class="fas fa-sync me-2"></i> Régénérer l'attestation
                        </a>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
function supprimerFichier(id) {
    Swal.fire({
        title: '⚠️ Supprimer le fichier',
        text: 'Cette action supprime UNIQUEMENT le fichier PDF du serveur. L\'attestation reste dans la base de données. Continuer ?',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#dc3545',
        cancelButtonColor: '#6c757d',
        confirmButtonText: 'Oui, supprimer le fichier',
        cancelButtonText: 'Annuler'
    }).then((result) => {
        if (result.isConfirmed) {
            window.location.href = '?id=' + id + '&supprimer_fichier=1';
        }
    });
}

function masquerAttestation(id) {
    Swal.fire({
        title: '👁️ Masquer l\'attestation',
        text: 'Cette action masque l\'attestation (elle reste dans la base de données). Continuer ?',
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#ffc107',
        cancelButtonColor: '#6c757d',
        confirmButtonText: 'Oui, masquer',
        cancelButtonText: 'Annuler'
    }).then((result) => {
        if (result.isConfirmed) {
            window.location.href = '?id=' + id + '&masquer=1';
        }
    });
}
</script>

<!-- SweetAlert2 -->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<?php include '../../includes/footer.php'; ?>