<?php
/**
 * Ajouter une filière (Admin)
 */

require_once '../../includes/config.php';
require_once '../../includes/functions.php';
require_once '../../includes/auth.php';

// Vérifier le rôle
require_role('admin');

$page_title = "Ajouter une filière - School-Connection";

$error = '';
$success = '';

// Récupérer la liste des départements pour le select
$departements = [
    'Informatique',
    'Réseaux et Télécommunications',
    'Gestion et Finance',
    'Marketing et Communication',
    'Ressources Humaines',
    'Droit',
    'Comptabilité',
    'Architecture',
    'Médecine',
    'Infographie',
    'Autre'
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $error = 'Token de sécurité invalide';
    } else {
        $nom_filiere = cleanInput($_POST['nom_filiere']);
        $description = cleanInput($_POST['description'] ?? '');
        $departement = cleanInput($_POST['departement'] ?? '');
        $duree_stage_recommandee = (int)($_POST['duree_stage_recommandee'] ?? 0);
        $actif = isset($_POST['actif']) ? 1 : 0;
        
        if (empty($nom_filiere)) {
            $error = 'Le nom de la filière est requis';
        } else {
            try {
                // Vérifier si la filière existe déjà
                $stmt = $pdo->prepare("SELECT COUNT(*) FROM filieres WHERE nom_filiere = ?");
                $stmt->execute([$nom_filiere]);
                if ($stmt->fetchColumn() > 0) {
                    $error = 'Une filière avec ce nom existe déjà';
                } else {
                    $stmt = $pdo->prepare("
                        INSERT INTO filieres (nom_filiere, description, departement, duree_stage_recommandee, actif, date_creation)
                        VALUES (?, ?, ?, ?, ?, NOW())
                    ");
                    $stmt->execute([$nom_filiere, $description, $departement, $duree_stage_recommandee, $actif]);
                    
                    $_SESSION['flash'] = ['type' => 'success', 'message' => 'Filière ajoutée avec succès'];
                    redirect('filieres.php');
                }
            } catch (PDOException $e) {
                $error = 'Erreur: ' . $e->getMessage();
            }
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
    
    .hero-form {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        border-radius: 30px;
        padding: 50px;
        margin-bottom: 40px;
        position: relative;
        overflow: hidden;
    }
    
    .hero-form::before {
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
        padding: 40px;
        box-shadow: 0 20px 40px rgba(0,0,0,0.1);
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
    
    .section-title {
        font-size: 1.1rem;
        font-weight: 700;
        color: #667eea;
        margin-bottom: 25px;
        padding-bottom: 12px;
        border-bottom: 2px solid #e5e7eb;
        display: flex;
        align-items: center;
        gap: 10px;
    }
    
    .required-star {
        color: #ef4444;
        margin-left: 4px;
    }
    
    .form-check-input:checked {
        background-color: #10b981;
        border-color: #10b981;
    }
    
    .help-text {
        font-size: 0.75rem;
        color: #9ca3af;
        margin-top: 5px;
    }
    
    @media (max-width: 768px) {
        .hero-form { padding: 30px; }
        .form-card { padding: 25px; }
    }
</style>

<div class="container-fluid">
    <!-- Hero Section -->
    <div class="hero-form animate-fadeInUp">
        <div class="row align-items-center">
            <div class="col-md-8">
                <h1 class="display-4 fw-bold text-white mb-3">
                    <i class="fas fa-plus-circle me-3"></i>
                    Ajouter une filière
                </h1>
                <p class="lead text-white-50 mb-0">
                    Créez une nouvelle filière pour l'organisation des stages
                </p>
            </div>
            <div class="col-md-4 text-md-end mt-3 mt-md-0">
                <a href="filieres.php" class="btn btn-light btn-lg rounded-pill px-4 shadow-sm">
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
                
                <form method="POST" action="">
                    <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
                    
                    <!-- Informations principales -->
                    <div class="section-title">
                        <i class="fas fa-info-circle"></i>
                        Informations générales
                    </div>
                    
                    <div class="mb-4">
                        <label class="form-label">
                            Nom de la filière <span class="required-star">*</span>
                        </label>
                        <input type="text" 
                               class="form-control" 
                               name="nom_filiere" 
                               placeholder="Ex: Informatique, Gestion, Marketing..."
                               value="<?= e($_POST['nom_filiere'] ?? '') ?>"
                               required>
                        <div class="help-text">
                            <i class="fas fa-info-circle me-1"></i>
                            Nom unique qui identifie la filière
                        </div>
                    </div>
                    
                  
                    
                    <!-- Description -->
                    <div class="section-title mt-4">
                        <i class="fas fa-align-left"></i>
                        Description
                    </div>
                    
                    <div class="mb-4">
                        <label class="form-label">
                            Description de la filière
                        </label>
                        <textarea class="form-control" 
                                  name="description" 
                                  rows="5" 
                                  placeholder="Décrivez les objectifs, les débouchés, les compétences acquises..."><?= e($_POST['description'] ?? '') ?></textarea>
                        <div class="help-text">
                            <i class="fas fa-quote-left me-1"></i>
                            Une description complète pour présenter la filière
                        </div>
                    </div>
                
                    
                    
                    <!-- Boutons d'action -->
                    <div class="d-flex gap-3 mt-4">
                        <button type="submit" class="btn btn-submit text-white flex-grow-1">
                            <i class="fas fa-save me-2"></i>
                            Enregistrer la filière
                        </button>
                        <a href="filieres.php" class="btn btn-cancel">
                            <i class="fas fa-times me-2"></i>
                            Annuler
                        </a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
    // Validation du formulaire côté client
    document.querySelector('form').addEventListener('submit', function(e) {
        const nomFiliere = document.querySelector('input[name="nom_filiere"]').value.trim();
        
        if (!nomFiliere) {
            e.preventDefault();
            alert('Veuillez saisir le nom de la filière');
            return false;
        }
        
        if (nomFiliere.length < 3) {
            e.preventDefault();
            alert('Le nom de la filière doit contenir au moins 3 caractères');
            return false;
        }
        
        return true;
    });
    
    // Animation des champs au focus
    document.querySelectorAll('.form-control, .form-select').forEach(input => {
        input.addEventListener('focus', function() {
            this.parentElement.classList.add('focused');
        });
        input.addEventListener('blur', function() {
            this.parentElement.classList.remove('focused');
        });
    });
</script>

<?php include '../../includes/footer.php'; ?>