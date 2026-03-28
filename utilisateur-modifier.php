<?php
/**
 * Modifier un utilisateur (Admin)
 */

require_once '../../includes/config.php';
require_once '../../includes/functions.php';
require_once '../../includes/auth.php';

// Vérifier le rôle
require_role('admin');

$page_title = "Modifier un utilisateur - School-Connection";
$id = (int)($_GET['id'] ?? 0);

if (!$id) {
    $_SESSION['flash']['danger'] = "ID utilisateur manquant";
    redirect('utilisateurs.php');
}

// Récupérer les informations de l'utilisateur
$stmt = $pdo->prepare("
    SELECT u.*, 
           s.filiere, s.niveau_etude, s.etablissement, s.theme_stage, s.date_debut, s.date_fin, s.statut_inscription,
           e.profession, e.specialite, e.entreprise, e.bio, e.disponible, e.max_stagiaires,
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

// Récupérer les filières
$filieres = $pdo->query("SELECT * FROM filieres WHERE actif = 1 ORDER BY nom_filiere")->fetchAll();

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
        $est_actif = isset($_POST['est_actif']) ? 1 : 0;
        $est_bloque = isset($_POST['est_bloque']) ? 1 : 0;
        $raison_blocage = cleanInput($_POST['raison_blocage'] ?? '');
        
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
        
        // Validation selon le rôle
        switch ($user['role']) {
            case 'stagiaire':
                $filiere = cleanInput($_POST['filiere'] ?? '');
                $niveau_etude = $_POST['niveau_etude'] ?? '';
                $etablissement = cleanInput($_POST['etablissement'] ?? '');
                $theme_stage = cleanInput($_POST['theme_stage'] ?? '');
                $date_debut = $_POST['date_debut'] ?? '';
                $date_fin = $_POST['date_fin'] ?? '';
                $statut_inscription = $_POST['statut_inscription'] ?? '';
                
                if (empty($filiere)) $errors[] = 'La filière est requise';
                if (empty($niveau_etude)) $errors[] = 'Le niveau d\'étude est requis';
                if (empty($theme_stage)) $errors[] = 'Le thème du stage est requis';
                if (empty($date_debut)) $errors[] = 'La date de début est requise';
                if (empty($date_fin)) $errors[] = 'La date de fin est requise';
                break;
                
            case 'encadreur_pro':
            case 'encadreur_acro':
                $profession = cleanInput($_POST['profession'] ?? '');
                $specialite = cleanInput($_POST['specialite'] ?? '');
                $entreprise = cleanInput($_POST['entreprise'] ?? '');
                $bio = cleanInput($_POST['bio'] ?? '');
                $disponible = isset($_POST['disponible']) ? 1 : 0;
                $max_stagiaires = (int)($_POST['max_stagiaires'] ?? 5);
                
                if (empty($profession)) $errors[] = 'La profession est requise';
                if (empty($specialite)) $errors[] = 'La spécialité est requise';
                if (empty($entreprise)) $errors[] = 'L\'entreprise est requise';
                break;
                
            case 'secretaire':
                $service = cleanInput($_POST['service'] ?? '');
                $matricule = cleanInput($_POST['matricule'] ?? '');
                break;
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
                
                // Mettre à jour les informations spécifiques
                switch ($user['role']) {
                    case 'stagiaire':
                        $stmt = $pdo->prepare("
                            UPDATE stagiaires 
                            SET filiere = ?, niveau_etude = ?, etablissement = ?, 
                                theme_stage = ?, date_debut = ?, date_fin = ?, statut_inscription = ?
                            WHERE id_stagiaire = ?
                        ");
                        $stmt->execute([$filiere, $niveau_etude, $etablissement, $theme_stage, 
                                       $date_debut, $date_fin, $statut_inscription, $id]);
                        break;
                        
                    case 'encadreur_pro':
                    case 'encadreur_acro':
                        $stmt = $pdo->prepare("
                            UPDATE encadreurs 
                            SET profession = ?, specialite = ?, entreprise = ?, bio = ?,
                                disponible = ?, max_stagiaires = ?
                            WHERE id_encadreur = ?
                        ");
                        $stmt->execute([$profession, $specialite, $entreprise, $bio,
                                       $disponible, $max_stagiaires, $id]);
                        break;
                        
                    case 'secretaire':
                        $stmt = $pdo->prepare("
                            UPDATE secretaires 
                            SET service = ?, matricule = ?
                            WHERE id_secretaire = ?
                        ");
                        $stmt->execute([$service, $matricule, $id]);
                        break;
                }
                
                // Journaliser l'action
                log_action($_SESSION['user_id'], 'UPDATE_USER', "Modification de l'utilisateur ID: $id", 'modification', 'utilisateurs', $id);
                
                $pdo->commit();
                
                $_SESSION['flash']['success'] = "L'utilisateur a été modifié avec succès";
                redirect("utilisateur-voir.php?id=$id");
                
            } catch (Exception $e) {
                $pdo->rollBack();
                $error = "Erreur lors de la modification : " . $e->getMessage();
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
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h1 class="h3">Modifier un utilisateur</h1>
                    <p class="text-muted"><?= e($user['prenom'] . ' ' . $user['nom']) ?></p>
                </div>
                <a href="utilisateur-voir.php?id=<?= $id ?>" class="btn btn-outline-secondary">
                    <i class="fas fa-arrow-left"></i> Retour
                </a>
            </div>
        </div>
    </div>
    
    <div class="row">
        <div class="col-md-8 mx-auto">
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <?php if ($error): ?>
                        <div class="alert alert-danger"><?= $error ?></div>
                    <?php endif; ?>
                    
                    <form method="POST" action="" enctype="multipart/form-data">
                        <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
                        
                        <!-- Photo actuelle -->
                        <div class="text-center mb-4">
                            <img src="<?= getPhotoUrl($user['photo']) ?>" 
                                 alt="Photo de profil" 
                                 class="rounded-circle img-thumbnail mb-2" 
                                 style="width: 100px; height: 100px; object-fit: cover;"
                                 id="photoPreview">
                            
                            <div>
                                <label for="photo" class="btn btn-outline-primary btn-sm">
                                    <i class="fas fa-camera"></i> Changer la photo
                                </label>
                                <input type="file" id="photo" name="photo" class="d-none" accept="image/*" data-preview="photoPreview">
                            </div>
                        </div>
                        
                        <!-- Informations de base -->
                        <h5 class="mb-3">Informations personnelles</h5>
                        <div class="row g-3 mb-4">
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
                            <div class="col-12">
                                <label for="adresse" class="form-label">Adresse</label>
                                <textarea class="form-control" id="adresse" name="adresse" rows="2"><?= e($user['adresse'] ?? '') ?></textarea>
                            </div>
                        </div>
                        
                        <!-- Statut du compte -->
                        <h5 class="mb-3">Statut du compte</h5>
                        <div class="row g-3 mb-4">
                            <div class="col-md-4">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="est_actif" name="est_actif" 
                                           <?= $user['est_actif'] ? 'checked' : '' ?>>
                                    <label class="form-check-label" for="est_actif">
                                        Compte actif
                                    </label>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="est_bloque" name="est_bloque" 
                                           <?= $user['est_bloque'] ? 'checked' : '' ?>>
                                    <label class="form-check-label" for="est_bloque">
                                        Bloquer le compte
                                    </label>
                                </div>
                            </div>
                        </div>
                        
                        <div class="row g-3 mb-4" id="blocage_div" style="<?= !$user['est_bloque'] ? 'display:none;' : '' ?>">
                            <div class="col-12">
                                <label for="raison_blocage" class="form-label">Raison du blocage</label>
                                <textarea class="form-control" id="raison_blocage" name="raison_blocage" rows="2"><?= e($user['raison_blocage'] ?? '') ?></textarea>
                            </div>
                        </div>
                        
                        <!-- Informations spécifiques -->
                        <?php if ($user['role'] === 'stagiaire'): ?>
                            <h5 class="mb-3">Informations de stage</h5>
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label for="filiere" class="form-label">Filière</label>
                                    <select class="form-select" id="filiere" name="filiere" required>
                                        <option value="">Sélectionnez</option>
                                        <?php foreach ($filieres as $f): ?>
                                            <option value="<?= e($f['nom_filiere']) ?>" <?= $user['filiere'] === $f['nom_filiere'] ? 'selected' : '' ?>>
                                                <?= e($f['nom_filiere']) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label for="niveau_etude" class="form-label">Niveau d'étude</label>
                                    <select class="form-select" id="niveau_etude" name="niveau_etude" required>
                                        <option value="">Sélectionnez</option>
                                        <option value="Licence1" <?= $user['niveau_etude'] === 'Licence1' ? 'selected' : '' ?>>Licence 1</option>
                                        <option value="Licence2" <?= $user['niveau_etude'] === 'Licence2' ? 'selected' : '' ?>>Licence 2</option>
                                        <option value="Licence3" <?= $user['niveau_etude'] === 'Licence3' ? 'selected' : '' ?>>Licence 3</option>
                                        <option value="Master1" <?= $user['niveau_etude'] === 'Master1' ? 'selected' : '' ?>>Master 1</option>
                                        <option value="Master2" <?= $user['niveau_etude'] === 'Master2' ? 'selected' : '' ?>>Master 2</option>
                                        <option value="Doctorat" <?= $user['niveau_etude'] === 'Doctorat' ? 'selected' : '' ?>>Doctorat</option>
                                        <option value="BTS" <?= $user['niveau_etude'] === 'BTS' ? 'selected' : '' ?>>BTS</option>
                                        <option value="DUT" <?= $user['niveau_etude'] === 'DUT' ? 'selected' : '' ?>>DUT</option>
                                    </select>
                                </div>
                                <div class="col-12">
                                    <label for="etablissement" class="form-label">Établissement</label>
                                    <input type="text" class="form-control" id="etablissement" name="etablissement" 
                                           value="<?= e($user['etablissement'] ?? '') ?>">
                                </div>
                                <div class="col-12">
                                    <label for="theme_stage" class="form-label">Thème du stage</label>
                                    <textarea class="form-control" id="theme_stage" name="theme_stage" rows="3" required><?= e($user['theme_stage'] ?? '') ?></textarea>
                                </div>
                                <div class="col-md-6">
                                    <label for="date_debut" class="form-label">Date de début</label>
                                    <input type="date" class="form-control" id="date_debut" name="date_debut" 
                                           value="<?= $user['date_debut'] ?>" required>
                                </div>
                                <div class="col-md-6">
                                    <label for="date_fin" class="form-label">Date de fin</label>
                                    <input type="date" class="form-control" id="date_fin" name="date_fin" 
                                           value="<?= $user['date_fin'] ?>" required>
                                </div>
                                <div class="col-md-6">
                                    <label for="statut_inscription" class="form-label">Statut inscription</label>
                                    <select class="form-select" id="statut_inscription" name="statut_inscription" required>
                                        <option value="en_attente" <?= $user['statut_inscription'] === 'en_attente' ? 'selected' : '' ?>>En attente</option>
                                        <option value="actif" <?= $user['statut_inscription'] === 'actif' ? 'selected' : '' ?>>Actif</option>
                                        <option value="termine" <?= $user['statut_inscription'] === 'termine' ? 'selected' : '' ?>>Terminé</option>
                                        <option value="abandon" <?= $user['statut_inscription'] === 'abandon' ? 'selected' : '' ?>>Abandon</option>
                                        <option value="suspendu" <?= $user['statut_inscription'] === 'suspendu' ? 'selected' : '' ?>>Suspendu</option>
                                    </select>
                                </div>
                            </div>
                            
                        <?php elseif ($user['role'] === 'encadreur_pro' || $user['role'] === 'encadreur_acro'): ?>
                            <h5 class="mb-3">Informations professionnelles</h5>
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label for="profession" class="form-label">Profession</label>
                                    <input type="text" class="form-control" id="profession" name="profession" 
                                           value="<?= e($user['profession'] ?? '') ?>" required>
                                </div>
                                <div class="col-md-6">
                                    <label for="specialite" class="form-label">Spécialité</label>
                                    <input type="text" class="form-control" id="specialite" name="specialite" 
                                           value="<?= e($user['specialite'] ?? '') ?>" required>
                                </div>
                                <div class="col-md-6">
                                    <label for="entreprise" class="form-label">Entreprise</label>
                                    <input type="text" class="form-control" id="entreprise" name="entreprise" 
                                           value="<?= e($user['entreprise'] ?? '') ?>" required>
                                </div>
                                <div class="col-md-3">
                                    <label for="max_stagiaires" class="form-label">Max stagiaires</label>
                                    <input type="number" class="form-control" id="max_stagiaires" name="max_stagiaires" 
                                           value="<?= $user['max_stagiaires'] ?? 5 ?>" min="1" max="20">
                                </div>
                                <div class="col-md-3">
                                    <div class="form-check mt-4">
                                        <input class="form-check-input" type="checkbox" id="disponible" name="disponible" 
                                               <?= $user['disponible'] ? 'checked' : '' ?>>
                                        <label class="form-check-label" for="disponible">
                                            Disponible
                                        </label>
                                    </div>
                                </div>
                                <div class="col-12">
                                    <label for="bio" class="form-label">Biographie</label>
                                    <textarea class="form-control summernote" id="bio" name="bio" rows="5"><?= e($user['bio'] ?? '') ?></textarea>
                                </div>
                            </div>
                            
                        <?php elseif ($user['role'] === 'secretaire'): ?>
                            <h5 class="mb-3">Informations secrétariat</h5>
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label for="service" class="form-label">Service</label>
                                    <input type="text" class="form-control" id="service" name="service" 
                                           value="<?= e($user['service'] ?? '') ?>">
                                </div>
                                <div class="col-md-6">
                                    <label for="matricule" class="form-label">Matricule</label>
                                    <input type="text" class="form-control" id="matricule" name="matricule" 
                                           value="<?= e($user['matricule'] ?? '') ?>">
                                </div>
                            </div>
                        <?php endif; ?>
                        
                        <hr class="my-4">
                        
                        <div class="d-flex justify-content-between">
                            <a href="utilisateur-voir.php?id=<?= $id ?>" class="btn btn-outline-secondary">
                                <i class="fas fa-times"></i> Annuler
                            </a>
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save"></i> Enregistrer les modifications
                            </button>
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
            document.getElementById('photoPreview').src = e.target.result;
        };
        reader.readAsDataURL(file);
    }
});

// Afficher/masquer raison du blocage
document.getElementById('est_bloque').addEventListener('change', function() {
    document.getElementById('blocage_div').style.display = this.checked ? 'block' : 'none';
});
</script>

<?php include '../../includes/footer.php'; ?>