<?php
/**
 * Page d'accueil de School-Connection - Version Design Moderne
 */

require_once 'includes/config.php';
require_once 'includes/functions.php';

// Ajout de la fonction is_logged_in si elle n'existe pas
if (!function_exists('is_logged_in')) {
    function is_logged_in() {
        return isset($_SESSION['user_id']);
    }
}

// =============================================
// TRAITEMENT DU FORMULAIRE DE CONTACT
// =============================================
$contact_message = '';
$contact_error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['contact_submit'])) {
    // Vérifier le token CSRF
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $contact_error = 'Token de sécurité invalide. Veuillez réessayer.';
    } else {
        $nom = cleanInput($_POST['nom'] ?? '');
        $email = cleanInput($_POST['email'] ?? '');
        $sujet = cleanInput($_POST['sujet'] ?? '');
        $message = cleanInput($_POST['message'] ?? '');
        
        // Validation
        $errors = [];
        if (empty($nom)) $errors[] = 'Votre nom est requis';
        if (empty($email)) $errors[] = 'Votre email est requis';
        elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Email invalide';
        if (empty($sujet)) $errors[] = 'Le sujet est requis';
        if (empty($message)) $errors[] = 'Votre message est requis';
        
        if (empty($errors)) {
            try {
                $pdo->beginTransaction();
                
                // 1. Enregistrer le message dans la base de données
                $stmt = $pdo->prepare("
                    INSERT INTO messages_contact (nom, email, sujet, message, date_envoi)
                    VALUES (?, ?, ?, ?, NOW())
                ");
                $stmt->execute([$nom, $email, $sujet, $message]);
                $message_id = $pdo->lastInsertId();
                
                // 2. Récupérer tous les secrétaires
                $secretaires = $pdo->prepare("
                    SELECT u.id_utilisateur, u.email, u.nom, u.prenom
                    FROM utilisateurs u
                    WHERE u.role = 'secretaire' AND u.est_actif = 1
                ");
                $secretaires->execute();
                $liste_secretaires = $secretaires->fetchAll();
                
                // Si aucun secrétaire trouvé, envoyer à l'admin
                if (empty($liste_secretaires)) {
                    $admin = $pdo->query("SELECT email FROM administrateur LIMIT 1")->fetch();
                    if ($admin) {
                        $liste_secretaires = [['email' => $admin['email'], 'nom' => 'Administrateur', 'prenom' => '']];
                    }
                }
                
                // 3. Envoyer un email à chaque secrétaire
                $emails_envoyes = 0;
                foreach ($liste_secretaires as $secretaire) {
                    $subject = "[Contact School-Connection] $sujet";
                    $body = "
                        <html>
                        <head>
                            <meta charset='UTF-8'>
                            <style>
                                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                                .container { max-width: 600px; margin: 0 auto; padding: 20px; background: #f9f9f9; }
                                .header { background: linear-gradient(135deg, #667eea, #764ba2); color: white; padding: 20px; text-align: center; border-radius: 10px 10px 0 0; }
                                .content { padding: 20px; background: white; border-radius: 0 0 10px 10px; }
                                .info { background: #e8f4fd; padding: 15px; border-radius: 10px; margin: 15px 0; }
                                .button { display: inline-block; padding: 10px 20px; background: #667eea; color: white; text-decoration: none; border-radius: 5px; margin-top: 10px; }
                                .footer { text-align: center; padding: 15px; font-size: 12px; color: #777; border-top: 1px solid #eee; margin-top: 20px; }
                            </style>
                        </head>
                        <body>
                            <div class='container'>
                                <div class='header'>
                                    <h1>School-Connection</h1>
                                    <p>Nouveau message de contact</p>
                                </div>
                                <div class='content'>
                                    <p>Bonjour <strong>{$secretaire['prenom']} {$secretaire['nom']}</strong>,</p>
                                    <p>Vous avez reçu un nouveau message via le formulaire de contact :</p>
                                    <div class='info'>
                                        <p><strong>👤 Nom :</strong> {$nom}</p>
                                        <p><strong>📧 Email :</strong> {$email}</p>
                                        <p><strong>📝 Sujet :</strong> {$sujet}</p>
                                        <p><strong>💬 Message :</strong></p>
                                        <p style='background: #f5f5f5; padding: 10px; border-radius: 5px;'>{$message}</p>
                                    </div>
                                    <p>Pour répondre à cette personne, vous pouvez :</p>
                                    <ul>
                                        <li>Cliquer sur le bouton ci-dessous pour répondre directement</li>
                                        <li>Ou utiliser son email : <strong>{$email}</strong></li>
                                    </ul>
                                    <p style='text-align: center;'>
                                        <a href='" . APP_URL . "/dashboard/secretaire/messages-contact.php?id={$message_id}' class='button'>
                                            📝 Voir et répondre au message
                                        </a>
                                    </p>
                                    <br>
                                    <p>Cordialement,<br>L'équipe School-Connection</p>
                                </div>
                                <div class='footer'>
                                    <p>© " . date('Y') . " School-Connection - Tous droits réservés</p>
                                </div>
                            </div>
                        </body>
                        </html>
                    ";
                    
                    if (send_email($secretaire['email'], $subject, $body)) {
                        $emails_envoyes++;
                    }
                }
                
                // 4. Créer une notification pour les secrétaires
                foreach ($liste_secretaires as $secretaire) {
                    create_notification(
    $secretaire['id_utilisateur'],
    '📩 Nouveau message d\'un visiteur',
    "Vous avez reçu un nouveau message de $nom ($email) : $sujet",
    'info',
    "/dashboard/secretaire/messages-contact.php?id=$message_id"
);
                }
                
                $pdo->commit();
                
                if ($emails_envoyes > 0) {
                    $contact_message = "✅ Votre message a été envoyé avec succès. Nous vous répondrons dans les plus brefs délais.";
                    $_POST = array(); // Vider le formulaire
                } else {
                    $contact_error = "❌ Votre message a bien été enregistré, mais une erreur est survenue lors de l'envoi de l'email. Notre équipe le traitera quand même.";
                }
                
            } catch (Exception $e) {
                $pdo->rollBack();
                $contact_error = "❌ Une erreur technique est survenue. Veuillez réessayer.";
                error_log("Erreur formulaire contact: " . $e->getMessage());
            }
        } else {
            $contact_error = implode('<br>', $errors);
        }
    }
}
$page_title = "School-Connection - La plateforme de gestion de stages";

// Requête pour les encadreurs (limité à 6 pour l'accueil)
$sql = "SELECT u.*, e.profession, e.specialite, e.entreprise, e.disponible
        FROM utilisateurs u 
        INNER JOIN encadreurs e ON u.id_utilisateur = e.id_encadreur 
        WHERE u.role IN ('encadreur_pro', 'encadreur_acro') 
        AND u.est_actif = 1 
        LIMIT 6";

$stmt = $pdo->query($sql);
$encadreurs = $stmt->fetchAll();

// Statistiques globales
$stats = [
    'stagiaires' => $pdo->query("SELECT COUNT(*) FROM stagiaires")->fetchColumn(),
    'encadreurs' => $pdo->query("SELECT COUNT(*) FROM encadreurs")->fetchColumn(),
    'entreprises' => $pdo->query("SELECT COUNT(DISTINCT entreprise) as total FROM encadreurs WHERE entreprise IS NOT NULL")->fetch()['total']
];

// Témoignages
$temoignages = [];
try {
    $temoignages = $pdo->query("
        SELECT u.nom, u.prenom, u.photo, t.commentaire, t.note 
        FROM temoignages t 
        JOIN utilisateurs u ON t.id_utilisateur = u.id_utilisateur 
        WHERE t.valide = 1 
        ORDER BY t.date_creation DESC 
        LIMIT 3
    ")->fetchAll();
} catch (PDOException $e) {
    $temoignages = [];
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $page_title ?></title>
    
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Font Awesome 6 -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Google Fonts - Inter et Poppins -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- Animate.css -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css">
    
    <!-- AOS (Animate On Scroll) -->
    <link href="https://unpkg.com/aos@2.3.1/dist/aos.css" rel="stylesheet">
    
    <style>
        :root {
            --primary: #4361ee;
            --secondary: #3f37c9;
            --success: #4cc9f0;
            --danger: #f72585;
            --warning: #f8961e;
            --info: #4895ef;
            --dark: #1e1e2f;
            --light: #f8f9fa;
        }
        
        body {
            font-family: 'Inter', sans-serif;
            overflow-x: hidden;
            color: #333;
        }
        
        h1, h2, h3, h4, h5, h6 {
            font-family: 'Poppins', sans-serif;
            font-weight: 600;
        }
        
        /* Navbar personnalisée */
        .navbar {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            box-shadow: 0 2px 20px rgba(0,0,0,0.1);
            transition: all 0.3s ease;
        }
        
        .navbar.scrolled {
            padding: 10px 0;
            background: rgba(255, 255, 255, 0.98);
        }
        
        .navbar-brand {
            font-weight: 700;
            font-size: 1.8rem;
            color: var(--primary) !important;
            letter-spacing: -0.5px;
        }
        
        .navbar-brand i {
            color: var(--primary);
            margin-right: 8px;
        }
        
        .nav-link {
            font-weight: 500;
            color: #333 !important;
            margin: 0 10px;
            position: relative;
            transition: all 0.3s ease;
        }
        
        .nav-link:after {
            content: '';
            position: absolute;
            bottom: -2px;
            left: 50%;
            transform: translateX(-50%);
            width: 0;
            height: 2px;
            background: var(--primary);
            transition: width 0.3s ease;
        }
        
        .nav-link:hover:after {
            width: 100%;
        }
        
        .nav-link:hover {
            color: var(--primary) !important;
        }
        
        .btn-custom-primary {
            background: var(--primary);
            color: white;
            border: none;
            padding: 12px 30px;
            border-radius: 50px;
            font-weight: 600;
            transition: all 0.3s ease;
            box-shadow: 0 5px 15px rgba(67, 97, 238, 0.3);
        }
        
        .btn-custom-primary:hover {
            background: var(--secondary);
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(67, 97, 238, 0.4);
            color: white;
        }
        
        .btn-custom-outline {
            background: transparent;
            color: var(--primary);
            border: 2px solid var(--primary);
            padding: 12px 30px;
            border-radius: 50px;
            font-weight: 600;
            transition: all 0.3s ease;
        }
        
        .btn-custom-outline:hover {
            background: var(--primary);
            color: white;
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(67, 97, 238, 0.3);
        }
        
        /* Hero Section */
        .hero-section {
            position: relative;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            padding: 150px 0 100px;
            overflow: hidden;
        }
        
        .hero-section:before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: url('https://images.unsplash.com/photo-1522071820081-009f0129c71c?auto=format&fit=crop&w=1950&q=80') center/cover;
            opacity: 0.1;
            animation: zoomInOut 20s infinite alternate;
        }
        
        @keyframes zoomInOut {
            0% { transform: scale(1); }
            100% { transform: scale(1.1); }
        }
        
        .hero-content {
            position: relative;
            z-index: 2;
        }
        
        .hero-title {
            font-size: 3.5rem;
            font-weight: 800;
            line-height: 1.2;
            color: white;
            text-shadow: 2px 2px 20px rgba(0,0,0,0.3);
            animation: fadeInUp 1s ease;
        }
        
        .hero-subtitle {
            font-size: 1.2rem;
            color: rgba(255,255,255,0.9);
            margin: 25px 0;
            animation: fadeInUp 1s ease 0.2s both;
        }
        
        .hero-image {
            position: relative;
            z-index: 2;
            animation: float 6s ease-in-out infinite;
        }
        
        @keyframes float {
            0% { transform: translateY(0px); }
            50% { transform: translateY(-20px); }
            100% { transform: translateY(0px); }
        }
        
        /* Cartes statistiques */
        .stat-card {
            background: rgba(255,255,255,0.1);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255,255,255,0.2);
            border-radius: 20px;
            padding: 30px 20px;
            transition: all 0.3s ease;
        }
        
        .stat-card:hover {
            transform: translateY(-10px);
            background: rgba(255,255,255,0.2);
            border-color: rgba(255,255,255,0.3);
        }
        
        .stat-number {
            font-size: 2.5rem;
            font-weight: 800;
            color: white;
            margin-bottom: 10px;
        }
        
        .stat-label {
            color: rgba(255,255,255,0.8);
            font-size: 1rem;
            font-weight: 500;
        }
        
        /* Cartes encadreurs */
        .mentor-card {
            background: white;
            border-radius: 20px;
            overflow: hidden;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            transition: all 0.4s ease;
            position: relative;
        }
        
        .mentor-card:hover {
            transform: translateY(-15px);
            box-shadow: 0 20px 40px rgba(67, 97, 238, 0.2);
        }
        
        .mentor-image {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            object-fit: cover;
            border: 4px solid var(--primary);
            margin: -60px auto 20px;
            position: relative;
            z-index: 2;
            background: white;
            transition: all 0.3s ease;
        }
        
        .mentor-card:hover .mentor-image {
            transform: scale(1.1);
            border-color: var(--secondary);
        }
        
        .mentor-badge {
            position: absolute;
            top: 20px;
            right: 20px;
            background: var(--success);
            color: white;
            padding: 5px 15px;
            border-radius: 50px;
            font-size: 0.8rem;
            font-weight: 600;
        }
        
        /* Témoignages */
        .testimonial-card {
            background: white;
            border-radius: 20px;
            padding: 30px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            transition: all 0.3s ease;
            border: 1px solid rgba(67, 97, 238, 0.1);
        }
        
        .testimonial-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 40px rgba(67, 97, 238, 0.2);
        }
        
        .testimonial-image {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            object-fit: cover;
            border: 3px solid var(--primary);
        }
        
        /* Section fonctionnalités */
        .feature-box {
            background: white;
            border-radius: 20px;
            padding: 40px 30px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            transition: all 0.3s ease;
            height: 100%;
            border: 1px solid rgba(67, 97, 238, 0.1);
        }
        
        .feature-box:hover {
            transform: translateY(-10px);
            box-shadow: 0 20px 40px rgba(67, 97, 238, 0.2);
        }
        
        .feature-icon {
            width: 80px;
            height: 80px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 25px;
            color: white;
            font-size: 2rem;
            transition: all 0.3s ease;
        }
        
        .feature-box:hover .feature-icon {
            transform: rotateY(180deg);
        }
        
        /* Section contact */
        .contact-section {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            position: relative;
            overflow: hidden;
        }
        
        .contact-section:before {
            content: '';
            position: absolute;
            top: -50%;
            right: -50%;
            width: 100%;
            height: 200%;
            background: rgba(255,255,255,0.1);
            transform: rotate(45deg);
        }
        
        .contact-card {
            background: white;
            border-radius: 30px;
            padding: 50px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.2);
        }
        
        .form-control {
            border: 2px solid #e0e0e0;
            border-radius: 15px;
            padding: 15px 20px;
            font-size: 1rem;
            transition: all 0.3s ease;
        }
        
        .form-control:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 0.2rem rgba(67, 97, 238, 0.25);
        }
        
        .btn-submit {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            padding: 15px 30px;
            border-radius: 15px;
            font-weight: 600;
            font-size: 1.1rem;
            transition: all 0.3s ease;
        }
        
        .btn-submit:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(67, 97, 238, 0.4);
        }
        
        /* Footer */
        .footer {
            background: #1e1e2f;
            color: white;
            padding: 80px 0 30px;
        }
        
        .social-icon {
            width: 45px;
            height: 45px;
            background: rgba(255,255,255,0.1);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1.2rem;
            transition: all 0.3s ease;
            margin: 0 5px;
        }
        
        .social-icon:hover {
            background: var(--primary);
            color: white;
            transform: translateY(-5px);
        }
        
        /* Animations */
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
        
        /* Responsive */
        @media (max-width: 768px) {
            .hero-title {
                font-size: 2.2rem;
            }
            
            .navbar-brand {
                font-size: 1.4rem;
            }
        }
    </style>
</head>
<body>

<!-- Navbar -->
<nav class="navbar navbar-expand-lg fixed-top">
    <div class="container">
        <a class="navbar-brand" href="index.php">
            <i class="fas fa-graduation-cap"></i> School-Connection
        </a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav ms-auto align-items-center">
                <li class="nav-item">
                    <a class="nav-link" href="#accueil">Accueil</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="#encadrants">Encadrants</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="#fonctionnalites">Fonctionnalités</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="#contact">Contact</a>
                </li>
                <?php if (is_logged_in()): ?>
                    <li class="nav-item">
                        <a class="btn btn-custom-primary ms-3" href="dashboard/index.php">
                            <i class="fas fa-tachometer-alt"></i> Dashboard
                        </a>
                    </li>
                <?php else: ?>
                    <li class="nav-item">
                        <a class="btn btn-custom-outline ms-3" href="login.php">
                            <i class="fas fa-sign-in-alt"></i> Connexion
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="btn btn-custom-primary ms-2" href="register.php">
                            <i class="fas fa-user-plus"></i> Inscription
                        </a>
                    </li>
                <?php endif; ?>
            </ul>
        </div>
    </div>
</nav>

<!-- Hero Section -->
<section id="accueil" class="hero-section">
    <div class="container">
        <div class="row align-items-center min-vh-100">
            <div class="col-lg-6 hero-content" data-aos="fade-right">
                <h1 class="hero-title">
                    La connexion entre l'école et l'entreprise
                </h1>
                <p class="hero-subtitle">
                    School-Connection : La plateforme qui maintient le lien entre encadrants et stagiaires, 
                    même après le stage.
                </p>
                <div class="d-flex gap-3 flex-wrap">
                    <a href="register.php?type=stagiaire" class="btn btn-custom-primary btn-lg">
                        <i class="fas fa-user-graduate me-2"></i>Je suis stagiaire
                    </a>
                    <a href="register.php?type=encadreur" class="btn btn-custom-primary btn-lg">
                        <i class="fas fa-chalkboard-teacher me-2"></i>Je suis encadreur
                    </a>
                </div>
                
                <!-- Statistiques -->
                <div class="row g-4 mt-5">
                    <div class="col-4">
                        <div class="stat-card text-center" data-aos="zoom-in" data-aos-delay="100">
                            <div class="stat-number counter" data-target="<?= $stats['stagiaires'] ?>">0</div>
                            <div class="stat-label">Stagiaires</div>
                        </div>
                    </div>
                    <div class="col-4">
                        <div class="stat-card text-center" data-aos="zoom-in" data-aos-delay="200">
                            <div class="stat-number counter" data-target="<?= $stats['encadreurs'] ?>">0</div>
                            <div class="stat-label">Encadreurs</div>
                        </div>
                    </div>
                    <div class="col-4">
                        <div class="stat-card text-center" data-aos="zoom-in" data-aos-delay="300">
                            <div class="stat-number counter" data-target="<?= $stats['entreprises'] ?>">0</div>
                            <div class="stat-label">Entreprises</div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-lg-6 hero-image" data-aos="fade-left">
                <img src="https://images.unsplash.com/photo-1522071820081-009f0129c71c?auto=format&fit=crop&w=800&q=80" 
                     alt="Hero Illustration" class="img-fluid rounded-4 shadow-lg">
            </div>
        </div>
    </div>
</section>

<!-- Section Encadrants -->
<section id="encadrants" class="py-5 bg-light">
    <div class="container">
        <div class="text-center mb-5" data-aos="fade-up">
            <h2 class="display-5 fw-bold">Nos Encadrants Experts</h2>
            <p class="lead text-muted">
                Intégrez des classes pour profiter de l'expertise de nos encadrants.
            </p>
        </div>
        
        <div class="row g-4">
            <?php foreach ($encadreurs as $index => $encadreur): ?>
            <div class="col-lg-4 col-md-6" data-aos="fade-up" data-aos-delay="<?= $index * 100 ?>">
                <div class="mentor-card">
                    <img src="https://images.unsplash.com/photo-1517694712202-14dd9538aa97?auto=format&fit=crop&w=400&q=80" 
                         alt="Ordinateur portable" class="img-fluid w-100" style="height: 150px; object-fit: cover;">
                    <div class="text-center p-4">
                        <?php if ($encadreur['disponible']): ?>
                            <span class="mentor-badge">Disponible</span>
                        <?php endif; ?>
                        <img src="<?= getPhotoUrl($encadreur['photo']) ?>" 
                             alt="<?= $encadreur['prenom'] ?>" 
                             class="mentor-image">
                        <h5 class="fw-bold mb-1"><?= $encadreur['prenom'] ?> <?= $encadreur['nom'] ?></h5>
                        <p class="text-primary mb-2"><?= $encadreur['profession'] ?></p>
                        <p class="text-muted small"><?= $encadreur['entreprise'] ?></p>
                        <div class="mb-3">
                            <span class="badge bg-primary bg-opacity-10 text-primary p-2">
                                <?= $encadreur['specialite'] ?>
                            </span>
                        </div>
                        <button class="btn btn-outline-primary btn-sm" onclick="voirEncadreur(<?= $encadreur['id_utilisateur'] ?>)">
                            Voir le profil <i class="fas fa-arrow-right ms-1"></i>
                        </button>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

        <div class="text-center mt-5" data-aos="fade-up">
            <a href="encadreurs-liste.php" class="btn btn-custom-primary btn-lg">
                Voir tous les encadrants <i class="fas fa-arrow-right ms-2"></i>
            </a>
        </div>
    </div>
</section>

<!-- Section Fonctionnalités -->
<section id="fonctionnalites" class="py-5">
    <div class="container">
        <div class="text-center mb-5" data-aos="fade-up">
            <h2 class="display-5 fw-bold">Une plateforme complète</h2>
            <p class="lead text-muted">Tout ce dont vous avez besoin pour gérer vos stages</p>
        </div>
        
        <div class="row g-4">
            <div class="col-md-4" data-aos="flip-left" data-aos-delay="100">
                <div class="feature-box text-center">
                    <div class="feature-icon">
                        <i class="fas fa-tasks"></i>
                    </div>
                    <h4 class="fw-bold">Gestion des tâches</h4>
                    <p class="text-muted">Assignez et suivez les tâches de vos stagiaires en temps réel.</p>
                </div>
            </div>
            <div class="col-md-4" data-aos="flip-left" data-aos-delay="200">
                <div class="feature-box text-center">
                    <div class="feature-icon">
                        <i class="fas fa-comments"></i>
                    </div>
                    <h4 class="fw-bold">Messagerie intégrée</h4>
                    <p class="text-muted">Communiquez facilement avec tous les acteurs de la plateforme.</p>
                </div>
            </div>
            <div class="col-md-4" data-aos="flip-left" data-aos-delay="300">
                <div class="feature-box text-center">
                    <div class="feature-icon">
                        <i class="fas fa-file-pdf"></i>
                    </div>
                    <h4 class="fw-bold">Attestations automatiques</h4>
                    <p class="text-muted">Générez automatiquement les attestations de fin de stage.</p>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Section Témoignages -->
<?php if (!empty($temoignages)): ?>
<section class="py-5 bg-light">
    <div class="container">
        <div class="text-center mb-5" data-aos="fade-up">
            <h2 class="display-5 fw-bold">Ce qu'ils disent de nous</h2>
            <p class="lead text-muted">Découvrez les avis de nos utilisateurs</p>
        </div>
        
        <div class="row g-4">
            <?php foreach ($temoignages as $index => $temoignage): ?>
            <div class="col-md-4" data-aos="fade-up" data-aos-delay="<?= $index * 100 ?>">
                <div class="testimonial-card">
                    <div class="d-flex align-items-center mb-3">
                        <img src="<?= getPhotoUrl($temoignage['photo']) ?>" 
                             alt="<?= $temoignage['prenom'] ?>" 
                             class="testimonial-image me-3">
                        <div>
                            <h6 class="fw-bold mb-1"><?= $temoignage['prenom'] ?> <?= $temoignage['nom'] ?></h6>
                            <div class="text-warning">
                                <?php for ($i = 1; $i <= 5; $i++): ?>
                                    <i class="fas fa-star<?= $i <= $temoignage['note'] ? '' : '-o' ?>"></i>
                                <?php endfor; ?>
                            </div>
                        </div>
                    </div>
                    <p class="text-muted mb-0">"<?= truncate($temoignage['commentaire'], 150) ?>"</p>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>
<?php endif; ?>

<!-- Section Contact -->
<section id="contact" class="contact-section py-5">
    <div class="container position-relative">
        <div class="row justify-content-center">
            <div class="col-lg-8">
                <div class="contact-card" data-aos="zoom-in">
                    <h3 class="fw-bold mb-4 text-center">Contactez-nous</h3>
                    <p class="text-center text-muted mb-5">
                        Une question ? Une suggestion ? N'hésitez pas à nous contacter.
                    </p>
                    
                    <div class="row g-4 mb-5">
                        <div class="col-md-4 text-center">
                            <div class="feature-icon mb-3" style="width: 60px; height: 60px; font-size: 1.5rem;">
                                <i class="fas fa-map-marker-alt"></i>
                            </div>
                            <h6 class="fw-bold">Adresse</h6>
                            <p class="text-muted">Yaoundé, Cameroun</p>
                        </div>
                        <div class="col-md-4 text-center">
                            <div class="feature-icon mb-3" style="width: 60px; height: 60px; font-size: 1.5rem;">
                                <i class="fas fa-phone"></i>
                            </div>
                            <h6 class="fw-bold">Téléphone</h6>
                            <p class="text-muted">(+237) 6 50 13 31 45</p>
                        </div>
                        <div class="col-md-4 text-center">
                            <div class="feature-icon mb-3" style="width: 60px; height: 60px; font-size: 1.5rem;">
                                <i class="fas fa-envelope"></i>
                            </div>
                            <h6 class="fw-bold">Email</h6>
                            <p class="text-muted">school-connection@icloud.com</p>
                        </div>
                    </div>
                    
                    <?php if ($contact_message): ?>
                        <div class="alert alert-success alert-dismissible fade show" role="alert">
                            <i class="fas fa-check-circle me-2"></i><?= $contact_message ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>
                    
                    <?php if ($contact_error): ?>
                        <div class="alert alert-danger alert-dismissible fade show" role="alert">
                            <i class="fas fa-exclamation-circle me-2"></i><?= $contact_error ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>
                    
                    <form method="POST" action="" id="contactForm">
                        <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
                        <input type="hidden" name="contact_submit" value="1">
                        
                        <div class="row g-3">
                            <div class="col-md-6">
                                <input type="text" class="form-control" name="nom" placeholder="Votre nom" value="<?= e($_POST['nom'] ?? '') ?>" required>
                            </div>
                            <div class="col-md-6">
                                <input type="email" class="form-control" name="email" placeholder="Votre email" value="<?= e($_POST['email'] ?? '') ?>" required>
                            </div>
                            <div class="col-12">
                                <input type="text" class="form-control" name="sujet" placeholder="Sujet" value="<?= e($_POST['sujet'] ?? '') ?>" required>
                            </div>
                            <div class="col-12">
                                <textarea class="form-control" rows="5" name="message" placeholder="Votre message" required><?= e($_POST['message'] ?? '') ?></textarea>
                            </div>
                            <div class="col-12 text-center">
                                <button type="submit" class="btn btn-submit btn-lg px-5">
                                    <i class="fas fa-paper-plane me-2"></i>Envoyer
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Footer -->
<footer class="footer">
    <div class="container">
        <div class="row g-4">
            <div class="col-lg-4">
                <h5 class="fw-bold mb-3">School-Connection</h5>
                <p class="text-white-50">
                    La plateforme innovante qui connecte les stagiaires et les encadreurs pour 
                    une expérience de stage enrichissante et durable.
                </p>
            </div>
            <div class="col-lg-2">
                <h6 class="fw-bold mb-3">Liens rapides</h6>
                <ul class="list-unstyled">
                    <li class="mb-2"><a href="#accueil" class="text-white-50 text-decoration-none">Accueil</a></li>
                    <li class="mb-2"><a href="#encadrants" class="text-white-50 text-decoration-none">Encadrants</a></li>
                    <li class="mb-2"><a href="#fonctionnalites" class="text-white-50 text-decoration-none">Fonctionnalités</a></li>
                    <li class="mb-2"><a href="#contact" class="text-white-50 text-decoration-none">Contact</a></li>
                </ul>
            </div>
            <div class="col-lg-3">
                <h6 class="fw-bold mb-3">Informations légales</h6>
                <ul class="list-unstyled">
                    <li class="mb-2"><a href="mentions-legales.php" class="text-white-50 text-decoration-none">Mentions légales</a></li>
                    <li class="mb-2"><a href="politique-confidentialite.php" class="text-white-50 text-decoration-none">Politique de confidentialité</a></li>
                    <li class="mb-2"><a href="cgu.php" class="text-white-50 text-decoration-none">CGU</a></li>
                </ul>
            </div>
            <div class="col-lg-3">
                <h6 class="fw-bold mb-3">Suivez-nous</h6>
                <div class="d-flex">
                    <a href="#" class="social-icon"><i class="fab fa-facebook-f"></i></a>
                    <a href="#" class="social-icon"><i class="fab fa-twitter"></i></a>
                    <a href="#" class="social-icon"><i class="fab fa-linkedin-in"></i></a>
                    <a href="#" class="social-icon"><i class="fab fa-instagram"></i></a>
                </div>
            </div>
        </div>
        
        <hr class="border-white-50 my-4">
        
        <div class="row">
            <div class="col-md-6">
                <p class="text-white-50 mb-0">
                    &copy; <?= date('Y') ?> School-Connection. Tous droits réservés.
                </p>
            </div>
            <div class="col-md-6 text-md-end">
            </div>
        </div>
    </div>
</footer>

<!-- Scripts -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://unpkg.com/aos@2.3.1/dist/aos.js"></script>

<script>
// Initialisation de AOS
AOS.init({
    duration: 1000,
    once: true
});

// Navbar scroll effect
window.addEventListener('scroll', function() {
    const navbar = document.querySelector('.navbar');
    if (window.scrollY > 50) {
        navbar.classList.add('scrolled');
    } else {
        navbar.classList.remove('scrolled');
    }
});

// Counter animation
function animateCounter(element, target) {
    let current = 0;
    const increment = target / 100;
    const timer = setInterval(() => {
        current += increment;
        if (current >= target) {
            element.textContent = target;
            clearInterval(timer);
        } else {
            element.textContent = Math.floor(current);
        }
    }, 10);
}

// Démarrer les compteurs quand ils sont visibles
const observerOptions = {
    threshold: 0.5
};

const observer = new IntersectionObserver((entries) => {
    entries.forEach(entry => {
        if (entry.isIntersecting) {
            const counter = entry.target;
            const target = parseInt(counter.dataset.target);
            animateCounter(counter, target);
            observer.unobserve(counter);
        }
    });
}, observerOptions);

document.querySelectorAll('.counter').forEach(counter => {
    observer.observe(counter);
});

// Fonction pour voir les détails d'un encadreur
function voirEncadreur(id) {
    window.location.href = 'encadreur-details.php?id=' + id;
}
</script>

</body>
</html>