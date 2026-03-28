<!-- Documents -->
<div class="card border-0 shadow-sm mb-4">
    <div class="card-header bg-white d-flex justify-content-between align-items-center">
        <h6 class="card-title mb-0">
            <i class="fas fa-file-alt me-2 text-primary"></i>
            Documents du stagiaire
        </h6>
        <a href="../stagiaire/document-uploader.php?stagiaire=<?= $id ?>" class="btn btn-sm btn-success">
            <i class="fas fa-upload"></i> Ajouter un document
        </a>
    </div>
    <div class="card-body">
        <?php if ($docs): ?>
            <div class="row g-3">
                <?php foreach ($docs as $doc): 
                    $file_ext = pathinfo($doc['nom_fichier'], PATHINFO_EXTENSION);
                    $icon = 'fa-file-alt';
                    $color = '#6c757d';
                    
                    if ($file_ext === 'pdf') {
                        $icon = 'fa-file-pdf';
                        $color = '#dc3545';
                    } elseif (in_array($file_ext, ['doc', 'docx'])) {
                        $icon = 'fa-file-word';
                        $color = '#0d6efd';
                    } elseif (in_array($file_ext, ['jpg', 'jpeg', 'png'])) {
                        $icon = 'fa-file-image';
                        $color = '#198754';
                    }
                ?>
                <div class="col-md-6">
                    <div class="d-flex align-items-center p-3 border rounded-3 hover-shadow transition">
                        <i class="fas <?= $icon ?> fa-2x me-3" style="color: <?= $color ?>"></i>
                        <div class="flex-grow-1">
                            <div class="d-flex justify-content-between align-items-start">
                                <div>
                                    <small class="d-block fw-bold"><?= e(truncate($doc['nom_fichier'], 30)) ?></small>
                                    <small class="text-muted">
                                        <i class="fas fa-calendar-alt me-1"></i> <?= format_date($doc['date_upload'], 'd/m/Y') ?>
                                        <i class="fas fa-database ms-2 me-1"></i> <?= format_filesize($doc['taille']) ?>
                                    </small>
                                </div>
                                <div class="dropdown">
                                    <button class="btn btn-sm btn-link text-dark" type="button" data-bs-toggle="dropdown">
                                        <i class="fas fa-ellipsis-v"></i>
                                    </button>
                                    <ul class="dropdown-menu dropdown-menu-end">
                                        <li>
                                            <a class="dropdown-item" href="../../ajax/download_document.php?id=<?= $doc['id_document'] ?>">
                                                <i class="fas fa-download me-2 text-primary"></i> Télécharger
                                            </a>
                                        </li>
                                        <li>
                                            <a class="dropdown-item" href="#" onclick="viewDocument(<?= $doc['id_document'] ?>)">
                                                <i class="fas fa-eye me-2 text-info"></i> Aperçu
                                            </a>
                                        </li>
                                        <li><hr class="dropdown-divider"></li>
                                        <li>
                                            <a class="dropdown-item text-danger" href="#" onclick="deleteDocument(<?= $doc['id_document'] ?>, '<?= e(addslashes($doc['nom_fichier'])) ?>')">
                                                <i class="fas fa-trash-alt me-2"></i> Supprimer
                                            </a>
                                        </li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="text-center py-4">
                <i class="fas fa-folder-open fa-3x text-muted mb-3"></i>
                <p class="text-muted mb-0">Aucun document uploadé par ce stagiaire</p>
            </div>
        <?php endif; ?>
    </div>
</div>