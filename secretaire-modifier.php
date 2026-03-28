<?php
/**
 * Modifier un secrétaire (Admin)
 */

require_once '../../includes/config.php';
require_once '../../includes/functions.php';
require_once '../../includes/auth.php';

// Vérifier le rôle
require_role('admin');

$page_title = "Modifier un secrétaire - School-Connection";
$id = (int)($_GET['id'] ?? 0);

if (!$id) {
    $_SESSION['flash']['danger'] = "ID secrétaire manquant";
    redirect('secretaires.php');
}

// Récupérer les informations du secrétaire
$stmt = $pdo->prepare("
    SELECT u.*, s.*
    FROM utilisateurs u
    JOIN secretaires s ON u.id_utilisateur = s.id_secretaire
    WHERE u.id_utilisateur = ?
");
$stmt->execute([$id]);
$secretaire = $stmt->fetch();

if (!$secretaire) {
    $_SESSION['flash']['danger'] = "Secrétaire non trouvé";
    redirect('secretaires.php');
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Vérifier le token CSRF
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $error = 'Token de sécurité invalide';
    } else {
        $nom = cleanInput($_POST['nom'] ?? '');
        $prenom = cleanInput($_POST['prenom'] ?? '');
        $telephone = cleanInput($_POST['telephone'] ?? '');
        $adresse = cleanInput($_POST['adresse'] ?? '');
        
        // Informations spécifiques
        $service = cleanInput($_POST['service'] ?? '');
        $matricule = cleanInput($_POST['matricule'] ?? '');
        $date_embauche = $_POST['date_embauche'] ?? '';
        
        // Statut du compte
        $est_actif = isset($_POST['est_actif']) ? 1 : 0;
        $est_bloque = isset($_POST['est_bloque']) ? 1 : 0;
        $raison_blocage = cleanInput($_POST['raison_blocage'] ?? '');
        
        // Validation
        $errors = [];
        
        if (empty($nom)) $errors[] = 'Le nom est requis';
        if (empty($prenom)) $errors[] = 'Le prénom est requis';
        if (empty($service)) $errors[] = 'Le service est requis';
        
        // Vérifier si le matricule est unique (sauf pour ce secrétaire)
        if (!empty($matricule)) {
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM secretaires WHERE matricule = ? AND id_secretaire != ?");
            $stmt->execute([$matricule, $id]);
            if ($stmt->fetchColumn() > 0) {
                $errors[] = 'Ce matricule est déjà utilisé';
            }
        }
        
        // Upload de la photo
        $photo_path = $secretaire['photo'];
        if (isset($_FILES['photo']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
            $result = upload_file($_FILES['photo'], UPLOADS_PATH . 'photos/', ALLOWED_IMAGES, 2 * 1024 * 1024);
            if ($result['success']) {
                // Supprimer l'ancienne photo si ce n'est pas la photo par défaut
                if (!empty($secretaire['photo']) && $secretaire['photo'] !== 'default-avatar.png' && file_exists(ROOT_PATH . $secretaire['photo'])) {
                    unlink(ROOT_PATH . $secretaire['photo']);
                }
                $photo_path = 'uploads/photos/' . $result['filename'];
            } else {
                $errors[] = 'Erreur photo : ' . $result['message'];
            }
        }
        
        if (empty($errors)) {
            try {
                $pdo->beginTransaction();
                
                // Mettre à jour l'utilisateur
                $stmt = $pdo->prepare("
                    UPDATE utilisateurs 
                    SET nom = ?, prenom = ?, telephone = ?, adresse = ?, photo = ?,
                        est_actif = ?, est_bloque = ?, raison_blocage = ?
                    WHERE id_utilisateur = ?
                ");
                $stmt->execute([$nom, $prenom, $telephone, $adresse, $photo_path, 
                               $est_actif, $est_bloque, $raison_blocage, $id]);
                
                // Mettre à jour le secrétaire
                $stmt = $pdo->prepare("
                    UPDATE secretaires 
                    SET service = ?, matricule = ?, date_embauche = ?
                    WHERE id_secretaire = ?
                ");
                $stmt->execute([$service, $matricule, $date_embauche ?: null, $id]);
                
                // Journaliser l'action
                log_action($_SESSION['user_id'], 'UPDATE_SECRETAIRE', "Modification du secrétaire ID: $id", 'modification', 'secretaires', $id);
                
                $pdo->commit();
                
                $_SESSION['flash']['success'] = "Le secrétaire a été modifié avec succès";
                redirect("secretaires.php");
                
            } catch (PDOException $e) {
                $pdo->rollBack();
                $error = 'Erreur : ' . $e->getMessage();
            }
        } else {
            $error = implode('<br>', $errors);
        }
    }
}

include '../../includes/header.php';
?>

<style>
    .hero-form {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        border-radius: 25px;
        padding: 40px;
        margin-bottom: 30px;
    }
    
    .form-card {
        background: white;
        border-radius: 25px;
        padding: 35px;
        box-shadow: 0 10px 40px rgba(0,0,0,0.1);
    }
    
    .form-control, .form-select {
        border-radius: 12px;
        padding: 12px 16px;
        border: 2px solid #e5e7eb;
        transition: all 0.3s ease;
    }
    
    .form-control:focus, .form-select:focus {
        border-color: #667eea;
        box-shadow: 0 0 0 3px rgba(102,126,234,0.1);
        outline: none;
    }
    
    .btn-submit {
        background: linear-gradient(135deg, #667eea, #764ba2);
        border: none;
        padding: 14px;
        border-radius: 12px;
        font-weight: 600;
        transition: all 0.3s ease;
    }
    
    .btn-submit:hover {
        transform: translateY(-2px);
        box-shadow: 0 10px 25px rgba(102,126,234,0.4);
    }
    
    .section-title {
        font-size: 1rem;
        font-weight: 600;
        color: #667eea;
        margin-bottom: 20px;
        padding-bottom: 10px;
        border-bottom: 2px solid #e5e7eb;
    }
    
    .avatar-preview {
        width: 120px;
        height: 120px;
        border-radius: 50%;
        object-fit: cover;
        border: 3px solid #667eea;
        margin-bottom: 15px;
    }
    
    .required-star {
        color: #ef4444;
        margin-left: 4px;
    }
</style>

<div class="container-fluid">
    <div class="hero-form">
        <div class="row align-items-center">
            <div class="col-md-8">
                <h1 class="display-4 fw-bold text-white mb-3">
                    <i class="fas fa-edit me-3"></i>
                    Modifier le secrétaire
                </h1>
                <p class="lead text-white-50 mb-0">
                    Modifiez les informations de <?= e($secretaire['prenom'] . ' ' . $secretaire['nom']) ?>
                </p>
            </div>
            <div class="col-md-4 text-md-end mt-3 mt-md-0">
                <a href="secretaires.php" class="btn btn-light btn-lg rounded-pill px-4">
                    <i class="fas fa-arrow-left me-2"></i>
                    Retour
                </a>
            </div>
        </div>
    </div>
    
    <div class="row">
        <div class="col-lg-10 mx-auto">
            <div class="form-card">
                <?php if ($error): ?>
                    <div class="alert alert-danger">
                        <i class="fas fa-exclamation-circle me-2"></i><?= $error ?>
                    </div>
                <?php endif; ?>
                
                <form method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
                    
                    <!-- Photo -->
                    <div class="text-center mb-4">
                        <img src="<?= getPhotoUrl($secretaire['photo'] ?? '') ?>" 
                             alt="Photo" class="avatar-preview" id="photoPreview">
                        <div class="mt-2">
                            <label class="btn btn-outline-primary btn-sm rounded-pill">
                                <i class="fas fa-camera me-1"></i>Changer la photo
                                <input type="file" name="photo" accept="image/*" style="display: none;" onchange="previewPhoto(this)">
                            </label>
                        </div>
                    </div>
                    
                    <!-- Informations personnelles -->
                    <div class="section-title">
                        <i class="fas fa-user me-2"></i>Informations personnelles
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-semibold">Nom <span class="required-star">*</span></label>
                            <input type="text" class="form-control" name="nom" value="<?= e($secretaire['nom']) ?>" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-semibold">Prénom <span class="required-star">*</span></label>
                            <input type="text" class="form-control" name="prenom" value="<?= e($secretaire['prenom']) ?>" required>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-semibold">Téléphone</label>
                            <input type="tel" class="form-control" name="telephone" value="<?= e($secretaire['telephone']) ?>">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-semibold">Email</label>
                            <input type="email" class="form-control" value="<?= e($secretaire['email']) ?>" disabled>
                            <small class="text-muted">L'email ne peut pas être modifié</small>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Adresse</label>
                        <textarea class="form-control" name="adresse" rows="2"><?= e($secretaire['adresse']) ?></textarea>
                    </div>
                    
                    <!-- Informations professionnelles -->
                    <div class="section-title mt-4">
                        <i class="fas fa-briefcase me-2"></i>Informations professionnelles
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-semibold">Service <span class="required-star">*</span></label>
                            <input type="text" class="form-control" name="service" value="<?= e($secretaire['service']) ?>" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-semibold">Matricule</label>
                            <input type="text" class="form-control" name="matricule" value="<?= e($secretaire['matricule']) ?>">
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Date d'embauche</label>
                        <input type="date" class="form-control" name="date_embauche" value="<?= $secretaire['date_embauche'] ?>">
                    </div>
                    
                    <!-- Statut du compte -->
                    <div class="section-title mt-4">
                        <i class="fas fa-toggle-on me-2"></i>Statut du compte
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" name="est_actif" id="est_actif" <?= $secretaire['est_actif'] ? 'checked' : '' ?>>
                                <label class="form-check-label fw-semibold" for="est_actif">
                                    Compte actif
                                </label>
                            </div>
                        </div>
                        <div class="col-md-6 mb-3">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" name="est_bloque" id="est_bloque" <?= $secretaire['est_bloque'] ? 'checked' : '' ?>>
                                <label class="form-check-label fw-semibold" for="est_bloque">
                                    Compte bloqué
                                </label>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-3" id="raison_blocage_div" style="display: <?= $secretaire['est_bloque'] ? 'block' : 'none' ?>;">
                        <label class="form-label fw-semibold">Raison du blocage</label>
                        <textarea class="form-control" name="raison_blocage" rows="2"><?= e($secretaire['raison_blocage']) ?></textarea>
                    </div>
                    
                    <!-- Boutons d'action -->
                    <div class="d-flex gap-3 mt-4">
                        <button type="submit" class="btn btn-submit text-white flex-grow-1">
                            <i class="fas fa-save me-2"></i>Enregistrer les modifications
                        </button>
                        <a href="secretaires.php" class="btn btn-outline-secondary px-4">Annuler</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
    function previewPhoto(input) {
        if (input.files && input.files[0]) {
            const reader = new FileReader();
            reader.onload = function(e) {
                document.getElementById('photoPreview').src = e.target.result;
            };
            reader.readAsDataURL(input.files[0]);
        }
    }
    
    // Afficher/masquer le champ raison de blocage
    document.getElementById('est_bloque').addEventListener('change', function() {
        const div = document.getElementById('raison_blocage_div');
        div.style.display = this.checked ? 'block' : 'none';
    });
</script>

<?php include '../../includes/footer.php'; ?>