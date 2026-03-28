<?php
/**
 * Ajouter un secrétaire (Admin) - Version améliorée
 */

require_once '../../includes/config.php';
require_once '../../includes/functions.php';
require_once '../../includes/auth.php';

// Vérifier le rôle
require_role('admin');

$page_title = "Ajouter un secrétaire - School-Connection";
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Vérifier le token CSRF
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $error = 'Token de sécurité invalide';
    } else {
        $nom = cleanInput($_POST['nom'] ?? '');
        $prenom = cleanInput($_POST['prenom'] ?? '');
        $email = cleanInput($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $telephone = cleanInput($_POST['telephone'] ?? '');
        $adresse = cleanInput($_POST['adresse'] ?? '');
        
        // Informations spécifiques - MATRICULE OBLIGATOIRE
        $service = cleanInput($_POST['service'] ?? '');
        $matricule = cleanInput($_POST['matricule'] ?? '');
        $date_embauche = $_POST['date_embauche'] ?? '';
        
        // Validation
        $errors = [];
        
        if (empty($nom)) $errors[] = 'Le nom est requis';
        if (empty($prenom)) $errors[] = 'Le prénom est requis';
        if (empty($email)) $errors[] = 'L\'email est requis';
        elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Email invalide';
        if (empty($password)) $errors[] = 'Le mot de passe est requis';
        elseif (strlen($password) < 8) $errors[] = 'Le mot de passe doit contenir au moins 8 caractères';
        
        if (empty($service)) $errors[] = 'Le service est requis';
        if (empty($matricule)) $errors[] = 'Le matricule est requis'; // OBLIGATOIRE MAINTENANT
        
        // Vérifier si l'email existe déjà
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM utilisateurs WHERE email = ?");
        $stmt->execute([$email]);
        if ($stmt->fetchColumn() > 0) {
            $errors[] = 'Cet email est déjà utilisé';
        }
        
        // Vérifier si le matricule est unique
        if (!empty($matricule)) {
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM secretaires WHERE matricule = ?");
            $stmt->execute([$matricule]);
            if ($stmt->fetchColumn() > 0) {
                $errors[] = 'Ce matricule est déjà utilisé';
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
        
        if (empty($errors)) {
            try {
                $pdo->beginTransaction();
                
                // Hash du mot de passe
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                
                // Insérer l'utilisateur
                $stmt = $pdo->prepare("
                    INSERT INTO utilisateurs (nom, prenom, email, mot_de_passe, telephone, adresse, photo, role, ip_inscription)
                    VALUES (?, ?, ?, ?, ?, ?, ?, 'secretaire', ?)
                ");
                $stmt->execute([
                    $nom, $prenom, $email, $hashed_password, $telephone, $adresse, $photo_path,
                    get_client_ip()
                ]);
                
                $user_id = $pdo->lastInsertId();
                
                // Insérer dans secretaires
                $stmt = $pdo->prepare("
                    INSERT INTO secretaires (id_secretaire, service, matricule, date_embauche)
                    VALUES (?, ?, ?, ?)
                ");
                $stmt->execute([$user_id, $service, $matricule, $date_embauche ?: null]);
                
                // Journaliser l'action
                log_action($_SESSION['user_id'], 'CREATE_SECRETAIRE', "Création du secrétaire $email", 'creation', 'secretaires', $user_id);
                
                $pdo->commit();
                
                $_SESSION['flash']['success'] = "Le secrétaire a été créé avec succès";
                redirect("utilisateur-voir.php?id=$user_id");
                
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

<!-- ============================================= -->
<!-- STYLES CSS AVEC ANIMATIONS                    -->
<!-- ============================================= -->
<style>
    @import url('https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap');
    @import url('https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css');
    
    * {
        font-family: 'Poppins', sans-serif;
    }
    
    body {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        min-height: 100vh;
        padding-top: 80px; /* ESPACE POUR LE NAVBAR */
    }
    
    .container-fluid {
        position: relative;
        z-index: 1;
        animation: fadeIn 1s ease;
    }
    
    @keyframes fadeIn {
        from { opacity: 0; transform: translateY(20px); }
        to { opacity: 1; transform: translateY(0); }
    }
    
    /* CARTE PRINCIPALE */
    .form-card {
        background: rgba(255, 255, 255, 0.95);
        backdrop-filter: blur(10px);
        border-radius: 30px;
        box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
        overflow: hidden;
        animation: slideInUp 0.8s ease;
        border: 1px solid rgba(255, 255, 255, 0.3);
        margin-bottom: 50px;
    }
    
    @keyframes slideInUp {
        from { transform: translateY(50px); opacity: 0; }
        to { transform: translateY(0); opacity: 1; }
    }
    
    .card-header {
        background: linear-gradient(135deg, #667eea, #764ba2);
        padding: 25px 30px;
        border-bottom: none;
        position: relative;
        overflow: hidden;
    }
    
    .card-header::before {
        content: '';
        position: absolute;
        top: -50%;
        right: -50%;
        width: 200%;
        height: 200%;
        background: linear-gradient(45deg, transparent, rgba(255,255,255,0.2), transparent);
        transform: rotate(45deg);
        animation: shine 6s infinite;
    }
    
    @keyframes shine {
        0% { transform: translateX(-100%) rotate(45deg); }
        20%, 100% { transform: translateX(100%) rotate(45deg); }
    }
    
    .card-header h2 {
        color: white;
        font-weight: 700;
        margin: 0;
        text-shadow: 2px 2px 10px rgba(0,0,0,0.2);
        font-size: 2rem;
    }
    
    .card-header p {
        color: rgba(255,255,255,0.8);
        margin: 5px 0 0 0;
    }
    
    .card-body {
        padding: 40px;
    }
    
    /* SECTIONS DU FORMULAIRE */
    .section-title {
        color: #667eea;
        font-weight: 600;
        margin: 30px 0 20px 0;
        padding-bottom: 10px;
        border-bottom: 2px solid #667eea;
        position: relative;
    }
    
    .section-title::after {
        content: '';
        position: absolute;
        bottom: -2px;
        left: 0;
        width: 50px;
        height: 2px;
        background: linear-gradient(90deg, #667eea, #764ba2);
        animation: slideLine 2s infinite;
    }
    
    @keyframes slideLine {
        0% { left: 0; width: 50px; }
        50% { left: 50%; width: 100px; transform: translateX(-50%); }
        100% { left: calc(100% - 50px); width: 50px; }
    }
    
    /* CHAMPS DE FORMULAIRE */
    .form-group {
        margin-bottom: 25px;
        position: relative;
        animation: fadeInUp 0.6s ease forwards;
        opacity: 0;
    }
    
    .form-group:nth-child(1) { animation-delay: 0.1s; }
    .form-group:nth-child(2) { animation-delay: 0.2s; }
    .form-group:nth-child(3) { animation-delay: 0.3s; }
    .form-group:nth-child(4) { animation-delay: 0.4s; }
    .form-group:nth-child(5) { animation-delay: 0.5s; }
    .form-group:nth-child(6) { animation-delay: 0.6s; }
    
    @keyframes fadeInUp {
        from { transform: translateY(20px); opacity: 0; }
        to { transform: translateY(0); opacity: 1; }
    }
    
    .form-label {
        font-weight: 600;
        color: #333;
        margin-bottom: 8px;
        display: block;
    }
    
    .form-label i {
        color: #667eea;
        margin-right: 8px;
    }
    
    .required-star {
        color: #f72585;
        margin-left: 3px;
        animation: pulse 1.5s infinite;
    }
    
    @keyframes pulse {
        0%, 100% { opacity: 1; }
        50% { opacity: 0.5; }
    }
    
    .input-group {
        position: relative;
        transition: all 0.3s ease;
    }
    
    .input-group:hover {
        transform: translateX(5px);
    }
    
    .input-icon {
        position: absolute;
        left: 15px;
        top: 50%;
        transform: translateY(-50%);
        color: #667eea;
        font-size: 1.2rem;
        z-index: 10;
    }
    
    .form-control, .form-select {
        height: 55px;
        border: 2px solid #e0e0e0;
        border-radius: 15px;
        padding: 10px 15px 10px 45px;
        font-size: 1rem;
        transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
        width: 100%;
        background: white;
    }
    
    .form-control:focus, .form-select:focus {
        border-color: #667eea;
        box-shadow: 0 0 0 4px rgba(102, 126, 234, 0.25);
        outline: none;
        transform: scale(1.02);
    }
    
    textarea.form-control {
        height: auto;
        padding-top: 15px;
    }
    
    /* PHOTO PREVIEW */
    .photo-preview-container {
        text-align: center;
        margin-bottom: 20px;
    }
    
    .photo-preview {
        width: 120px;
        height: 120px;
        border-radius: 50%;
        border: 4px solid #667eea;
        object-fit: cover;
        margin-bottom: 10px;
        box-shadow: 0 10px 20px rgba(102, 126, 234, 0.4);
        transition: all 0.5s ease;
        animation: pulseBorder 2s infinite;
    }
    
    @keyframes pulseBorder {
        0% { box-shadow: 0 0 0 0 rgba(102, 126, 234, 0.7); }
        70% { box-shadow: 0 0 0 15px rgba(102, 126, 234, 0); }
        100% { box-shadow: 0 0 0 0 rgba(102, 126, 234, 0); }
    }
    
    .photo-preview:hover {
        transform: scale(1.1) rotate(5deg);
        border-color: #764ba2;
    }
    
    .photo-upload-btn {
        display: inline-block;
        padding: 10px 25px;
        background: linear-gradient(135deg, #667eea, #764ba2);
        color: white;
        border-radius: 50px;
        cursor: pointer;
        transition: all 0.3s ease;
        font-weight: 500;
        border: none;
        box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
    }
    
    .photo-upload-btn:hover {
        transform: translateY(-3px);
        box-shadow: 0 10px 25px rgba(102, 126, 234, 0.6);
    }
    
    .photo-upload-btn i {
        margin-right: 8px;
    }
    
    /* BOUTONS */
    .btn-container {
        display: flex;
        justify-content: space-between;
        gap: 20px;
        margin-top: 40px;
        flex-wrap: wrap;
    }
    
    .btn {
        padding: 15px 40px;
        border-radius: 50px;
        font-weight: 600;
        font-size: 1.1rem;
        transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
        border: none;
        position: relative;
        overflow: hidden;
        flex: 1;
        min-width: 200px;
    }
    
    .btn::before {
        content: '';
        position: absolute;
        top: 50%;
        left: 50%;
        width: 0;
        height: 0;
        border-radius: 50%;
        background: rgba(255, 255, 255, 0.3);
        transform: translate(-50%, -50%);
        transition: width 0.6s ease, height 0.6s ease;
    }
    
    .btn:hover::before {
        width: 300px;
        height: 300px;
    }
    
    .btn-primary {
        background: linear-gradient(135deg, #667eea, #764ba2);
        color: white;
        box-shadow: 0 10px 25px rgba(102, 126, 234, 0.5);
    }
    
    .btn-primary:hover {
        transform: translateY(-5px) scale(1.05);
        box-shadow: 0 15px 35px rgba(102, 126, 234, 0.7);
    }
    
    .btn-secondary {
        background: linear-gradient(135deg, #f72585, #b5179e);
        color: white;
        box-shadow: 0 10px 25px rgba(247, 37, 133, 0.4);
    }
    
    .btn-secondary:hover {
        transform: translateY(-5px) scale(1.05);
        box-shadow: 0 15px 35px rgba(247, 37, 133, 0.6);
    }
    
    .btn-outline-secondary {
        background: transparent;
        border: 2px solid #6c757d;
        color: #6c757d;
    }
    
    .btn-outline-secondary:hover {
        background: linear-gradient(135deg, #6c757d, #495057);
        color: white;
        border-color: transparent;
    }
    
    /* ALERTES */
    .alert {
        border-radius: 15px;
        padding: 15px 20px;
        margin-bottom: 30px;
        animation: slideInDown 0.5s ease;
        border: none;
    }
    
    @keyframes slideInDown {
        from { transform: translateY(-20px); opacity: 0; }
        to { transform: translateY(0); opacity: 1; }
    }
    
    .alert-danger {
        background: rgba(247, 37, 133, 0.2);
        border-left: 4px solid #f72585;
        color: #333;
    }
    
    .alert-success {
        background: rgba(76, 201, 240, 0.2);
        border-left: 4px solid #4cc9f0;
        color: #333;
    }
    
    /* BADGE OBLIGATOIRE */
    .required-badge {
        display: inline-block;
        background: linear-gradient(135deg, #f72585, #b5179e);
        color: white;
        padding: 2px 10px;
        border-radius: 20px;
        font-size: 0.7rem;
        margin-left: 10px;
        animation: blink 1.5s infinite;
    }
    
    @keyframes blink {
        0%, 100% { opacity: 1; }
        50% { opacity: 0.7; }
    }
    
    /* RESPONSIVE */
    @media (max-width: 768px) {
        body {
            padding-top: 60px;
        }
        
        .card-body {
            padding: 20px;
        }
        
        .btn {
            padding: 12px 25px;
            font-size: 1rem;
            min-width: 150px;
        }
        
        .photo-preview {
            width: 100px;
            height: 100px;
        }
    }
</style>

<!-- ============================================= -->
<!-- CONTENU PRINCIPAL                             -->
<!-- ============================================= -->
<div class="container-fluid py-4">
    <div class="row justify-content-center">
        <div class="col-lg-10 col-xl-8">
            
            <!-- Messages flash -->
            <?php if (isset($_SESSION['flash'])): ?>
                <div class="alert alert-<?= key($_SESSION['flash']) === 'success' ? 'success' : 'danger' ?> alert-dismissible fade show">
                    <?= current($_SESSION['flash']) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
                <?php unset($_SESSION['flash']); ?>
            <?php endif; ?>
            
            <!-- Carte principale -->
            <div class="form-card">
                <div class="card-header">
                    <h2><i class="fas fa-user-tie me-2"></i> Ajouter un secrétaire</h2>
                    <p>Créez un nouveau compte secrétaire dans le système</p>
                </div>
                
                <div class="card-body">
                    <?php if ($error): ?>
                        <div class="alert alert-danger">
                            <i class="fas fa-exclamation-circle me-2"></i> <?= $error ?>
                        </div>
                    <?php endif; ?>
                    
                    <form method="POST" action="" enctype="multipart/form-data" id="secretaireForm">
                        <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
                        
                        <!-- PHOTO DE PROFIL -->
                        <div class="photo-preview-container">
                            <img src="<?= getPhotoUrl('default-avatar.png') ?>" 
                                 alt="Aperçu" class="photo-preview" id="photoPreview">
                            <div>
                                <label for="photo" class="photo-upload-btn">
                                    <i class="fas fa-camera"></i> Choisir une photo
                                </label>
                                <input type="file" id="photo" name="photo" class="d-none" accept="image/*" data-preview="photoPreview">
                                <small class="d-block text-muted mt-2">Format: JPG, PNG, GIF. Max 2 Mo</small>
                            </div>
                        </div>
                        
                        <!-- SECTION 1 : INFORMATIONS PERSONNELLES -->
                        <h3 class="section-title">
                            <i class="fas fa-user-circle me-2"></i>Informations personnelles
                        </h3>
                        
                        <div class="row">
                            <div class="col-md-6 form-group">
                                <label class="form-label">
                                    <i class="fas fa-user"></i> Nom <span class="required-star">*</span>
                                </label>
                                <div class="input-group">
                                    <span class="input-icon"><i class="fas fa-user"></i></span>
                                    <input type="text" class="form-control" name="nom" 
                                           value="<?= e($_POST['nom'] ?? '') ?>" 
                                           placeholder="Entrez le nom" required>
                                </div>
                            </div>
                            
                            <div class="col-md-6 form-group">
                                <label class="form-label">
                                    <i class="fas fa-user"></i> Prénom <span class="required-star">*</span>
                                </label>
                                <div class="input-group">
                                    <span class="input-icon"><i class="fas fa-user"></i></span>
                                    <input type="text" class="form-control" name="prenom" 
                                           value="<?= e($_POST['prenom'] ?? '') ?>" 
                                           placeholder="Entrez le prénom" required>
                                </div>
                            </div>
                            
                            <div class="col-md-6 form-group">
                                <label class="form-label">
                                    <i class="fas fa-envelope"></i> Email <span class="required-star">*</span>
                                </label>
                                <div class="input-group">
                                    <span class="input-icon"><i class="fas fa-envelope"></i></span>
                                    <input type="email" class="form-control" name="email" 
                                           value="<?= e($_POST['email'] ?? '') ?>" 
                                           placeholder="exemple@email.com" required>
                                </div>
                            </div>
                            
                            <div class="col-md-6 form-group">
                                <label class="form-label">
                                    <i class="fas fa-lock"></i> Mot de passe <span class="required-star">*</span>
                                </label>
                                <div class="input-group">
                                    <span class="input-icon"><i class="fas fa-lock"></i></span>
                                    <input type="password" class="form-control" name="password" 
                                           placeholder="Minimum 8 caractères" required>
                                </div>
                                <small class="text-muted">8 caractères minimum</small>
                            </div>
                            
                            <div class="col-md-6 form-group">
                                <label class="form-label">
                                    <i class="fas fa-phone"></i> Téléphone
                                </label>
                                <div class="input-group">
                                    <span class="input-icon"><i class="fas fa-phone"></i></span>
                                    <input type="tel" class="form-control" name="telephone" 
                                           value="<?= e($_POST['telephone'] ?? '') ?>" 
                                           placeholder="6XXXXXXXX">
                                </div>
                            </div>
                            
                            <div class="col-12 form-group">
                                <label class="form-label">
                                    <i class="fas fa-map-marker-alt"></i> Adresse
                                </label>
                                <div class="input-group">
                                    <span class="input-icon"><i class="fas fa-map-marker-alt"></i></span>
                                    <textarea class="form-control" name="adresse" rows="2" 
                                              placeholder="Adresse complète"><?= e($_POST['adresse'] ?? '') ?></textarea>
                                </div>
                            </div>
                        </div>
                        
                        <!-- SECTION 2 : INFORMATIONS PROFESSIONNELLES -->
                        <h3 class="section-title">
                            <i class="fas fa-briefcase me-2"></i>Informations professionnelles
                        </h3>
                        
                        <div class="row">
                            <div class="col-md-6 form-group">
                                <label class="form-label">
                                    <i class="fas fa-building"></i> Service <span class="required-star">*</span>
                                </label>
                                <div class="input-group">
                                    <span class="input-icon"><i class="fas fa-building"></i></span>
                                    <input type="text" class="form-control" name="service" 
                                           value="<?= e($_POST['service'] ?? '') ?>" 
                                           placeholder="Ex: Scolarité, Comptabilité..." required>
                                </div>
                            </div>
                            
                            <div class="col-md-6 form-group">
                                <label class="form-label">
                                    <i class="fas fa-id-card"></i> Matricule 
                                    <span class="required-star">*</span>
                                    <span class="required-badge">Obligatoire</span>
                                </label>
                                <div class="input-group">
                                    <span class="input-icon"><i class="fas fa-id-card"></i></span>
                                    <input type="text" class="form-control" name="matricule" 
                                           value="<?= e($_POST['matricule'] ?? '') ?>" 
                                           placeholder="Ex: SEC001" required>
                                </div>
                                <small class="text-muted">Doit être unique</small>
                            </div>
                            
                            <div class="col-md-6 form-group">
                                <label class="form-label">
                                    <i class="fas fa-calendar"></i> Date d'embauche
                                </label>
                                <div class="input-group">
                                    <span class="input-icon"><i class="fas fa-calendar"></i></span>
                                    <input type="date" class="form-control" name="date_embauche" 
                                           value="<?= $_POST['date_embauche'] ?? '' ?>">
                                </div>
                            </div>
                        </div>
                        
                        <!-- BOUTONS D'ACTION -->
                        <div class="btn-container">
                            <a href="secretaires.php" class="btn btn-outline-secondary">
                                <i class="fas fa-arrow-left me-2"></i> Annuler
                            </a>
                            <button type="reset" class="btn btn-secondary">
                                <i class="fas fa-undo me-2"></i> Réinitialiser
                            </button>
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save me-2"></i> Créer la secrétaire
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- ============================================= -->
<!-- SCRIPTS                                       -->
<!-- ============================================= -->
<script>
// Aperçu de la photo
document.getElementById('photo')?.addEventListener('change', function(e) {
    const file = e.target.files[0];
    if (file) {
        const reader = new FileReader();
        reader.onload = function(e) {
            document.getElementById('photoPreview').src = e.target.result;
        };
        reader.readAsDataURL(file);
    }
});

// Validation du formulaire
document.getElementById('secretaireForm')?.addEventListener('submit', function(e) {
    const matricule = document.querySelector('input[name="matricule"]').value;
    if (!matricule || matricule.trim() === '') {
        e.preventDefault();
        alert('Le matricule est obligatoire !');
    }
});

// Animation au scroll
window.addEventListener('scroll', function() {
    const cards = document.querySelectorAll('.form-group');
    cards.forEach(card => {
        const cardTop = card.getBoundingClientRect().top;
        const windowHeight = window.innerHeight;
        if (cardTop < windowHeight - 100) {
            card.style.opacity = '1';
            card.style.transform = 'translateY(0)';
        }
    });
});

// Effet de parallax
document.addEventListener('mousemove', function(e) {
    const x = (e.clientX / window.innerWidth) * 20;
    const y = (e.clientY / window.innerHeight) * 20;
    document.querySelector('.form-card').style.transform = `translate(${x/10}px, ${y/10}px)`;
});
</script>

<?php include '../../includes/footer.php'; ?>