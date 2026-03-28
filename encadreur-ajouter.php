<?php
/**
 * Ajouter un encadreur (Admin)
 * Version avec design moderne et animations
 */

require_once '../../includes/config.php';
require_once '../../includes/functions.php';
require_once '../../includes/auth.php';

// Vérifier le rôle
require_role('admin');

$page_title = "Ajouter un encadreur - School-Connection";
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Vérifier le token CSRF
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
        
        // Validation
        $errors = [];
        
        if (empty($nom)) $errors[] = 'Le nom est requis';
        if (empty($prenom)) $errors[] = 'Le prénom est requis';
        if (empty($email)) $errors[] = 'L\'email est requis';
        elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Email invalide';
        if (empty($password)) $errors[] = 'Le mot de passe est requis';
        elseif (strlen($password) < 8) $errors[] = 'Le mot de passe doit contenir au moins 8 caractères';
        
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
                if ($age < 18) $errors[] = 'L\'encadreur doit avoir au moins 18 ans';
                if ($age > 100) $errors[] = 'L\'âge ne peut pas dépasser 100 ans';
            }
        }
        
        // Validation des années d'expérience
        if ($annees_experience < 0) $errors[] = 'Les années d\'expérience ne peuvent pas être négatives';
        if ($annees_experience > 50) $errors[] = 'Les années d\'expérience ne peuvent pas dépasser 50';
        
        // Vérifier si l'email existe déjà
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM utilisateurs WHERE email = ?");
        $stmt->execute([$email]);
        if ($stmt->fetchColumn() > 0) {
            $errors[] = 'Cet email est déjà utilisé';
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
        
        if (empty($errors)) {
            try {
                $pdo->beginTransaction();
                
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                
                // Insérer l'utilisateur
                $stmt = $pdo->prepare("
                    INSERT INTO utilisateurs (nom, prenom, email, mot_de_passe, telephone, adresse, photo, role, ip_inscription)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([
                    $nom, $prenom, $email, $hashed_password, $telephone, $adresse, $photo_path, 'encadreur_pro',
                    get_client_ip()
                ]);
                
                $user_id = $pdo->lastInsertId();
                
                // Insérer dans encadreurs
                $stmt = $pdo->prepare("
                    INSERT INTO encadreurs (
                        id_encadreur, date_naissance, profession, specialite, entreprise, poste, 
                        annees_experience, diplome, certifications, bio, disponible, max_stagiaires
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([
                    $user_id, $date_naissance, $profession, $specialite, $entreprise, $poste,
                    $annees_experience, $diplome, $certifications, $bio, $disponible, $max_stagiaires
                ]);
                
                log_action($_SESSION['user_id'], 'CREATE_ENCADREUR', "Création de l'encadreur $email", 'creation', 'encadreurs', $user_id);
                
                $pdo->commit();
                
                $_SESSION['flash']['success'] = "L'encadreur a été créé avec succès";
                redirect("encadreur-voir.php?id=$user_id");
                
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
        from {
            opacity: 0;
            transform: translateY(30px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }
    
    @keyframes slideInLeft {
        from {
            opacity: 0;
            transform: translateX(-30px);
        }
        to {
            opacity: 1;
            transform: translateX(0);
        }
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
        padding: 40px;
        margin-bottom: 30px;
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
        width: 120px;
        height: 120px;
        border-radius: 50%;
        object-fit: cover;
        border: 4px solid #667eea;
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
        .hero-add { padding: 25px; }
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
                    <i class="fas fa-chalkboard-user me-3"></i>
                    Ajouter un encadreur
                </h1>
                <p class="lead text-white-50 mb-0">
                    Créez un nouveau compte encadreur avec toutes ses informations professionnelles
                </p>
            </div>
            <div class="col-md-4 text-md-end mt-3 mt-md-0">
                <a href="encadreurs.php" class="btn btn-light btn-lg rounded-pill px-4 shadow-sm">
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
                                    Minimum 8 caractères, avec majuscule, minuscule et chiffre
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
                    
                    <!-- Informations professionnelles -->
                    <div class="form-section">
                        <div class="section-title">
                            <i class="fas fa-briefcase"></i>
                            Informations professionnelles
                            <span class="badge-info ms-2">Carrière</span>
                        </div>
                        <div class="row g-4">
                            <div class="col-md-6">
                                <label class="form-label">Date de naissance</label>
                                <input type="date" class="form-control" name="date_naissance" 
                                       value="<?= e($_POST['date_naissance'] ?? '') ?>" 
                                       min="1920-01-01" max="<?= date('Y-m-d', strtotime('-18 years')) ?>">
                                <div class="help-text" id="ageDisplay"></div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">
                                    Profession <span class="required-star">*</span>
                                </label>
                                <input type="text" class="form-control" name="profession" 
                                       value="<?= e($_POST['profession'] ?? '') ?>" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">
                                    Spécialité <span class="required-star">*</span>
                                </label>
                                <input type="text" class="form-control" name="specialite" 
                                       value="<?= e($_POST['specialite'] ?? '') ?>" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">
                                    Entreprise / Institution <span class="required-star">*</span>
                                </label>
                                <input type="text" class="form-control" name="entreprise" 
                                       value="<?= e($_POST['entreprise'] ?? '') ?>" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Poste occupé</label>
                                <input type="text" class="form-control" name="poste" 
                                       value="<?= e($_POST['poste'] ?? '') ?>" 
                                       placeholder="Ex: Directeur technique, Chef de projet...">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Années d'expérience</label>
                                <div class="input-group">
                                    <input type="number" class="form-control" name="annees_experience" 
                                           value="<?= (int)($_POST['annees_experience'] ?? 0) ?>" 
                                           min="0" max="50" step="1">
                                    <span class="input-group-text">ans</span>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Diplôme</label>
                                <input type="text" class="form-control" name="diplome" 
                                       value="<?= e($_POST['diplome'] ?? '') ?>" 
                                       placeholder="Ex: Master, Doctorat, Ingénieur...">
                            </div>
                            <div class="col-12">
                                <label class="form-label">Certifications</label>
                                <textarea class="form-control" name="certifications" rows="2" 
                                          placeholder="Ex: CCNA, PMP, AWS Certified, TOEIC..."><?= e($_POST['certifications'] ?? '') ?></textarea>
                            </div>
                            <div class="col-12">
                                <label class="form-label">Biographie</label>
                                <textarea class="form-control" name="bio" rows="4" 
                                          placeholder="Présentez l'encadreur, son parcours, ses compétences..."><?= e($_POST['bio'] ?? '') ?></textarea>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Disponibilité -->
                    <div class="form-section">
                        <div class="section-title">
                            <i class="fas fa-calendar-check"></i>
                            Disponibilité
                            <span class="badge-info ms-2">Gestion des stagiaires</span>
                        </div>
                        <div class="row g-4">
                            <div class="col-md-4">
                                <label class="form-label">Nombre maximum de stagiaires</label>
                                <input type="number" class="form-control" name="max_stagiaires" 
                                       value="<?= (int)($_POST['max_stagiaires'] ?? 5) ?>" 
                                       min="1" max="50">
                            </div>
                            <div class="col-md-8">
                                <div class="form-check mt-4">
                                    <input class="form-check-input" type="checkbox" id="disponible" name="disponible" 
                                           <?= isset($_POST['disponible']) ? 'checked' : 'checked' ?>>
                                    <label class="form-check-label" for="disponible">
                                        <i class="fas fa-check-circle text-success me-1"></i>
                                        Disponible pour encadrer des stagiaires
                                    </label>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Actions -->
                    <div class="d-flex gap-3 justify-content-end mt-4">
                        <a href="encadreurs.php" class="btn btn-cancel px-4">
                            <i class="fas fa-times me-2"></i>Annuler
                        </a>
                        <button type="submit" class="btn btn-submit text-white px-5">
                            <i class="fas fa-save me-2"></i>Créer l'encadreur
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
    
    // Calcul de l'âge en temps réel
    const dateNaissance = document.querySelector('input[name="date_naissance"]');
    const ageDisplay = document.getElementById('ageDisplay');
    
    if (dateNaissance) {
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
    }
</script>

<?php include '../../includes/footer.php'; ?>