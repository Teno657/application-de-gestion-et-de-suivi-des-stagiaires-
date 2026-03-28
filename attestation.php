<?php
/**
 * Attestations de stage (Stagiaire)
 * Version avec téléchargement forcé et modal de confirmation
 */

require_once '../../includes/config.php';
require_once '../../includes/functions.php';
require_once '../../includes/auth.php';

// Vérifier le rôle
require_role('stagiaire');

$page_title = "Mon attestation - School-Connection";
$user_id = $_SESSION['user_id'];

// Récupérer les attestations du stagiaire
$attestations = $pdo->prepare("
    SELECT a.*, u.nom, u.prenom
    FROM attestations a
    LEFT JOIN utilisateurs u ON a.signe_par = u.id_utilisateur
    WHERE a.id_stagiaire = ?
    ORDER BY a.date_emission DESC
");
$attestations->execute([$user_id]);
$attests = $attestations->fetchAll();

// Récupérer les informations du stage
$stage = $pdo->prepare("
    SELECT theme_stage, date_debut, date_fin, statut_inscription, filiere, niveau_etude
    FROM stagiaires
    WHERE id_stagiaire = ?
");
$stage->execute([$user_id]);
$info_stage = $stage->fetch();

// Calculer la durée du stage
$duree_stage = '';
if ($info_stage['date_debut'] && $info_stage['date_fin']) {
    $debut = new DateTime($info_stage['date_debut']);
    $fin = new DateTime($info_stage['date_fin']);
    $interval = $debut->diff($fin);
    $duree_stage = $interval->days . ' jours';
    if ($interval->m > 0) {
        $duree_stage = $interval->m . ' mois et ' . $interval->d . ' jours';
    }
}

include '../../includes/header.php';
?>

<style>
    /* Animations et styles personnalisés */
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
    
    @keyframes pulse {
        0% {
            box-shadow: 0 0 0 0 rgba(102, 126, 234, 0.4);
        }
        70% {
            box-shadow: 0 0 0 20px rgba(102, 126, 234, 0);
        }
        100% {
            box-shadow: 0 0 0 0 rgba(102, 126, 234, 0);
        }
    }
    
    .animate-fadeInUp {
        animation: fadeInUp 0.6s ease-out;
    }
    
    .certificate-card {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        border: none;
        border-radius: 20px;
        overflow: hidden;
        transition: transform 0.3s ease, box-shadow 0.3s ease;
    }
    
    .certificate-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 20px 40px rgba(0, 0, 0, 0.2);
    }
    
    .attestation-card {
        background: white;
        border-radius: 20px;
        transition: all 0.3s ease;
        border: 1px solid rgba(0, 0, 0, 0.05);
        position: relative;
        overflow: hidden;
    }
    
    .attestation-card::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        height: 5px;
        background: linear-gradient(90deg, #667eea, #764ba2, #f093fb);
    }
    
    .attestation-card:hover {
        transform: translateY(-3px);
        box-shadow: 0 15px 30px rgba(0, 0, 0, 0.1);
    }
    
    .btn-download {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        border: none;
        transition: all 0.3s ease;
        position: relative;
        overflow: hidden;
    }
    
    .btn-download:hover {
        transform: translateY(-2px);
        box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
    }
    
    .btn-download:active {
        transform: translateY(0);
    }
    
    .status-badge {
        padding: 6px 12px;
        border-radius: 20px;
        font-weight: 600;
        font-size: 0.8rem;
        display: inline-flex;
        align-items: center;
        gap: 6px;
    }
    
    .status-terminé {
        background: linear-gradient(135deg, #10b981, #059669);
        color: white;
        animation: pulse 2s infinite;
    }
    
    .status-en-cours {
        background: linear-gradient(135deg, #f59e0b, #d97706);
        color: white;
    }
    
    .info-box {
        background: linear-gradient(135deg, #f8f9ff 0%, #f0f2ff 100%);
        border-radius: 15px;
        padding: 20px;
        margin-bottom: 20px;
    }
    
    .info-icon {
        width: 50px;
        height: 50px;
        background: linear-gradient(135deg, #667eea, #764ba2);
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        color: white;
        font-size: 1.5rem;
    }
    
    .counter-number {
        font-size: 2rem;
        font-weight: bold;
        background: linear-gradient(135deg, #667eea, #764ba2);
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
        background-clip: text;
    }
    
    /* Modal personnalisé */
    .modal-custom {
        display: none;
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(0, 0, 0, 0.5);
        z-index: 9999;
        align-items: center;
        justify-content: center;
        animation: fadeIn 0.3s ease;
    }
    
    @keyframes fadeIn {
        from { opacity: 0; }
        to { opacity: 1; }
    }
    
    .modal-content-custom {
        background: white;
        border-radius: 20px;
        max-width: 400px;
        width: 90%;
        padding: 30px;
        text-align: center;
        animation: slideIn 0.3s ease;
    }
    
    @keyframes slideIn {
        from {
            transform: translateY(-50px);
            opacity: 0;
        }
        to {
            transform: translateY(0);
            opacity: 1;
        }
    }
    
    @media (max-width: 768px) {
        .attestation-card {
            margin-bottom: 20px;
        }
    }
</style>

<div class="container-fluid">
    <!-- En-tête avec animation -->
    <div class="row mb-5 animate-fadeInUp">
        <div class="col-12">
            <div class="certificate-card text-white p-5">
                <div class="row align-items-center">
                    <div class="col-md-8">
                        <h1 class="display-4 fw-bold mb-3">
                            <i class="fas fa-certificate me-3"></i>
                            Mon attestation de stage
                        </h1>
                        <p class="lead mb-0 opacity-90">
                            Téléchargez votre attestation officielle de stage
                        </p>
                    </div>
                    <div class="col-md-4 text-center">
                        <i class="fas fa-file-alt fa-4x opacity-50"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <?php if ($info_stage['statut_inscription'] === 'termine'): ?>
        <!-- Message de félicitations -->
        <div class="alert alert-success border-0 shadow-sm animate-fadeInUp" style="background: linear-gradient(135deg, #d1fae5, #a7f3d0); border-radius: 15px;">
            <div class="d-flex align-items-center">
                <i class="fas fa-trophy fa-2x me-3 text-success"></i>
                <div>
                    <h5 class="mb-1 fw-bold text-success">Félicitations !</h5>
                    <p class="mb-0 text-success-dark">Votre stage est terminé. Vous pouvez télécharger votre attestation officielle ci-dessous.</p>
                </div>
            </div>
        </div>
        
        <!-- Informations du stage -->
        <div class="info-box animate-fadeInUp mb-4">
            <div class="row align-items-center">
                <div class="col-md-3 text-center">
                    <div class="info-icon mx-auto mb-3 mb-md-0">
                        <i class="fas fa-graduation-cap"></i>
                    </div>
                </div>
                <div class="col-md-9">
                    <div class="row">
                        <div class="col-md-4">
                            <small class="text-muted">Thème du stage</small>
                            <p class="fw-bold mb-0"><?= e($info_stage['theme_stage'] ?? 'Non spécifié') ?></p>
                        </div>
                        <div class="col-md-4">
                            <small class="text-muted">Période</small>
                            <p class="fw-bold mb-0">
                                <?= format_date($info_stage['date_debut']) ?> - <?= format_date($info_stage['date_fin']) ?>
                            </p>
                        </div>
                        <div class="col-md-4">
                            <small class="text-muted">Durée</small>
                            <p class="fw-bold mb-0"><?= $duree_stage ?></p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Attestations -->
        <?php if ($attests): ?>
            <div class="row g-4">
                <?php foreach ($attests as $index => $a): ?>
                <div class="col-md-6 col-lg-4 animate-fadeInUp" style="animation-delay: <?= $index * 0.1 ?>s">
                    <div class="attestation-card p-4">
                        <div class="text-center mb-3">
                            <div class="mb-3">
                                <i class="fas fa-file-pdf text-danger fa-4x"></i>
                            </div>
                            <span class="status-badge status-terminé mb-3">
                                <i class="fas fa-check-circle"></i> Disponible
                            </span>
                        </div>
                        
                        <div class="mb-4">
                            <h5 class="text-center mb-3">Attestation de stage</h5>
                            <div class="border-top pt-3">
                                <div class="d-flex justify-content-between mb-2">
                                    <span class="text-muted">N° attestation:</span>
                                    <span class="fw-bold"><?= e($a['numero_attestation']) ?></span>
                                </div>
                                <div class="d-flex justify-content-between mb-2">
                                    <span class="text-muted">Date d'émission:</span>
                                    <span><?= format_date($a['date_emission']) ?></span>
                                </div>
                                <div class="d-flex justify-content-between mb-2">
                                    <span class="text-muted">Signé par:</span>
                                    <span><?= e($a['prenom'] ?? '') ?> <?= e($a['nom'] ?? 'Secrétaire') ?></span>
                                </div>
                                <div class="d-flex justify-content-between">
                                    <span class="text-muted">Téléchargements:</span>
                                    <span>
                                        <i class="fas fa-download text-primary me-1"></i>
                                        <span id="download-count-<?= $a['id_attestation'] ?>"> </span>
                                    </span>
                                </div>
                            </div>
                        </div>
                        
                        <div class="d-grid gap-2">
                            <button class="btn btn-download text-white py-2" 
                                    onclick="confirmDownload(<?= $a['id_attestation'] ?>, '<?= e($a['numero_attestation']) ?>')">
                                <i class="fas fa-download me-2"></i>
                                Télécharger l'attestation
                            </button>
                            <button class="btn btn-outline-secondary py-2" 
                                    onclick="shareAttestation('<?= e($a['numero_attestation']) ?>')">
                                <i class="fas fa-share-alt me-2"></i>
                                Partager
                            </button>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            
            <!-- Statistiques des attestations -->
           
        <?php else: ?>
            <!-- Aucune attestation -->
            <div class="card border-0 shadow-lg text-center p-5 animate-fadeInUp" style="border-radius: 20px;">
                <div class="card-body py-5">
                    <div class="mb-4">
                        <i class="fas fa-file-alt fa-5x text-muted mb-3"></i>
                        <i class="fas fa-clock fa-3x text-warning position-relative" style="top: -30px; left: -20px;"></i>
                    </div>
                    <h3 class="mb-3">Attestation en cours de génération</h3>
                    <p class="text-muted mb-4">
                        Votre stage est terminé, mais l'attestation n'a pas encore été générée.
                        <br>Veuillez contacter la secrétaire pour obtenir votre document.
                    </p>
                    <button class="btn btn-primary btn-lg px-5" onclick="contactSecretary()">
                        <i class="fas fa-envelope me-2"></i>
                        Contacter la secrétaire
                    </button>
                </div>
            </div>
        <?php endif; ?>
        
    <?php else: ?>
        <!-- Stage non terminé -->
        <div class="card border-0 shadow-lg text-center p-5 animate-fadeInUp" style="border-radius: 20px; background: linear-gradient(135deg, #fef3c7, #fde68a);">
            <div class="card-body py-5">
                <div class="mb-4">
                    <i class="fas fa-hourglass-half fa-5x text-warning mb-3"></i>
                </div>
                <h3 class="mb-3">Attestation non encore disponible</h3>
                <p class="mb-4">
                    L'attestation sera automatiquement disponible une fois votre stage terminé.
                </p>
                <div class="progress mb-4" style="height: 10px; border-radius: 10px;">
                    <?php
                    $pourcentage = 0;
                    if ($info_stage['date_debut'] && $info_stage['date_fin']) {
                        $total = (strtotime($info_stage['date_fin']) - strtotime($info_stage['date_debut'])) / (60 * 60 * 24);
                        $ecoule = (time() - strtotime($info_stage['date_debut'])) / (60 * 60 * 24);
                        $pourcentage = min(100, max(0, ($ecoule / $total) * 100));
                    }
                    ?>
                    <div class="progress-bar bg-warning" style="width: <?= $pourcentage ?>%; border-radius: 10px;"></div>
                </div>
                <p class="mb-0">
                    <i class="fas fa-calendar-alt me-2"></i>
                    Fin du stage prévue le <strong><?= format_date($info_stage['date_fin']) ?></strong>
                    <br>
                    <small class="text-muted">Plus que <?= ceil((strtotime($info_stage['date_fin']) - time()) / (60 * 60 * 24)) ?> jours</small>
                </p>
            </div>
        </div>
        
        <!-- Informations complémentaires -->
        <div class="row mt-4 animate-fadeInUp">
            <div class="col-12">
                <div class="alert alert-info border-0 shadow-sm" style="background: linear-gradient(135deg, #dbeafe, #bfdbfe); border-radius: 15px;">
                    <div class="d-flex">
                        <i class="fas fa-info-circle fa-2x me-3 text-primary"></i>
                        <div>
                            <h6 class="mb-1 fw-bold">À savoir</h6>
                            <p class="mb-0 small">Dès la fin de votre stage, l'attestation sera automatiquement générée et disponible en téléchargement ici même. Vous recevrez également une notification par email.</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>

<!-- Modal de confirmation -->
<div id="downloadModal" class="modal-custom">
    <div class="modal-content-custom">
        <i class="fas fa-file-pdf text-danger fa-4x mb-3"></i>
        <h4 class="mb-3">Téléchargement de l'attestation</h4>
        <p class="text-muted mb-4">
            Êtes-vous sûr de vouloir télécharger votre attestation de stage ?
        </p>
        <div class="d-grid gap-2">
            <button id="confirmDownloadBtn" class="btn btn-download text-white py-2">
                <i class="fas fa-download me-2"></i>
                Oui, télécharger
            </button>
            <button onclick="closeModal()" class="btn btn-outline-secondary py-2">
                <i class="fas fa-times me-2"></i>
                Annuler
            </button>
        </div>
    </div>
</div>

<script>
    let currentAttestationId = null;
    
    // Afficher le modal de confirmation
    function confirmDownload(attestationId, numero) {
        currentAttestationId = attestationId;
        const modal = document.getElementById('downloadModal');
        modal.style.display = 'flex';
        
        // Mettre à jour le message
        const modalContent = modal.querySelector('.modal-content-custom');
        modalContent.querySelector('p').innerHTML = `Êtes-vous sûr de vouloir télécharger l'attestation n°<strong>${numero}</strong> ?`;
    }
    
    // Fermer le modal
    function closeModal() {
        const modal = document.getElementById('downloadModal');
        modal.style.display = 'none';
        currentAttestationId = null;
    }
    
    // Télécharger l'attestation
    function downloadAttestation() {
        if (currentAttestationId) {
            // Créer un lien invisible pour le téléchargement
            const link = document.createElement('a');
            link.href = `../../ajax/download_attestation.php?id=${currentAttestationId}`;
            link.download = '';
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
            
            // Incrémenter le compteur visuellement
            const counterSpan = document.getElementById(`download-count-${currentAttestationId}`);
            if (counterSpan) {
                let currentCount = parseInt(counterSpan.innerText);
                counterSpan.innerText = currentCount + 1;
            }
            
            // Fermer le modal
            closeModal();
        }
    }
    
    // Événement pour le bouton de confirmation
    document.getElementById('confirmDownloadBtn').addEventListener('click', downloadAttestation);
    
    // Fermer le modal en cliquant à l'extérieur
    window.onclick = function(event) {
        const modal = document.getElementById('downloadModal');
        if (event.target === modal) {
            closeModal();
        }
    }
    
    // Fonction pour partager l'attestation
    function shareAttestation(numero) {
        if (navigator.share) {
            navigator.share({
                title: 'Attestation de stage',
                text: `Mon attestation de stage n°${numero}`,
                url: window.location.href
            }).catch(console.error);
        } else {
            // Fallback
            alert(`Votre attestation n°${numero} est disponible en téléchargement.`);
        }
    }
    
    // Fonction pour contacter la secrétaire
    function contactSecretary() {
        window.location.href = '../../messagerie/nouvelle.php?destinataire=secretaire';
    }
    
    // Animation des compteurs
    document.addEventListener('DOMContentLoaded', function() {
        const counters = document.querySelectorAll('.counter-number');
        counters.forEach(counter => {
            const target = parseInt(counter.innerText);
            let current = 0;
            const increment = target / 50;
            const timer = setInterval(() => {
                current += increment;
                if (current >= target) {
                    counter.innerText = target;
                    clearInterval(timer);
                } else {
                    counter.innerText = Math.floor(current);
                }
            }, 20);
        });
    });
</script>

<?php include '../../includes/footer.php'; ?>