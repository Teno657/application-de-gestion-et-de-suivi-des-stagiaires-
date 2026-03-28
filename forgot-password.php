<?php
/**
 * Page de mot de passe oublié
 */

require_once 'includes/config.php';
require_once 'includes/functions.php';
require_once 'includes/auth.php';

// =============================================
// AJOUT : Créer la table password_resets si elle n'existe pas
// =============================================
try {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS password_resets (
            id INT PRIMARY KEY AUTO_INCREMENT,
            email VARCHAR(255) NOT NULL,
            token VARCHAR(255) NOT NULL,
            expiry DATETIME NOT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_email (email),
            INDEX idx_token (token)
        )
    ");
} catch (Exception $e) {
    // Ignorer les erreurs de création
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $error = 'Token de sécurité invalide';
    } else {
        $email = cleanInput($_POST['email'] ?? '');
        
        if (empty($email)) {
            $error = 'Veuillez entrer votre adresse email';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Adresse email invalide';
        } else {
            // Vérifier si l'email existe dans utilisateurs
            $user = $pdo->prepare("SELECT * FROM utilisateurs WHERE email = ?");
            $user->execute([$email]);
            $user_data = $user->fetch();
            
            // Vérifier aussi dans administrateur
            if (!$user_data) {
                $admin = $pdo->prepare("SELECT * FROM administrateur WHERE email = ?");
                $admin->execute([$email]);
                $user_data = $admin->fetch();
            }
            
            if ($user_data) {
                // Générer un token unique
                $token = bin2hex(random_bytes(32));
                $expiry = date('Y-m-d H:i:s', strtotime('+1 hour'));
                
                // Supprimer les anciens tokens pour cet email
                $pdo->prepare("DELETE FROM password_resets WHERE email = ?")->execute([$email]);
                
                // Sauvegarder le token dans la base de données
                $stmt = $pdo->prepare("
                    INSERT INTO password_resets (email, token, expiry) 
                    VALUES (?, ?, ?)
                ");
                $stmt->execute([$email, $token, $expiry]);
                
                // Envoyer l'email
                $reset_link = APP_URL . "/reset-password.php?token=" . $token;
                $subject = "Réinitialisation de votre mot de passe - School-Connection";
                $message = "
                <html>
                <head>
                    <style>
                        body { font-family: Arial, sans-serif; }
                        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                        .header { background: linear-gradient(135deg, #667eea, #764ba2); padding: 20px; text-align: center; color: white; }
                        .content { padding: 30px; background: #f9f9f9; }
                        .button { display: inline-block; padding: 12px 30px; background: #667eea; color: white; text-decoration: none; border-radius: 5px; }
                        .footer { text-align: center; margin-top: 20px; font-size: 12px; color: #777; }
                    </style>
                </head>
                <body>
                    <div class='container'>
                        <div class='header'>
                            <h2>School-Connection</h2>
                        </div>
                        <div class='content'>
                            <h3>Bonjour " . htmlspecialchars($user_data['prenom'] . ' ' . $user_data['nom']) . ",</h3>
                            <p>Vous avez demandé la réinitialisation de votre mot de passe.</p>
                            <p>Cliquez sur le lien ci-dessous pour réinitialiser votre mot de passe :</p>
                            <center>
                                <a href='$reset_link' class='button'>Réinitialiser mon mot de passe</a>
                            </center>
                            <p>Ce lien est valable pendant 1 heure.</p>
                            <p>Si vous n'avez pas demandé cette réinitialisation, ignorez cet email.</p>
                            <hr>
                            <p><small>Si le bouton ne fonctionne pas, copiez ce lien :<br>$reset_link</small></p>
                        </div>
                        <div class='footer'>
                            <p>Cordialement,<br>L'équipe School-Connection</p>
                        </div>
                    </div>
                </body>
                </html>
                ";
                
                send_email($email, $subject, $message);
                
                $success = "Un email de réinitialisation a été envoyé à $email";
            } else {
                // Pour des raisons de sécurité, ne pas révéler si l'email existe ou non
                $success = "Si cette adresse email existe dans notre base, vous recevrez un email de réinitialisation.";
            }
        }
    }
}

$page_title = "Mot de passe oublié - School-Connection";
include 'includes/header-simple.php';
?>

<div class="container">
    <div class="row justify-content-center align-items-center min-vh-100">
        <div class="col-md-6 col-lg-5">
            <div class="text-center mb-4 animate__animated animate__fadeInDown">
                <!-- Logo School-Connection -->
                <div class="logo-wrapper mb-4">
                    <div class="logo-icon mx-auto">
                        <i class="fas fa-graduation-cap fa-3x text-white"></i>
                    </div>
                    <h1 class="display-4 fw-bold text-white mt-3" style="text-shadow: 2px 2px 10px rgba(0,0,0,0.2);">
                        School-Connection
                    </h1>
                </div>
                <div class="reset-card-icon">
                    <div class="icon-circle mb-3">
                        <i class="fas fa-key fa-2x"></i>
                    </div>
                    <h2 class="fw-bold mt-2" style="color: #2d3436;">Mot de passe oublié</h2>
                    <p class="text-muted">Recevez un lien pour réinitialiser votre mot de passe</p>
                </div>
            </div>
            
            <div class="card border-0 shadow-lg animate__animated animate__fadeInUp" style="border-radius: 25px; overflow: hidden;">
                <div class="card-body p-5">
                    <?php if ($error): ?>
                        <div class="alert alert-danger alert-dismissible fade show" role="alert">
                            <i class="fas fa-exclamation-circle me-2"></i><?= $error ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>
                    
                    <?php if ($success): ?>
                        <div class="alert alert-success alert-dismissible fade show" role="alert">
                            <i class="fas fa-check-circle me-2"></i><?= $success ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>
                    
                    <form method="POST" action="">
                        <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
                        
                        <div class="mb-4">
                            <label for="email" class="form-label fw-semibold">Adresse email</label>
                            <div class="input-group">
                                <span class="input-group-text bg-light border-0">
                                    <i class="fas fa-envelope text-primary"></i>
                                </span>
                                <input type="email" class="form-control form-control-lg border-0 bg-light" 
                                       id="email" name="email" 
                                       placeholder="exemple@email.com" 
                                       style="border-radius: 0 12px 12px 0;" required>
                            </div>
                            <div class="form-text mt-2">
                                <i class="fas fa-info-circle me-1"></i>
                                Entrez l'adresse email associée à votre compte.
                            </div>
                        </div>
                        
                        <button type="submit" class="btn btn-primary btn-lg w-100 mb-3 py-3" 
                                style="background: linear-gradient(135deg, #667eea, #764ba2); border: none; border-radius: 50px; transition: all 0.3s ease;">
                            <i class="fas fa-paper-plane me-2"></i>Envoyer le lien
                        </button>
                        
                        <div class="text-center">
                            <a href="login.php" class="text-decoration-none">
                                <i class="fas fa-arrow-left me-1"></i>Retour à la connexion
                            </a>
                        </div>
                    </form>
                    
                    <hr class="my-4">
                    
                    <div class="text-center">
                        <p class="text-muted mb-0">
                            <i class="fas fa-shield-alt me-1"></i>
                            Votre sécurité est notre priorité
                        </p>
                    </div>
                </div>
            </div>
            
            <div class="text-center mt-4">
                <a href="index.php" class="text-white text-decoration-none">
                    <i class="fas fa-home me-2"></i>Retour à l'accueil
                </a>
            </div>
        </div>
    </div>
</div>

<style>
    /* Animation personnalisée */
    @keyframes fadeInDown {
        from {
            opacity: 0;
            transform: translateY(-30px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }
    
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
            transform: scale(1);
            box-shadow: 0 0 0 0 rgba(102, 126, 234, 0.4);
        }
        70% {
            transform: scale(1.05);
            box-shadow: 0 0 0 15px rgba(102, 126, 234, 0);
        }
        100% {
            transform: scale(1);
            box-shadow: 0 0 0 0 rgba(102, 126, 234, 0);
        }
    }
    
    .animate__animated {
        animation-duration: 0.8s;
        animation-fill-mode: both;
    }
    
    .animate__fadeInDown {
        animation-name: fadeInDown;
    }
    
    .animate__fadeInUp {
        animation-name: fadeInUp;
    }
    
    body {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        min-height: 100vh;
    }
    
    .logo-wrapper {
        animation: pulse 2s infinite;
    }
    
    .logo-icon {
        width: 80px;
        height: 80px;
        background: rgba(255, 255, 255, 0.2);
        backdrop-filter: blur(10px);
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        border: 2px solid rgba(255, 255, 255, 0.3);
        transition: all 0.3s ease;
    }
    
    .logo-icon:hover {
        transform: scale(1.1);
        background: rgba(255, 255, 255, 0.3);
    }
    
    .reset-card-icon {
        position: relative;
    }
    
    .icon-circle {
        width: 70px;
        height: 70px;
        background: linear-gradient(135deg, #667eea, #764ba2);
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        margin: 0 auto;
        color: white;
        box-shadow: 0 10px 25px rgba(102, 126, 234, 0.3);
        transition: all 0.3s ease;
    }
    
    .icon-circle:hover {
        transform: rotate(15deg) scale(1.1);
    }
    
    .card {
        border-radius: 25px !important;
        transition: all 0.3s ease;
    }
    
    .card:hover {
        transform: translateY(-5px);
        box-shadow: 0 20px 40px rgba(0, 0, 0, 0.2) !important;
    }
    
    .form-control:focus {
        box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.2);
        border-color: #667eea;
        background-color: white !important;
    }
    
    .btn-primary {
        transition: all 0.3s ease;
        position: relative;
        overflow: hidden;
    }
    
    .btn-primary::before {
        content: '';
        position: absolute;
        top: 50%;
        left: 50%;
        width: 0;
        height: 0;
        border-radius: 50%;
        background: rgba(255, 255, 255, 0.3);
        transform: translate(-50%, -50%);
        transition: width 0.6s, height 0.6s;
    }
    
    .btn-primary:hover::before {
        width: 300px;
        height: 300px;
    }
    
    .btn-primary:hover {
        transform: translateY(-3px);
        box-shadow: 0 15px 30px rgba(102, 126, 234, 0.4);
    }
    
    .alert {
        border-radius: 15px;
        border: none;
        animation: slideInDown 0.5s ease;
    }
    
    @keyframes slideInDown {
        from {
            transform: translateY(-20px);
            opacity: 0;
        }
        to {
            transform: translateY(0);
            opacity: 1;
        }
    }
    
    .input-group-text {
        border-radius: 12px 0 0 12px !important;
    }
    
    hr {
        opacity: 0.5;
    }
    
    @media (max-width: 768px) {
        .card-body {
            padding: 2rem !important;
        }
        
        .logo-icon {
            width: 60px;
            height: 60px;
        }
        
        .logo-icon i {
            font-size: 2rem;
        }
        
        h1.display-4 {
            font-size: 1.8rem !important;
        }
        
        .icon-circle {
            width: 50px;
            height: 50px;
        }
        
        .icon-circle i {
            font-size: 1.5rem;
        }
        
        h2 {
            font-size: 1.5rem !important;
        }
    }
</style>

<?php include 'includes/footer-simple.php'; ?>