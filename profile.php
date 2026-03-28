<?php
/**
 * Profil secrétaire
 */

require_once '../../includes/config.php';
require_once '../../includes/functions.php';
require_once '../../includes/auth.php';

// Vérifier le rôle
require_role('secretaire');

$page_title = "Mon Profil - School-Connection";
$user_id = $_SESSION['user_id'];

// Récupérer les informations
$stmt = $pdo->prepare("
    SELECT u.*, s.service, s.matricule, s.date_embauche
    FROM utilisateurs u
    LEFT JOIN secretaires s ON u.id_utilisateur = s.id_secretaire
    WHERE u.id_utilisateur = ?
");
$stmt->execute([$user_id]);
$user = $stmt->fetch();

$error = '';
$success = '';

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
        
        // Validation
        $errors = [];
        if (empty($nom)) $errors[] = 'Le nom est requis';
        if (empty($prenom)) $errors[] = 'Le prénom est requis';
        
        // Upload de la photo
        $photo_path = $user['photo'];
        if (isset($_FILES['photo']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
            $result = upload_file($_FILES['photo'], UPLOADS_PATH . 'photos/', ALLOWED_IMAGES, 2 * 1024 * 1024);
            if ($result['success']) {
                // Supprimer l'ancienne photo si ce n'est pas la photo par défaut
                if (!empty($user['photo']) && $user['photo'] !== 'default-avatar.png' && file_exists(ROOT_PATH . $user['photo'])) {
                    unlink(ROOT_PATH . $user['photo']);
                }
                $photo_path = 'uploads/photos/' . $result['filename'];
            } else {
                $errors[] = 'Erreur photo : ' . $result['message'];
            }
        }
        
        if (empty($errors)) {
            try {
                // Mettre à jour l'utilisateur
                $stmt = $pdo->prepare("
                    UPDATE utilisateurs 
                    SET nom = ?, prenom = ?, telephone = ?, adresse = ?, photo = ?
                    WHERE id_utilisateur = ?
                ");
                $stmt->execute([$nom, $prenom, $telephone, $adresse, $photo_path, $user_id]);
                
                // Mettre à jour la secrétaire
                if (!empty($service)) {
                    $stmt = $pdo->prepare("
                        UPDATE secretaires 
                        SET service = ?
                        WHERE id_secretaire = ?
                    ");
                    $stmt->execute([$service, $user_id]);
                }
                
                log_action($user_id, 'UPDATE_PROFILE', 'Mise à jour du profil secrétaire', 'modification');
                
                $success = 'Profil mis à jour avec succès';
                
                // Rafraîchir les données
                $stmt = $pdo->prepare("
                    SELECT u.*, s.service, s.matricule, s.date_embauche
                    FROM utilisateurs u
                    LEFT JOIN secretaires s ON u.id_utilisateur = s.id_secretaire
                    WHERE u.id_utilisateur = ?
                ");
                $stmt->execute([$user_id]);
                $user = $stmt->fetch();
                
            } catch (Exception $e) {
                $error = 'Une erreur est survenue lors de la mise à jour';
            }
        } else {
            $error = implode('<br>', $errors);
        }
    }
}

include '../../includes/header.php';
?>

<div class="container-fluid">
    <div class="row mb-4">
        <div class="col-12">
            <h1 class="h3">Mon Profil</h1>
            <p class="text-muted">Gérez vos informations personnelles</p>
        </div>
    </div>
    
    <div class="row">
        <div class="col-md-4">
            <!-- Carte de profil -->
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-body text-center">
                    <img src="<?= getPhotoUrl($user['photo']) ?>" 
                         alt="Photo de profil" 
                         class="rounded-circle img-thumbnail mb-3" 
                         style="width: 150px; height: 150px; object-fit: cover;"
                         id="profilePreview">
                    
                    <h4><?= e($user['prenom'] . ' ' . $user['nom']) ?></h4>
                    <p class="text-primary">
                        <span class="badge bg-warning">Secrétaire</span>
                    </p>
                    
                    <hr>
                    
                    <div class="text-start">
                        <p><i class="fas fa-envelope text-primary me-2"></i> <?= e($user['email']) ?></p>
                        <p><i class="fas fa-phone text-primary me-2"></i> <?= e($user['telephone'] ?? 'Non renseigné') ?></p>
                        <p><i class="fas fa-map-marker-alt text-primary me-2"></i> <?= e($user['adresse'] ?? 'Non renseignée') ?></p>
                        <p><i class="fas fa-calendar text-primary me-2"></i> Membre depuis le <?= format_date($user['date_creation']) ?></p>
                        <?php if ($user['service']): ?>
                            <p><i class="fas fa-building text-primary me-2"></i> Service: <?= e($user['service']) ?></p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-md-8">
            <!-- Formulaire de modification -->
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white">
                    <h6 class="card-title mb-0">Modifier mes informations</h6>
                </div>
                <div class="card-body">
                    <?php if ($error): ?>
                        <div class="alert alert-danger"><?= $error ?></div>
                    <?php endif; ?>
                    
                    <?php if ($success): ?>
                        <div class="alert alert-success"><?= $success ?></div>
                    <?php endif; ?>
                    
                    <form method="POST" action="" enctype="multipart/form-data">
                        <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
                        
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label for="nom" class="form-label">Nom</label>
                                <input type="text" class="form-control" id="nom" name="nom" 
                                       value="<?= e($user['nom']) ?>" required>
                            </div>
                            <div class="col-md-6">
                                <label for="prenom" class="form-label">Prénom</label>
                                <input type="text" class="form-control" id="prenom" name="prenom" 
                                       value="<?= e($user['prenom']) ?>" required>
                            </div>
                            <div class="col-md-6">
                                <label for="telephone" class="form-label">Téléphone</label>
                                <input type="tel" class="form-control" id="telephone" name="telephone" 
                                       value="<?= e($user['telephone'] ?? '') ?>">
                            </div>
                            <div class="col-md-6">
                                <label for="service" class="form-label">Service</label>
                                <input type="text" class="form-control" id="service" name="service" 
                                       value="<?= e($user['service'] ?? '') ?>">
                            </div>
                            <div class="col-md-6">
                                <label for="photo" class="form-label">Photo de profil</label>
                                <input type="file" class="form-control" id="photo" name="photo" accept="image/*">
                                <small class="text-muted">Laissez vide pour conserver la photo actuelle</small>
                            </div>
                            <div class="col-12">
                                <label for="adresse" class="form-label">Adresse</label>
                                <textarea class="form-control" id="adresse" name="adresse" rows="3"><?= e($user['adresse'] ?? '') ?></textarea>
                            </div>
                            <div class="col-12">
                                <hr>
                                <p class="text-muted small">
                                    <i class="fas fa-info-circle"></i> 
                                    Pour des raisons de sécurité, l'email ne peut pas être modifié.
                                    Pour changer votre mot de passe, 
                                    <a href="change-password.php" class="text-primary">cliquez ici</a>.
                                </p>
                            </div>
                            <div class="col-12 text-end">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-save"></i> Enregistrer les modifications
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Aperçu de la photo
document.getElementById('photo').addEventListener('change', function(e) {
    const file = e.target.files[0];
    if (file) {
        const reader = new FileReader();
        reader.onload = function(e) {
            document.getElementById('profilePreview').src = e.target.result;
        };
        reader.readAsDataURL(file);
    }
});
</script>

<?php include '../../includes/footer.php'; ?>