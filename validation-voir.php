<?php
/**
 * Voir les détails d'une inscription (Secrétaire)
 */

require_once '../../includes/config.php';
require_once '../../includes/functions.php';
require_once '../../includes/auth.php';

// Vérifier le rôle
require_role('secretaire');

$page_title = "Détails de l'inscription - School-Connection";
$id = (int)($_GET['id'] ?? 0);

if (!$id) {
    $_SESSION['flash']['danger'] = "ID stagiaire manquant";
    redirect('validations.php');
}

// Récupérer les informations du stagiaire
$stmt = $pdo->prepare("
    SELECT u.*, s.*
    FROM utilisateurs u
    JOIN stagiaires s ON u.id_utilisateur = s.id_stagiaire
    WHERE u.id_utilisateur = ? AND s.statut_inscription = 'en_attente'
");
$stmt->execute([$id]);
$stagiaire = $stmt->fetch();

if (!$stagiaire) {
    $_SESSION['flash']['danger'] = "Inscription non trouvée ou déjà traitée";
    redirect('validations.php');
}

// Récupérer les documents
$documents = $pdo->prepare("
    SELECT * FROM documents 
    WHERE id_stagiaire = ? 
    ORDER BY date_upload DESC
");
$documents->execute([$id]);
$docs = $documents->fetchAll();

include '../../includes/header.php';
?>

<div class="container-fluid">
    <div class="row mb-4">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h1 class="h3">Détails de l'inscription</h1>
                    <p class="text-muted">Examinez les informations du candidat</p>
                </div>
                <a href="validations.php" class="btn btn-outline-secondary">
                    <i class="fas fa-arrow-left"></i> Retour
                </a>
            </div>
        </div>
    </div>
    
    <div class="row g-4">
        <div class="col-md-4">
            <!-- Carte d'identité -->
            <div class="card border-0 shadow-sm">
                <div class="card-body text-center">
                    <img src="<?= getPhotoUrl($stagiaire['photo']) ?>" 
                         alt="Photo" class="rounded-circle img-thumbnail mb-3" 
                         style="width: 150px; height: 150px; object-fit: cover;">
                    
                    <h4><?= e($stagiaire['prenom'] . ' ' . $stagiaire['nom']) ?></h4>
                    <p class="text-muted"><?= e($stagiaire['email']) ?></p>
                    
                    <hr>
                    
                    <div class="text-start">
                        <p><i class="fas fa-phone text-primary me-2"></i> <?= e($stagiaire['telephone'] ?? 'Non renseigné') ?></p>
                        <p><i class="fas fa-map-marker-alt text-primary me-2"></i> <?= e($stagiaire['adresse'] ?? 'Non renseignée') ?></p>
                        <p><i class="fas fa-calendar text-primary me-2"></i> Inscrit le <?= format_datetime($stagiaire['date_creation']) ?></p>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-md-8">
            <!-- Informations académiques -->
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white">
                    <h6 class="card-title mb-0">Informations académiques</h6>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <p><strong>Filière :</strong> <?= e($stagiaire['filiere']) ?></p>
                            <p><strong>Niveau :</strong> <?= e($stagiaire['niveau_etude']) ?></p>
                            <p><strong>Établissement :</strong> <?= e($stagiaire['etablissement'] ?? 'Non renseigné') ?></p>
                        </div>
                        <div class="col-md-6">
                            <p><strong>Date début :</strong> <?= format_date($stagiaire['date_debut']) ?></p>
                            <p><strong>Date fin :</strong> <?= format_date($stagiaire['date_fin']) ?></p>
                            <p><strong>Durée :</strong> <?= days_remaining($stagiaire['date_fin']) + 90 ?> jours</p>
                        </div>
                        <div class="col-12">
                            <p><strong>Thème du stage :</strong></p>
                            <div class="p-3 bg-light rounded">
                                <?= nl2br(e($stagiaire['theme_stage'])) ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Documents -->
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white">
                    <h6 class="card-title mb-0">Documents fournis</h6>
                </div>
                <div class="card-body">
                    <?php if ($docs): ?>
                        <div class="row g-3">
                            <?php foreach ($docs as $doc): ?>
                            <div class="col-md-6">
                                <div class="d-flex align-items-center p-3 border rounded">
                                    <i class="fas fa-file-pdf text-danger fa-2x me-3"></i>
                                    <div class="flex-grow-1">
                                        <strong class="d-block"><?= e($doc['nom_fichier']) ?></strong>
                                        <small class="text-muted">
                                            <?= format_filesize($doc['taille']) ?> - 
                                            <?= format_datetime($doc['date_upload'], 'd/m/Y H:i') ?>
                                        </small>
                                    </div>
                                    <a href="<?= APP_URL ?>/<?= $doc['chemin'] ?>" target="_blank" 
                                       class="btn btn-sm btn-outline-primary">
                                        <i class="fas fa-download"></i>
                                    </a>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <p class="text-muted text-center py-3">Aucun document fourni</p>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Actions -->
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <button type="button" class="btn btn-outline-danger btn-lg" 
                                onclick="rejeter(<?= $id ?>, '<?= e($stagiaire['prenom'] . ' ' . $stagiaire['nom']) ?>')">
                            <i class="fas fa-times"></i> Rejeter
                        </button>
                        <a href="?valider=<?= $id ?>" class="btn btn-success btn-lg" 
                           onclick="return confirm('Valider cette inscription ?')">
                            <i class="fas fa-check"></i> Valider l'inscription
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal de rejet -->
<div class="modal fade" id="rejectModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Rejeter l'inscription</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p>Vous êtes sur le point de rejeter l'inscription de <strong id="rejectName"></strong>.</p>
                <div class="mb-3">
                    <label for="raison" class="form-label">Raison du rejet</label>
                    <textarea class="form-control" id="raison" rows="3" placeholder="Expliquez la raison..."></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                <a href="#" id="rejectLink" class="btn btn-danger">Confirmer le rejet</a>
            </div>
        </div>
    </div>
</div>

<script>
function rejeter(id, name) {
    document.getElementById('rejectName').textContent = name;
    document.getElementById('rejectLink').href = 'validations.php?rejeter=' + id + '&raison=' + encodeURIComponent(document.getElementById('raison').value);
    new bootstrap.Modal(document.getElementById('rejectModal')).show();
}

document.getElementById('raison').addEventListener('input', function() {
    const urlParams = new URLSearchParams(window.location.search);
    const id = urlParams.get('id');
    if (id) {
        document.getElementById('rejectLink').href = 'validations.php?rejeter=' + id + '&raison=' + encodeURIComponent(this.value);
    }
});
</script>

<?php include '../../includes/footer.php'; ?>