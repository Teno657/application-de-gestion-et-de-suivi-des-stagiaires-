<?php
/**
 * Ajouter un stagiaire (Admin)
 * Version avec design moderne et animations
 */

require_once '../../includes/config.php';
require_once '../../includes/functions.php';
require_once '../../includes/auth.php';

// Vérifier le rôle
require_role('admin');

$page_title = "Ajouter un stagiaire - School-Connection";
$error = '';

// Récupérer les filières
$filieres = $pdo->query("SELECT * FROM filieres WHERE actif = 1 ORDER BY nom_filiere")->fetchAll();

// Récupérer les encadreurs disponibles
$encadreurs = $pdo->query("
    SELECT u.id_utilisateur, u.nom, u.prenom, e.profession, e.specialite, e.disponible,
           (e.max_stagiaires - e.stagiaires_actuels) as places_disponibles
    FROM encadreurs e
    JOIN utilisateurs u ON e.id_encadreur = u.id_utilisateur
    WHERE u.est_actif = 1 AND e.disponible = 1
    ORDER BY u.nom, u.prenom
")->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $error = 'Token de sécurité invalide';
    } else {
        // Informations de base
        $nom = cleanInput($_POST['nom'] ?? '');
        $prenom = cleanInput($_POST['prenom'] ?? '');
        $email = cleanInput($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $telephone = cleanInput($_POST['telephone'] ?? '');
        $adresse = cleanInput($_POST['adresse'] ?? '');
        
        // Informations de stage
        $filiere = cleanInput($_POST['filiere'] ?? '');
        $niveau_etude = $_POST['niveau_etude'] ?? '';
        $etablissement = cleanInput($_POST['etablissement'] ?? '');
        $theme_stage = cleanInput($_POST['theme_stage'] ?? '');
        $date_debut = $_POST['date_debut'] ?? '';
        $date_fin = $_POST['date_fin'] ?? '';
        $statut_inscription = $_POST['statut_inscription'] ?? 'en_attente';
        $id_encadreur = (int)($_POST['id_encadreur'] ?? 0);
        
        // Validation
        $errors = [];
        
        if (empty($nom)) $errors[] = 'Le nom est requis';
        if (empty($prenom)) $errors[] = 'Le prénom est requis';
        if (empty($email)) $errors[] = 'L\'email est requis';
        elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Email invalide';
        if (empty($password)) $errors[] = 'Le mot de passe est requis';
        elseif (strlen($password) < 8) $errors[] = 'Le mot de passe doit contenir au moins 8 caractères';
        
        if (empty($filiere)) $errors[] = 'La filière est requise';
        if (empty($niveau_etude)) $errors[] = 'Le niveau d\'étude est requis';
        if (empty($theme_stage)) $errors[] = 'Le thème du stage est requis';
        if (empty($date_debut)) $errors[] = 'La date de début est requise';
        if (empty($date_fin)) $errors[] = 'La date de fin est requise';
        
        if (strtotime($date_fin) <= strtotime($date_debut)) {
            $errors[] = 'La date de fin doit être postérieure à la date de début';
        }
        
        // Vérifier si l'email existe déjà
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM utilisateurs WHERE email = ?");
        $stmt->execute([$email]);
        if ($stmt->fetchColumn() > 0) {
            $errors[] = 'Cet email est déjà utilisé';
        }
        
        // Vérifier si l'encadreur a des places disponibles
        if ($id_encadreur > 0) {
            $stmt = $pdo->prepare("
                SELECT (max_stagiaires - stagiaires_actuels) as places 
                FROM encadreurs 
                WHERE id_encadreur = ?
            ");
            $stmt->execute([$id_encadreur]);
            $places = $stmt->fetchColumn();
            
            if ($places <= 0) {
                $errors[] = 'Cet encadreur n\'a plus de places disponibles';
            }
        }
        
        // Upload de la photo
        $photo_path = 'default-avatar.png';
        if (isset($_FILES['photo']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
            $result = upload_file($_FILES['photo'], UPLOADS_PATH . 'photos/', ALLOWED_IMAGES, 2 * 1024 * 1024);
            if ($result['success']) {
                $photo_path = 'uploads/photos/' . $result['filename'];
            } else {
                $errors[] = 'Erreur photo : ' . $result['message'];
            }
        }
        
        // Upload du CV
        $cv_path = null;
        if (isset($_FILES['cv']) && $_FILES['cv']['error'] === UPLOAD_ERR_OK) {
            $result = upload_file($_FILES['cv'], UPLOADS_PATH . 'documents/cv/', ['application/pdf'], 5 * 1024 * 1024);
            if ($result['success']) {
                $cv_path = 'uploads/documents/cv/' . $result['filename'];
            } else {
                $errors[] = 'Erreur CV : ' . $result['message'];
            }
        }
        
        // Upload de la lettre de motivation
        $lettre_path = null;
        if (isset($_FILES['lettre']) && $_FILES['lettre']['error'] === UPLOAD_ERR_OK) {
            $result = upload_file($_FILES['lettre'], UPLOADS_PATH . 'documents/lettres/', ['application/pdf'], 5 * 1024 * 1024);
            if ($result['success']) {
                $lettre_path = 'uploads/documents/lettres/' . $result['filename'];
            } else {
                $errors[] = 'Erreur lettre de motivation : ' . $result['message'];
            }
        }
        
        if (empty($errors)) {
            try {
                $pdo->beginTransaction();
                
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                
                // Insérer l'utilisateur
                $stmt = $pdo->prepare("
                    INSERT INTO utilisateurs (nom, prenom, email, mot_de_passe, telephone, adresse, photo, role, ip_inscription)
                    VALUES (?, ?, ?, ?, ?, ?, ?, 'stagiaire', ?)
                ");
                $stmt->execute([
                    $nom, $prenom, $email, $hashed_password, $telephone, $adresse, $photo_path,
                    get_client_ip()
                ]);
                
                $user_id = $pdo->lastInsertId();
                
                // Insérer dans stagiaires
                $stmt = $pdo->prepare("
                    INSERT INTO stagiaires (id_stagiaire, filiere, niveau_etude, etablissement, theme_stage, date_debut, date_fin, statut_inscription)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([$user_id, $filiere, $niveau_etude, $etablissement, $theme_stage, $date_debut, $date_fin, $statut_inscription]);
                
                // Créer la relation d'encadrement si un encadreur a été choisi
                if ($id_encadreur > 0) {
                    $stmt = $pdo->prepare("
                        UPDATE stagiaires SET id_encadreur = ? WHERE id_stagiaire = ?
                    ");
                    $stmt->execute([$id_encadreur, $user_id]);
                    
                    // Mettre à jour le compteur de l'encadreur
                    $pdo->prepare("
                        UPDATE encadreurs SET stagiaires_actuels = stagiaires_actuels + 1
                        WHERE id_encadreur = ?
                    ")->execute([$id_encadreur]);
                }
                
                // Ajouter les documents
                if ($cv_path) {
                    $stmt = $pdo->prepare("
                        INSERT INTO documents (id_utilisateur, nom_fichier, chemin, taille, type_document)
                        VALUES (?, ?, ?, ?, 'cv')
                    ");
                    $stmt->execute([$user_id, $_FILES['cv']['name'], $cv_path, $_FILES['cv']['size']]);
                }
                
                if ($lettre_path) {
                    $stmt = $pdo->prepare("
                        INSERT INTO documents (id_utilisateur, nom_fichier, chemin, taille, type_document)
                        VALUES (?, ?, ?, ?, 'lettre_motivation')
                    ");
                    $stmt->execute([$user_id, $_FILES['lettre']['name'], $lettre_path, $_FILES['lettre']['size']]);
                }
                
                log_action($_SESSION['user_id'], 'CREATE_STAGIAIRE', "Création du stagiaire $email", 'creation', 'stagiaires', $user_id);
                
                $pdo->commit();
                
                $_SESSION['flash']['success'] = "Le stagiaire a été créé avec succès";
                redirect("stagiaire-voir.php?id=$user_id");
                
            } catch (Exception $e) {
                $pdo->rollBack();
                $error = "Erreur lors de la création : " . $e->getMessage();
            }
        } else {
            $error = implode('<br>', $errors);
        }
    }
}

include '../../includes/header.php';
?>

<style>
    @keyframes fadeInUp {
        from { opacity: 0; transform: translateY(30px); }
        to { opacity: 1; transform: translateY(0); }
    }
    
    @keyframes slideInLeft {
        from { opacity: 0; transform: translateX(-30px); }
        to { opacity: 1; transform: translateX(0); }
    }
    
    .animate-fadeInUp {
        animation: fadeInUp 0.6s ease-out forwards;
    }
    
    .animate-slideInLeft {
        animation: slideInLeft 0.6s ease-out forwards;
    }
    
    .hero-add {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        border-radius: 30px;
        padding: 50px;
        margin-bottom: 40px;
        position: relative;
        overflow: hidden;
    }
    
    .hero-add::before {
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
    
    .form-card {
        background: white;
        border-radius: 28px;
        padding: 35px;
        box-shadow: 0 20px 40px rgba(0,0,0,0.1);
    }
    
    .form-section {
        background: #f8faff;
        border-radius: 20px;
        padding: 25px;
        margin-bottom: 30px;
        transition: all 0.3s ease;
    }
    
    .form-section:hover {
        box-shadow: 0 5px 20px rgba(102,126,234,0.1);
    }
    
    .section-title {
        font-size: 1.2rem;
        font-weight: 700;
        color: #667eea;
        margin-bottom: 25px;
        padding-bottom: 12px;
        border-bottom: 2px solid #e5e7eb;
        display: flex;
        align-items: center;
        gap: 10px;
    }
    
    .section-title i {
        font-size: 1.3rem;
    }
    
    .form-control, .form-select {
        border-radius: 14px;
        padding: 12px 16px;
        border: 2px solid #e5e7eb;
        transition: all 0.3s ease;
    }
    
    .form-control:focus, .form-select:focus {
        border-color: #667eea;
        box-shadow: 0 0 0 4px rgba(102,126,234,0.1);
        outline: none;
    }
    
    .form-label {
        font-weight: 600;
        color: #374151;
        margin-bottom: 8px;
    }
    
    .required-star {
        color: #ef4444;
        margin-left: 4px;
    }
    
    .help-text {
        font-size: 0.75rem;
        color: #9ca3af;
        margin-top: 5px;
    }
    
    .btn-submit {
        background: linear-gradient(135deg, #667eea, #764ba2);
        border: none;
        padding: 14px 30px;
        border-radius: 50px;
        font-weight: 600;
        transition: all 0.3s ease;
    }
    
    .btn-submit:hover {
        transform: translateY(-3px);
        box-shadow: 0 10px 25px rgba(102,126,234,0.4);
    }
    
    .btn-cancel {
        background: #f3f4f6;
        color: #4b5563;
        border: none;
        padding: 14px 30px;
        border-radius: 50px;
        font-weight: 600;
        transition: all 0.3s ease;
    }
    
    .btn-cancel:hover {
        background: #e5e7eb;
        transform: translateY(-2px);
    }
    
    .photo-preview {
        width: 100px;
        height: 100px;
        border-radius: 50%;
        object-fit: cover;
        border: 3px solid #667eea;
        margin-top: 10px;
        transition: all 0.3s ease;
    }
    
    .photo-preview:hover {
        transform: scale(1.05);
    }
    
    .badge-info {
        background: #e0e7ff;
        color: #3730a3;
        padding: 4px 10px;
        border-radius: 20px;
        font-size: 0.7rem;
        font-weight: 600;
    }
    
    @media (max-width: 768px) {
        .hero-add { padding: 30px; }
        .form-card { padding: 20px; }
        .form-section { padding: 20px; }
    }
</style>

<div class="container-fluid">
    <!-- Hero Section -->
    <div class="hero-add animate-fadeInUp">
        <div class="row align-items-center">
            <div class="col-md-8">
                <h1 class="display-4 fw-bold text-white mb-3">
                    <i class="fas fa-user-plus me-3"></i>
                    Ajouter un stagiaire
                </h1>
                <p class="lead text-white-50 mb-0">
                    Créez un nouveau compte stagiaire avec toutes ses informations
                </p>
            </div>
            <div class="col-md-4 text-md-end mt-3 mt-md-0">
                <a href="stagiaires.php" class="btn btn-light btn-lg rounded-pill px-4 shadow-sm">
                    <i class="fas fa-arrow-left me-2"></i>
                    Retour
                </a>
            </div>
        </div>
    </div>
    
    <div class="row">
        <div class="col-lg-10 mx-auto">
            <div class="form-card animate-slideInLeft">
                <?php if ($error): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <i class="fas fa-exclamation-circle me-2"></i>
                        <?= $error ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>
                
                <form method="POST" action="" enctype="multipart/form-data">
                    <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
                    
                    <!-- Informations personnelles -->
                    <div class="form-section">
                        <div class="section-title">
                            <i class="fas fa-user-circle"></i>
                            Informations personnelles
                            <span class="badge-info ms-2">Identité</span>
                        </div>
                        <div class="row g-4">
                            <div class="col-md-6">
                                <label class="form-label">
                                    Nom <span class="required-star">*</span>
                                </label>
                                <input type="text" class="form-control" name="nom" 
                                       value="<?= e($_POST['nom'] ?? '') ?>" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">
                                    Prénom <span class="required-star">*</span>
                                </label>
                                <input type="text" class="form-control" name="prenom" 
                                       value="<?= e($_POST['prenom'] ?? '') ?>" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">
                                    Email <span class="required-star">*</span>
                                </label>
                                <input type="email" class="form-control" name="email" 
                                       value="<?= e($_POST['email'] ?? '') ?>" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">
                                    Mot de passe <span class="required-star">*</span>
                                </label>
                                <input type="password" class="form-control" name="password" required>
                                <div class="help-text">
                                    <i class="fas fa-info-circle me-1"></i>
                                    Minimum 8 caractères
                                </div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Téléphone</label>
                                <input type="tel" class="form-control" name="telephone" 
                                       value="<?= e($_POST['telephone'] ?? '') ?>">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Photo de profil</label>
                                <input type="file" class="form-control" name="photo" accept="image/*" 
                                       onchange="previewPhoto(this)">
                                <div class="text-center mt-2">
                                    <img id="photoPreview" src="<?= APP_URL ?>/assets/images/default-avatar.png" 
                                         class="photo-preview" style="display: none;">
                                </div>
                            </div>
                            <div class="col-12">
                                <label class="form-label">Adresse</label>
                                <textarea class="form-control" name="adresse" rows="2"><?= e($_POST['adresse'] ?? '') ?></textarea>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Informations académiques -->
                    <div class="form-section">
                        <div class="section-title">
                            <i class="fas fa-graduation-cap"></i>
                            Informations académiques
                            <span class="badge-info ms-2">Formation</span>
                        </div>
                        <div class="row g-4">
                            <div class="col-md-6">
                                <label class="form-label">
                                    Filière <span class="required-star">*</span>
                                </label>
                                <select class="form-select" name="filiere" required>
                                    <option value="">Sélectionnez une filière</option>
                                    <?php foreach ($filieres as $f): ?>
                                        <option value="<?= e($f['nom_filiere']) ?>" <?= ($_POST['filiere'] ?? '') === $f['nom_filiere'] ? 'selected' : '' ?>>
                                            <?= e($f['nom_filiere']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">
                                    Niveau d'étude <span class="required-star">*</span>
                                </label>
                                <select class="form-select" name="niveau_etude" required>
                                    <option value="">Sélectionnez</option>
                                    <option value="Licence1" <?= ($_POST['niveau_etude'] ?? '') === 'Licence1' ? 'selected' : '' ?>>Licence 1</option>
                                    <option value="Licence2" <?= ($_POST['niveau_etude'] ?? '') === 'Licence2' ? 'selected' : '' ?>>Licence 2</option>
                                    <option value="Licence3" <?= ($_POST['niveau_etude'] ?? '') === 'Licence3' ? 'selected' : '' ?>>Licence 3</option>
                                    <option value="Master1" <?= ($_POST['niveau_etude'] ?? '') === 'Master1' ? 'selected' : '' ?>>Master 1</option>
                                    <option value="Master2" <?= ($_POST['niveau_etude'] ?? '') === 'Master2' ? 'selected' : '' ?>>Master 2</option>
                                    <option value="Doctorat" <?= ($_POST['niveau_etude'] ?? '') === 'Doctorat' ? 'selected' : '' ?>>Doctorat</option>
                                    <option value="BTS" <?= ($_POST['niveau_etude'] ?? '') === 'BTS' ? 'selected' : '' ?>>BTS</option>
                                    <option value="DUT" <?= ($_POST['niveau_etude'] ?? '') === 'DUT' ? 'selected' : '' ?>>DUT</option>
                                </select>
                            </div>
                            <div class="col-12">
                                <label class="form-label">Établissement</label>
                                <input type="text" class="form-control" name="etablissement" 
                                       value="<?= e($_POST['etablissement'] ?? '') ?>">
                            </div>
                            <div class="col-12">
                                <label class="form-label">
                                    Thème du stage <span class="required-star">*</span>
                                </label>
                                <textarea class="form-control" name="theme_stage" rows="3" required><?= e($_POST['theme_stage'] ?? '') ?></textarea>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Période de stage -->
                    <div class="form-section">
                        <div class="section-title">
                            <i class="fas fa-calendar-alt"></i>
                            Période de stage
                            <span class="badge-info ms-2">Dates</span>
                        </div>
                        <div class="row g-4">
                            <div class="col-md-6">
                                <label class="form-label">
                                    Date de début <span class="required-star">*</span>
                                </label>
                                <input type="date" class="form-control" name="date_debut" 
                                       value="<?= $_POST['date_debut'] ?? '' ?>" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">
                                    Date de fin <span class="required-star">*</span>
                                </label>
                                <input type="date" class="form-control" name="date_fin" 
                                       value="<?= $_POST['date_fin'] ?? '' ?>" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Statut</label>
                                <select class="form-select" name="statut_inscription">
                                    <option value="en_attente" <?= ($_POST['statut_inscription'] ?? '') === 'en_attente' ? 'selected' : '' ?>>En attente</option>
                                    <option value="actif" <?= ($_POST['statut_inscription'] ?? '') === 'actif' ? 'selected' : '' ?>>Actif</option>
                                    <option value="termine" <?= ($_POST['statut_inscription'] ?? '') === 'termine' ? 'selected' : '' ?>>Terminé</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Assignation encadreur -->
                    <div class="form-section">
                        <div class="section-title">
                            <i class="fas fa-chalkboard-teacher"></i>
                            Assigner un encadreur
                            <span class="badge-info ms-2">Optionnel</span>
                        </div>
                        <div class="row g-4">
                            <div class="col-12">
                                <select class="form-select" name="id_encadreur">
                                    <option value="0">-- Ne pas assigner d'encadreur pour le moment --</option>
                                    <?php foreach ($encadreurs as $e): ?>
                                        <?php if ($e['places_disponibles'] > 0): ?>
                                        <option value="<?= $e['id_utilisateur'] ?>" <?= ($_POST['id_encadreur'] ?? 0) == $e['id_utilisateur'] ? 'selected' : '' ?>>
                                            <?= e($e['prenom'] . ' ' . $e['nom']) ?> - <?= e($e['profession']) ?> 
                                            (<?= $e['places_disponibles'] ?> place(s) disponible(s))
                                        </option>
                                        <?php endif; ?>
                                    <?php endforeach; ?>
                                </select>
                                <div class="help-text mt-2">
                                    <i class="fas fa-info-circle me-1"></i>
                                    Vous pourrez modifier l'encadreur plus tard
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Documents -->
                    <div class="form-section">
                        <div class="section-title">
                            <i class="fas fa-file-alt"></i>
                            Documents
                            <span class="badge-info ms-2">Optionnel</span>
                        </div>
                        <div class="row g-4">
                            <div class="col-md-6">
                                <label class="form-label">CV (PDF)</label>
                                <input type="file" class="form-control" name="cv" accept=".pdf">
                                <div class="help-text">Max 5 Mo</div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Lettre de motivation (PDF)</label>
                                <input type="file" class="form-control" name="lettre" accept=".pdf">
                                <div class="help-text">Max 5 Mo</div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Actions -->
                    <div class="d-flex gap-3 justify-content-end mt-4">
                        <a href="stagiaires.php" class="btn btn-cancel px-4">
                            <i class="fas fa-times me-2"></i>Annuler
                        </a>
                        <button type="submit" class="btn btn-submit text-white px-5">
                            <i class="fas fa-save me-2"></i>Créer le stagiaire
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
    // Prévisualisation de la photo
    function previewPhoto(input) {
        const preview = document.getElementById('photoPreview');
        if (input.files && input.files[0]) {
            const reader = new FileReader();
            reader.onload = function(e) {
                preview.src = e.target.result;
                preview.style.display = 'block';
            };
            reader.readAsDataURL(input.files[0]);
        }
    }
    
    // Calcul automatique de la date de fin (3 mois après début)
    document.querySelector('input[name="date_debut"]').addEventListener('change', function() {
        const dateFin = document.querySelector('input[name="date_fin"]');
        if (this.value && !dateFin.value) {
            const date = new Date(this.value);
            date.setMonth(date.getMonth() + 3);
            const year = date.getFullYear();
            const month = String(date.getMonth() + 1).padStart(2, '0');
            const day = String(date.getDate()).padStart(2, '0');
            dateFin.value = `${year}-${month}-${day}`;
        }
    });
    
    // Animation des sections au scroll
    document.addEventListener('DOMContentLoaded', function() {
        const sections = document.querySelectorAll('.form-section');
        sections.forEach((section, index) => {
            section.style.animation = `fadeInUp 0.5s ease-out ${index * 0.1}s forwards`;
            section.style.opacity = '0';
        });
    });
</script>

<?php include '../../includes/footer.php'; ?>