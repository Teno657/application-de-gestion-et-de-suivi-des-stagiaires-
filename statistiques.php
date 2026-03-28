<?php
/**
 * Statistiques (Secrétaire)
 */

require_once '../../includes/config.php';
require_once '../../includes/functions.php';
require_once '../../includes/auth.php';

// Vérifier le rôle
require_role('secretaire');

$page_title = "Statistiques - School-Connection";

// Période
$periode = $_GET['periode'] ?? 'mois';
$date_debut = $_GET['date_debut'] ?? date('Y-m-d', strtotime('-30 days'));
$date_fin = $_GET['date_fin'] ?? date('Y-m-d');

// Statistiques générales
$stats = [
    'total_stagiaires' => $pdo->query("SELECT COUNT(*) FROM stagiaires")->fetchColumn(),
    'stagiaires_actifs' => $pdo->query("SELECT COUNT(*) FROM stagiaires WHERE statut_inscription = 'actif'")->fetchColumn(),
    'stagiaires_termines' => $pdo->query("SELECT COUNT(*) FROM stagiaires WHERE statut_inscription = 'termine'")->fetchColumn(),
    'stagiaires_attente' => $pdo->query("SELECT COUNT(*) FROM stagiaires WHERE statut_inscription = 'en_attente'")->fetchColumn(),
    
    'total_encadreurs' => $pdo->query("SELECT COUNT(*) FROM encadreurs")->fetchColumn(),
    'encadreurs_disponibles' => $pdo->query("SELECT COUNT(*) FROM encadreurs WHERE disponible = 1")->fetchColumn(),
    
    'attestations' => $pdo->query("SELECT COUNT(*) FROM attestations")->fetchColumn(),
    'attestations_mois' => $pdo->query("SELECT COUNT(*) FROM attestations WHERE MONTH(date_emission) = MONTH(NOW())")->fetchColumn(),
];

// Évolution des inscriptions
$evolution_inscriptions = $pdo->prepare("
    SELECT DATE(date_creation) as date, COUNT(*) as total
    FROM utilisateurs
    WHERE role = 'stagiaire' AND date_creation BETWEEN ? AND ?
    GROUP BY DATE(date_creation)
    ORDER BY date
");
$evolution_inscriptions->execute([$date_debut . ' 00:00:00', $date_fin . ' 23:59:59']);
$inscriptions = $evolution_inscriptions->fetchAll();

// Répartition par filière
$repartition_filieres = $pdo->query("
    SELECT filiere, COUNT(*) as total
    FROM stagiaires
    WHERE filiere IS NOT NULL
    GROUP BY filiere
    ORDER BY total DESC
")->fetchAll();

// Stages par mois
$stages_par_mois = $pdo->query("
    SELECT 
        DATE_FORMAT(date_debut, '%Y-%m') as mois,
        COUNT(*) as debuts,
        SUM(CASE WHEN statut_inscription = 'termine' THEN 1 ELSE 0 END) as termines
    FROM stagiaires
    GROUP BY DATE_FORMAT(date_debut, '%Y-%m')
    ORDER BY mois DESC
    LIMIT 12
")->fetchAll();

// Encadreurs les plus sollicités
$top_encadreurs = $pdo->query("
    SELECT u.nom, u.prenom, e.profession, COUNT(r.id_stagiaire) as nb_stagiaires
    FROM encadreurs e
    JOIN utilisateurs u ON e.id_encadreur = u.id_utilisateur
    LEFT JOIN relations_encadrement r ON e.id_encadreur = r.id_encadreur AND r.statut = 'active'
    GROUP BY e.id_encadreur
    ORDER BY nb_stagiaires DESC
    LIMIT 10
")->fetchAll();

include '../../includes/header.php';
?>

<!-- ✅ CORRECTION : Espacement pour éviter que le contenu soit caché sous la navbar -->
<style>
    .container-fluid:first-of-type {
        margin-top: 20px;
        padding-top: 10px;
    }
    
    @media (max-width: 768px) {
        .container-fluid:first-of-type {
            margin-top: 10px;
        }
    }
</style>

<div class="container-fluid">
    <!-- Le reste du contenu -->

<div class="container-fluid">
    <div class="row mb-4">
        <div class="col-12">
            <h1 class="h3">Statistiques</h1>
            <p class="text-muted">Analyse des données de la plateforme</p>
        </div>
    </div>
    
    <!-- Filtres de période -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <form method="GET" class="row g-3">
                        <div class="col-md-4">
                            <label for="date_debut" class="form-label">Date début</label>
                            <input type="date" class="form-control" id="date_debut" name="date_debut" 
                                   value="<?= $date_debut ?>">
                        </div>
                        <div class="col-md-4">
                            <label for="date_fin" class="form-label">Date fin</label>
                            <input type="date" class="form-control" id="date_fin" name="date_fin" 
                                   value="<?= $date_fin ?>">
                        </div>
                        <div class="col-md-4 d-flex align-items-end">
                            <button type="submit" class="btn btn-primary w-100">
                                <i class="fas fa-filter"></i> Appliquer
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
    
    <!-- KPIs -->
    <div class="row g-4 mb-4">
        <div class="col-md-3">
            <div class="card border-0 shadow-sm bg-primary text-white">
                <div class="card-body">
                    <h2 class="mb-0"><?= $stats['total_stagiaires'] ?></h2>
                    <small>Total stagiaires</small>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 shadow-sm bg-success text-white">
                <div class="card-body">
                    <h2 class="mb-0"><?= $stats['stagiaires_actifs'] ?></h2>
                    <small>Actuellement en stage</small>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 shadow-sm bg-warning text-white">
                <div class="card-body">
                    <h2 class="mb-0"><?= $stats['stagiaires_attente'] ?></h2>
                    <small>En attente</small>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 shadow-sm bg-info text-white">
                <div class="card-body">
                    <h2 class="mb-0"><?= $stats['stagiaires_termines'] ?></h2>
                    <small>Stages terminés</small>
                </div>
            </div>
        </div>
    </div>
    
    <div class="row g-4 mb-4">
        <div class="col-md-4">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white">
                    <h6 class="card-title mb-0">Encadreurs</h6>
                </div>
                <div class="card-body">
                    <h2 class="mb-0"><?= $stats['total_encadreurs'] ?></h2>
                    <small>Total encadreurs</small>
                    <div class="mt-2">
                        <span class="badge bg-success"><?= $stats['encadreurs_disponibles'] ?> disponibles</span>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white">
                    <h6 class="card-title mb-0">Attestations</h6>
                </div>
                <div class="card-body">
                    <h2 class="mb-0"><?= $stats['attestations'] ?></h2>
                    <small>Total générées</small>
                    <div class="mt-2">
                        <span class="badge bg-info"><?= $stats['attestations_mois'] ?> ce mois</span>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white">
                    <h6 class="card-title mb-0">Ratio d'encadrement</h6>
                </div>
                <div class="card-body">
                    <?php 
                    $ratio = $stats['total_encadreurs'] > 0 
                        ? round($stats['stagiaires_actifs'] / $stats['total_encadreurs'], 1) 
                        : 0;
                    ?>
                    <h2 class="mb-0"><?= $ratio ?></h2>
                    <small>Stagiaires par encadreur</small>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Graphiques -->
    <div class="row g-4 mb-4">
        <div class="col-md-6">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white">
                    <h6 class="card-title mb-0">Évolution des inscriptions</h6>
                </div>
                <div class="card-body">
                    <canvas id="inscriptionsChart" height="300"></canvas>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white">
                    <h6 class="card-title mb-0">Répartition par filière</h6>
                </div>
                <div class="card-body">
                    <canvas id="filiereChart" height="300"></canvas>
                </div>
            </div>
        </div>
    </div>
    
    <div class="row g-4">
        <div class="col-md-6">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white">
                    <h6 class="card-title mb-0">Stages par mois</h6>
                </div>
                <div class="card-body">
                    <canvas id="stagesChart" height="300"></canvas>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white">
                    <h6 class="card-title mb-0">Top encadreurs</h6>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Encadreur</th>
                                    <th>Profession</th>
                                    <th>Stagiaires</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($top_encadreurs as $e): ?>
                                <tr>
                                    <td><?= e($e['prenom'] . ' ' . $e['nom']) ?></td>
                                    <td><?= e($e['profession']) ?></td>
                                    <td>
                                        <span class="badge bg-primary"><?= $e['nb_stagiaires'] ?></span>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Graphique des inscriptions
    const ctx1 = document.getElementById('inscriptionsChart').getContext('2d');
    new Chart(ctx1, {
        type: 'line',
        data: {
            labels: <?= json_encode(array_column($inscriptions, 'date')) ?>,
            datasets: [{
                label: 'Inscriptions',
                data: <?= json_encode(array_column($inscriptions, 'total')) ?>,
                borderColor: '#4361ee',
                backgroundColor: 'rgba(67, 97, 238, 0.1)',
                tension: 0.4
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    display: false
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        stepSize: 1
                    }
                }
            }
        }
    });
    
    // Graphique des filières
    const ctx2 = document.getElementById('filiereChart').getContext('2d');
    new Chart(ctx2, {
        type: 'doughnut',
        data: {
            labels: <?= json_encode(array_column($repartition_filieres, 'filiere')) ?>,
            datasets: [{
                data: <?= json_encode(array_column($repartition_filieres, 'total')) ?>,
                backgroundColor: [
                    '#4361ee', '#4cc9f0', '#f72585', '#f8961e', '#7209b7',
                    '#b5179e', '#4895ef', '#3f37c9', '#f15bb5', '#fee440'
                ],
                borderWidth: 0
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'bottom',
                    labels: {
                        boxWidth: 12
                    }
                }
            }
        }
    });
    
    // Graphique des stages
    const ctx3 = document.getElementById('stagesChart').getContext('2d');
    new Chart(ctx3, {
        type: 'bar',
        data: {
            labels: <?= json_encode(array_column($stages_par_mois, 'mois')) ?>,
            datasets: [{
                label: 'Stages commencés',
                data: <?= json_encode(array_column($stages_par_mois, 'debuts')) ?>,
                backgroundColor: '#4361ee',
                borderRadius: 5
            }, {
                label: 'Stages terminés',
                data: <?= json_encode(array_column($stages_par_mois, 'termines')) ?>,
                backgroundColor: '#4cc9f0',
                borderRadius: 5
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'bottom'
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        stepSize: 1
                    }
                }
            }
        }
    });
});
</script>

<?php include '../../includes/footer.php'; ?>