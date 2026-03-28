<?php
/**
 * Modifier un encadreur (Admin)
 * Avec possibilité d'assigner des stagiaires
 */

require_once '../../includes/config.php';
require_once '../../includes/functions.php';
require_once '../../includes/auth.php';

// Vérifier le rôle
require_role('admin');

$page_title = "Modifier un encadreur - School-Connection";
$id = (int)($_GET['id'] ?? 0);

if (!$id) {
    $_SESSION['flash']['danger'] = "ID encadreur manquant";
    redirect('encadreurs.php');
}

// Récupérer les informations de l'encadreur
$stmt = $pdo->prepare("
    SELECT u.*, e.*
    FROM utilisateurs u
    JOIN encadreurs e ON u.id_utilisateur = e.id_encadreur
    WHERE u.id_utilisateur = ?
");
$stmt->execute([$id]);
$encadreur = $stmt->fetch();

if (!$encadreur) {
    $_SESSION['flash']['danger'] = "Encadreur non trouvé";
    redirect('encadreurs.php');
}

// Vérifier les colonnes de la table stagiaires
$colonnesStagiaires = [];
try {
    $stmt = $pdo->query("SHOW COLUMNS FROM stagiaires");
    $colonnesStagiaires = $stmt->fetchAll(PDO::FETCH_COLUMN);
} catch (Exception $e) {
    $colonnesStagiaires = [];
}

$has_encadreur_column = in_array('id_encadreur', $colonnesStagiaires);

// Récupérer la liste des stagiaires non assignés (si la colonne existe)
$stagiaires_non_assignes = [];
if ($has_encadreur_column) {
    $stmt = $pdo->prepare("
        SELECT s.id_stagiaire, u.nom, u.prenom, s.filiere, s.niveau_etude
        FROM stagiaires s
        JOIN utilisateurs u ON s.id_stagiaire = u.id_utilisateur
        WHERE (s.id_encadreur IS NULL OR s.id_encadreur = 0 OR s.id_encadreur = '')
        ORDER BY u.nom, u.prenom
    ");
    $stmt->execute();
    $stagiaires_non_assignes = $stmt->fetchAll();
}

// Récupérer la liste des stagiaires déjà assignés à cet encadreur (si la colonne existe)
$stagiaires_assignes = [];
if ($has_encadreur_column) {
    $stmt = $pdo->prepare("
        SELECT s.id_stagiaire, u.nom, u.prenom, s.filiere, s.niveau_etude, s.date_debut, s.date_fin
        FROM stagiaires s
        JOIN utilisateurs u ON s.id_stagiaire = u.id_utilisateur
        WHERE s.id_encadreur = ?
        ORDER BY u.nom, u.prenom
    ");
    $stmt->execute([$id]);
    $stagiaires_assignes = $stmt->fetchAll();
}

$error = '';
$success = '';

// Traitement de l'assignation (uniquement si la colonne existe)
if ($has_encadreur_column && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['assigner_stagiaire'])) {
        $stagiaire_id = (int)($_POST['stagiaire_id'] ?? 0);
        
        if ($stagiaire_id) {
            try {
                // Vérifier si l'encadreur peut encore prendre des stagiaires
                $nb_stagiaires = count($stagiaires_assignes);
                if ($nb_stagiaires >= $encadreur['max_stagiaires']) {
                    $error = "Cet encadreur a déjà atteint son nombre maximum de stagiaires ({$encadreur['max_stagiaires']})";
                } else {
                    $stmt = $pdo->prepare("UPDATE stagiaires SET id_encadreur = ? WHERE id_stagiaire = ?");
                    $stmt->execute([$id, $stagiaire_id]);
                    
                    // Récupérer le nom du stagiaire pour la notification
                    $stmt2 = $pdo->prepare("SELECT nom, prenom FROM utilisateurs WHERE id_utilisateur = ?");
                    $stmt2->execute([$stagiaire_id]);
                    $stag = $stmt2->fetch();
                    
                    // Créer une notification pour le stagiaire
                    if (function_exists('create_notification')) {
                        create_notification(
                            $stagiaire_id,
                            "📋 Encadreur assigné",
                            "Vous avez été assigné à l'encadreur " . $encadreur['prenom'] . " " . $encadreur['nom'],
                            'success',
                            'dashboard/stagiaire/encadreur.php'
                        );
                    }
                    
                    $success = "Stagiaire assigné avec succès !";
                    
                    // Rafraîchir les listes
                    $stmt = $pdo->prepare("
                        SELECT s.id_stagiaire, u.nom, u.prenom, s.filiere, s.niveau_etude
                        FROM stagiaires s
                        JOIN utilisateurs u ON s.id_stagiaire = u.id_utilisateur
                        WHERE s.id_encadreur IS NULL OR s.id_encadreur = 0 OR s.id_encadreur = ''
                        ORDER BY u.nom, u.prenom
                    ");
                    $stmt->execute();
                    $stagiaires_non_assignes = $stmt->fetchAll();
                    
                    $stmt = $pdo->prepare("
                        SELECT s.id_stagiaire, u.nom, u.prenom, s.filiere, s.niveau_etude, s.date_debut, s.date_fin
                        FROM stagiaires s
                        JOIN utilisateurs u ON s.id_stagiaire = u.id_utilisateur
                        WHERE s.id_encadreur = ?
                        ORDER BY u.nom, u.prenom
                    ");
                    $stmt->execute([$id]);
                    $stagiaires_assignes = $stmt->fetchAll();
                }
            } catch (Exception $e) {
                $error = "Erreur lors de l'assignation : " . $e->getMessage();
            }
        } else {
            $error = "Veuillez sélectionner un stagiaire";
        }
    }
    
    // Traitement du désassignement
    if (isset($_POST['desassigner_stagiaire'])) {
        $stagiaire_id = (int)($_POST['stagiaire_id'] ?? 0);
        
        if ($stagiaire_id) {
            try {
                $stmt = $pdo->prepare("UPDATE stagiaires SET id_encadreur = NULL WHERE id_stagiaire = ? AND id_encadreur = ?");
                $stmt->execute([$stagiaire_id, $id]);
                
                // Récupérer le nom du stagiaire pour la notification
                $stmt2 = $pdo->prepare("SELECT nom, prenom FROM utilisateurs WHERE id_utilisateur = ?");
                $stmt2->execute([$stagiaire_id]);
                $stag = $stmt2->fetch();
                
                // Créer une notification pour le stagiaire
                if (function_exists('create_notification')) {
                    create_notification(
                        $stagiaire_id,
                        "⚠️ Changement d'encadreur",
                        "Vous n'êtes plus assigné à l'encadreur " . $encadreur['prenom'] . " " . $encadreur['nom'],
                        'warning',
                        'dashboard/stagiaire/encadreur.php'
                    );
                }
                
                $success = "Stagiaire désassigné avec succès !";
                
                // Rafraîchir les listes
                $stmt = $pdo->prepare("
                    SELECT s.id_stagiaire, u.nom, u.prenom, s.filiere, s.niveau_etude
                    FROM stagiaires s
                    JOIN utilisateurs u ON s.id_stagiaire = u.id_utilisateur
                    WHERE s.id_encadreur IS NULL OR s.id_encadreur = 0 OR s.id_encadreur = ''
                    ORDER BY u.nom, u.prenom
                ");
                $stmt->execute();
                $stagiaires_non_assignes = $stmt->fetchAll();
                
                $stmt = $pdo->prepare("
                    SELECT s.id_stagiaire, u.nom, u.prenom, s.filiere, s.niveau_etude, s.date_debut, s.date_fin
                    FROM stagiaires s
                    JOIN utilisateurs u ON s.id_stagiaire = u.id_utilisateur
                    WHERE s.id_encadreur = ?
                    ORDER BY u.nom, u.prenom
                ");
                $stmt->execute([$id]);
                $stagiaires_assignes = $stmt->fetchAll();
            } catch (Exception $e) {
                $error = "Erreur lors du désassignement : " . $e->getMessage();
            }
        }
    }
    
    // Traitement de la modification des informations de l'encadreur
    if (isset($_POST['save_encadreur'])) {
        // Vérifier le token CSRF
        if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
            $error = 'Token de sécurité invalide';
        } else {
            // Informations de base
            $nom = cleanInput($_POST['nom'] ?? '');
            $prenom = cleanInput($_POST['prenom'] ?? '');
            $telephone = cleanInput($_POST['telephone'] ?? '');
            $adresse = cleanInput($_POST['adresse'] ?? '');
            
            // Informations professionnelles
            $date_naissance = $_POST['date_naissance'] ?? '';
            $profession = cleanInput($_POST['profession'] ?? '');
            $specialite = cleanInput($_POST['specialite'] ?? '');
            $entreprise = cleanInput($_POST['entreprise'] ?? '');
            $poste = cleanInput($_POST['poste'] ?? '');
            $annees_experience = (int)($_POST['annees_experience'] ?? 0);
            $diplome = cleanInput($_POST['diplome'] ?? '');
            $certifications = cleanInput($_POST['certifications'] ?? '');
            $bio = cleanInput($_POST['bio'] ?? '');
            $max_stagiaires = (int)($_POST['max_stagiaires'] ?? 5);
            $disponible = isset($_POST['disponible']) ? 1 : 0;
            
            // Statut du compte
            $est_actif = isset($_POST['est_actif']) ? 1 : 0;
            $est_bloque = isset($_POST['est_bloque']) ? 1 : 0;
            $raison_blocage = cleanInput($_POST['raison_blocage'] ?? '');
            
            // Validation
            $errors = [];
            
            if (empty($nom)) $errors[] = 'Le nom est requis';
            if (empty($prenom)) $errors[] = 'Le prénom est requis';
            if (empty($profession)) $errors[] = 'La profession est requise';
            if (empty($specialite)) $errors[] = 'La spécialité est requise';
            if (empty($entreprise)) $errors[] = 'L\'entreprise est requise';
            
            // Validation de la date de naissance
            if (!empty($date_naissance)) {
                $date_obj = DateTime::createFromFormat('Y-m-d', $date_naissance);
                if (!$date_obj || $date_obj->format('Y-m-d') !== $date_naissance) {
                    $errors[] = 'Format de date de naissance invalide';
                } else {
                    $age = date_diff($date_obj, new DateTime())->y;
                    if ($age < 18) {
                        $errors[] = 'L\'encadreur doit avoir au moins 18 ans';
                    }
                    if ($age > 100) {
                        $errors[] = 'L\'âge ne peut pas dépasser 100 ans';
                    }
                }
            }
            
            // Validation des années d'expérience
            if ($annees_experience < 0) $errors[] = 'Les années d\'expérience ne peuvent pas être négatives';
            if ($annees_experience > 50) $errors[] = 'Les années d\'expérience ne peuvent pas dépasser 50';
            
            // Upload de la photo
            $photo_path = $encadreur['photo'];
            if (isset($_FILES['photo']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
                $result = upload_file($_FILES['photo'], UPLOADS_PATH . 'photos/', ALLOWED_IMAGES, 2 * 1024 * 1024);
                if ($result['success']) {
                    if (!empty($encadreur['photo']) && $encadreur['photo'] !== 'default-avatar.png' && file_exists(ROOT_PATH . $encadreur['photo'])) {
                        unlink(ROOT_PATH . $encadreur['photo']);
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
                    
                    // Mettre à jour l'encadreur
                    $stmt = $pdo->prepare("
                        UPDATE encadreurs 
                        SET date_naissance = ?, profession = ?, specialite = ?, entreprise = ?, poste = ?,
                            annees_experience = ?, diplome = ?, certifications = ?, bio = ?,
                            disponible = ?, max_stagiaires = ?
                        WHERE id_encadreur = ?
                    ");
                    $stmt->execute([
                        $date_naissance,
                        $profession, 
                        $specialite, 
                        $entreprise, 
                        $poste,
                        $annees_experience,
                        $diplome,
                        $certifications,
                        $bio,
                        $disponible, 
                        $max_stagiaires, 
                        $id
                    ]);
                    
                    // Journaliser l'action
                    log_action($_SESSION['user_id'], 'UPDATE_ENCADREUR', "Modification de l'encadreur ID: $id", 'modification', 'encadreurs', $id);
                    
                    $pdo->commit();
                    
                    $_SESSION['flash']['success'] = "L'encadreur a été modifié avec succès";
                    redirect("encadreur-voir.php?id=$id");
                    
                } catch (Exception $e) {
                    $pdo->rollBack();
                    $error = "Erreur lors de la modification : " . $e->getMessage();
                }
            } else {
                $error = implode('<br>', $errors);
            }
        }
    }
}

include '../../includes/header.php';
?>

<style>
    .form-section {
        background: #f8f9fa;
        border-radius: 15px;
        padding: 20px;
        margin-bottom: 25px;
    }
    
    .form-section h5 {
        color: #667eea;
        margin-bottom: 20px;
        font-weight: 600;
        border-left: 4px solid #667eea;
        padding-left: 15px;
    }
    
    .required:after {
        content: " *";
        color: red;
    }
    
    .help-text {
        font-size: 0.8rem;
        color: #6c757d;
        margin-top: 5px;
    }
    
    .stagiaire-card {
        background: #f8f9fa;
        border-radius: 12px;
        padding: 15px;
        margin-bottom: 10px;
        transition: all 0.3s;
    }
    
    .stagiaire-card:hover {
        background: #e9ecef;
        transform: translateX(5px);
    }
    
    .badge-capacity {
        background: #e9ecef;
        padding: 5px 10px;
        border-radius: 50px;
        font-size: 0.8rem;
    }
    
    .list-group-item {
        border: none;
        border-radius: 10px !important;
        margin-bottom: 5px;
    }
    
    .btn-sm-custom {
        padding: 5px 15px;
        border-radius: 50px;
        font-size: 0.8rem;
    }
    
    .warning-box {
        background: #fff3cd;
        border: 1px solid #ffc107;
        border-radius: 10px;
        padding: 15px;
        margin-bottom: 20px;
    }
</style>

<div class="container-fluid">
    <div class="row mb-4">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h1 class="h3">
                        <i class="fas fa-edit me-2"></i>Modifier un encadreur
                    </h1>
                    <p class="text-muted"><?= e($encadreur['prenom'] . ' ' . $encadreur['nom']) ?></p>
                </div>
                <a href="encadreur-voir.php?id=<?= $id ?>" class="btn btn-outline-secondary">
                    <i class="fas fa-arrow-left"></i> Retour
                </a>
            </div>
        </div>
    </div>
    
    <div class="row">
        <div class="col-md-8 mx-auto">
            <!-- Formulaire de modification de l'encadreur -->
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-body">
                    <?php if ($error): ?>
                        <div class="alert alert-danger alert-dismissible fade show">
                            <i class="fas fa-exclamation-circle me-2"></i><?= $error ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>
                    
                    <?php if ($success): ?>
                        <div class="alert alert-success alert-dismissible fade show">
                            <i class="fas fa-check-circle me-2"></i><?= $success ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>
                    
                    <form method="POST" action="" enctype="multipart/form-data">
                        <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
                        <input type="hidden" name="save_encadreur" value="1">
                        
                        <!-- Photo -->
                        <div class="text-center mb-4">
                            <img src="<?= getPhotoUrl($encadreur['photo']) ?>" 
                                 alt="Photo" class="rounded-circle img-thumbnail mb-2" 
                                 style="width: 120px; height: 120px; object-fit: cover;"
                                 id="photoPreview">
                            <div>
                                <label for="photo" class="btn btn-outline-primary btn-sm">
                                    <i class="fas fa-camera"></i> Changer la photo
                                </label>
                                <input type="file" id="photo" name="photo" class="d-none" accept="image/*" data-preview="photoPreview">
                            </div>
                        </div>
                        
                        <!-- Informations personnelles -->
                        <div class="form-section">
                            <h5><i class="fas fa-user me-2"></i>Informations personnelles</h5>
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label for="nom" class="form-label required">Nom</label>
                                    <input type="text" class="form-control" id="nom" name="nom" 
                                           value="<?= e($encadreur['nom']) ?>" required>
                                </div>
                                <div class="col-md-6">
                                    <label for="prenom" class="form-label required">Prénom</label>
                                    <input type="text" class="form-control" id="prenom" name="prenom" 
                                           value="<?= e($encadreur['prenom']) ?>" required>
                                </div>
                                <div class="col-md-6">
                                    <label for="email" class="form-label">Email</label>
                                    <input type="email" class="form-control" id="email" 
                                           value="<?= e($encadreur['email']) ?>" readonly disabled>
                                </div>
                                <div class="col-md-6">
                                    <label for="telephone" class="form-label">Téléphone</label>
                                    <input type="tel" class="form-control" id="telephone" name="telephone" 
                                           value="<?= e($encadreur['telephone'] ?? '') ?>">
                                </div>
                                <div class="col-12">
                                    <label for="adresse" class="form-label">Adresse</label>
                                    <textarea class="form-control" id="adresse" name="adresse" rows="2"><?= e($encadreur['adresse'] ?? '') ?></textarea>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Informations professionnelles -->
                        <div class="form-section">
                            <h5><i class="fas fa-briefcase me-2"></i>Informations professionnelles</h5>
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label for="date_naissance" class="form-label">Date de naissance</label>
                                    <input type="date" class="form-control" id="date_naissance" name="date_naissance" 
                                           value="<?= e($encadreur['date_naissance'] ?? '') ?>" 
                                           min="1920-01-01" max="<?= date('Y-m-d', strtotime('-18 years')) ?>">
                                </div>
                                <div class="col-md-6">
                                    <label for="profession" class="form-label required">Profession</label>
                                    <input type="text" class="form-control" id="profession" name="profession" 
                                           value="<?= e($encadreur['profession']) ?>" required>
                                </div>
                                <div class="col-md-6">
                                    <label for="specialite" class="form-label required">Spécialité</label>
                                    <input type="text" class="form-control" id="specialite" name="specialite" 
                                           value="<?= e($encadreur['specialite']) ?>" required>
                                </div>
                                <div class="col-md-6">
                                    <label for="entreprise" class="form-label required">Entreprise / Institution</label>
                                    <input type="text" class="form-control" id="entreprise" name="entreprise" 
                                           value="<?= e($encadreur['entreprise']) ?>" required>
                                </div>
                                <div class="col-md-6">
                                    <label for="poste" class="form-label">Poste occupé</label>
                                    <input type="text" class="form-control" id="poste" name="poste" 
                                           value="<?= e($encadreur['poste'] ?? '') ?>">
                                </div>
                                <div class="col-md-6">
                                    <label for="annees_experience" class="form-label">Années d'expérience</label>
                                    <div class="input-group">
                                        <input type="number" class="form-control" id="annees_experience" name="annees_experience" 
                                               value="<?= (int)($encadreur['annees_experience'] ?? 0) ?>" min="0" max="50" step="1">
                                        <span class="input-group-text">ans</span>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <label for="diplome" class="form-label">Diplôme</label>
                                    <input type="text" class="form-control" id="diplome" name="diplome" 
                                           value="<?= e($encadreur['diplome'] ?? '') ?>">
                                </div>
                                <div class="col-12">
                                    <label for="certifications" class="form-label">Certifications</label>
                                    <textarea class="form-control" id="certifications" name="certifications" rows="3"><?= e($encadreur['certifications'] ?? '') ?></textarea>
                                </div>
                                <div class="col-12">
                                    <label for="bio" class="form-label">Biographie</label>
                                    <textarea class="form-control" id="bio" name="bio" rows="5"><?= e($encadreur['bio'] ?? '') ?></textarea>
                                </div>
                                <div class="col-md-4">
                                    <label for="max_stagiaires" class="form-label">Max stagiaires</label>
                                    <input type="number" class="form-control" id="max_stagiaires" name="max_stagiaires" 
                                           value="<?= $encadreur['max_stagiaires'] ?>" min="1" max="50">
                                </div>
                                <div class="col-md-4">
                                    <div class="form-check mt-4">
                                        <input class="form-check-input" type="checkbox" id="disponible" name="disponible" 
                                               <?= $encadreur['disponible'] ? 'checked' : '' ?>>
                                        <label class="form-check-label" for="disponible">Disponible</label>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Statut du compte -->
                        <div class="form-section">
                            <h5><i class="fas fa-shield-alt me-2"></i>Statut du compte</h5>
                            <div class="row g-3">
                                <div class="col-md-4">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" id="est_actif" name="est_actif" 
                                               <?= $encadreur['est_actif'] ? 'checked' : '' ?>>
                                        <label class="form-check-label" for="est_actif">Compte actif</label>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" id="est_bloque" name="est_bloque" 
                                               <?= $encadreur['est_bloque'] ? 'checked' : '' ?>>
                                        <label class="form-check-label" for="est_bloque">Bloquer le compte</label>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="row g-3 mt-2" id="blocage_div" style="<?= !$encadreur['est_bloque'] ? 'display:none;' : '' ?>">
                                <div class="col-12">
                                    <label for="raison_blocage" class="form-label">Raison du blocage</label>
                                    <textarea class="form-control" id="raison_blocage" name="raison_blocage" rows="2"><?= e($encadreur['raison_blocage'] ?? '') ?></textarea>
                                </div>
                            </div>
                        </div>
                        
                        <div class="d-flex justify-content-between mt-4">
                            <a href="encadreur-voir.php?id=<?= $id ?>" class="btn btn-outline-secondary">
                                <i class="fas fa-times"></i> Annuler
                            </a>
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save"></i> Enregistrer les modifications
                            </button>
                        </div>
                    </form>
                </div>
            </div>
            
            <!-- Section d'assignation des stagiaires (uniquement si la colonne existe) -->
            <?php if ($has_encadreur_column): ?>
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white">
                    <h5 class="mb-0">
                        <i class="fas fa-users me-2 text-primary"></i>Gestion des stagiaires
                    </h5>
                    <small class="text-muted">
                        Capacité actuelle: <?= count($stagiaires_assignes) ?> / <?= $encadreur['max_stagiaires'] ?> stagiaires
                        <span class="badge-capacity ms-2">
                            <?= $encadreur['max_stagiaires'] - count($stagiaires_assignes) ?> places restantes
                        </span>
                    </small>
                </div>
                <div class="card-body">
                    <div class="row">
                        <!-- Stagiaires non assignés -->
                        <div class="col-md-6">
                            <h6 class="fw-bold mb-3">
                                <i class="fas fa-user-plus text-success me-1"></i>Stagiaires non assignés
                            </h6>
                            <?php if (empty($stagiaires_non_assignes)): ?>
                                <div class="alert alert-info">
                                    <i class="fas fa-info-circle me-2"></i>Aucun stagiaire disponible
                                </div>
                            <?php else: ?>
                                <div class="list-group">
                                    <?php foreach ($stagiaires_non_assignes as $stag): ?>
                                        <div class="list-group-item d-flex justify-content-between align-items-center">
                                            <div>
                                                <strong><?= htmlspecialchars($stag['prenom'] . ' ' . $stag['nom']) ?></strong>
                                                <br>
                                                <small class="text-muted"><?= htmlspecialchars($stag['filiere'] ?? 'Non renseignée') ?> - <?= htmlspecialchars($stag['niveau_etude'] ?? '') ?></small>
                                            </div>
                                            <form method="POST" class="m-0">
                                                <input type="hidden" name="stagiaire_id" value="<?= $stag['id_stagiaire'] ?>">
                                                <button type="submit" name="assigner_stagiaire" class="btn btn-sm btn-success" 
                                                        <?= count($stagiaires_assignes) >= $encadreur['max_stagiaires'] ? 'disabled' : '' ?>>
                                                    <i class="fas fa-plus"></i> Assigner
                                                </button>
                                            </form>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                        
                        <!-- Stagiaires assignés -->
                        <div class="col-md-6">
                            <h6 class="fw-bold mb-3">
                                <i class="fas fa-user-check text-primary me-1"></i>Stagiaires assignés
                            </h6>
                            <?php if (empty($stagiaires_assignes)): ?>
                                <div class="alert alert-secondary">
                                    <i class="fas fa-info-circle me-2"></i>Aucun stagiaire assigné
                                </div>
                            <?php else: ?>
                                <div class="list-group">
                                    <?php foreach ($stagiaires_assignes as $stag): ?>
                                        <div class="list-group-item d-flex justify-content-between align-items-center">
                                            <div>
                                                <strong><?= htmlspecialchars($stag['prenom'] . ' ' . $stag['nom']) ?></strong>
                                                <br>
                                                <small class="text-muted">
                                                    <?= htmlspecialchars($stag['filiere'] ?? 'Non renseignée') ?> - 
                                                    Stage: <?= date('d/m/Y', strtotime($stag['date_debut'] ?? 'now')) ?> au <?= date('d/m/Y', strtotime($stag['date_fin'] ?? 'now')) ?>
                                                </small>
                                            </div>
                                            <form method="POST" class="m-0">
                                                <input type="hidden" name="stagiaire_id" value="<?= $stag['id_stagiaire'] ?>">
                                                <button type="submit" name="desassigner_stagiaire" class="btn btn-sm btn-danger" onclick="return confirm('Retirer ce stagiaire de cet encadreur ?')">
                                                    <i class="fas fa-trash"></i> Retirer
                                                </button>
                                            </form>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
            <?php else: ?>
            <div class="warning-box">
                <i class="fas fa-exclamation-triangle text-warning me-2"></i>
                <strong>Note :</strong> La table stagiaires ne contient pas la colonne d'assignation d'encadreur. 
                Pour pouvoir assigner des stagiaires, veuillez exécuter la requête SQL suivante :
                <pre class="mt-2">ALTER TABLE stagiaires ADD COLUMN id_encadreur INT NULL;</pre>
            </div>
            <?php endif; ?>
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

// Calcul de l'âge en temps réel
const dateNaissance = document.getElementById('date_naissance');
const ageDisplay = document.createElement('small');
ageDisplay.className = 'help-text mt-1';
ageDisplay.style.display = 'block';

if (dateNaissance) {
    dateNaissance.insertAdjacentElement('afterend', ageDisplay);
    
    dateNaissance.addEventListener('change', function() {
        const date = new Date(this.value);
        const today = new Date();
        let age = today.getFullYear() - date.getFullYear();
        const m = today.getMonth() - date.getMonth();
        if (m < 0 || (m === 0 && today.getDate() < date.getDate())) {
            age--;
        }
        
        if (this.value && !isNaN(age)) {
            if (age < 18) {
                ageDisplay.innerHTML = '<i class="fas fa-exclamation-triangle text-warning me-1"></i> Âge: ' + age + ' ans (minimum 18 ans requis)';
                ageDisplay.style.color = '#dc2626';
            } else {
                ageDisplay.innerHTML = '<i class="fas fa-check-circle text-success me-1"></i> Âge: ' + age + ' ans';
                ageDisplay.style.color = '#16a34a';
            }
        } else {
            ageDisplay.innerHTML = '';
        }
    });
    
    if (dateNaissance.value) {
        dateNaissance.dispatchEvent(new Event('change'));
    }
}
</script>

<?php include '../../includes/footer.php'; ?>