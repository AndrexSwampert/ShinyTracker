<?php
session_start();
require '../config.php';

// Controlla se l'utente è loggato
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Ottieni informazioni sull'utente
$user_id = $_SESSION['user_id'];
$query = $conn->prepare("SELECT * FROM tblUtenti WHERE idUtente = ?");
$query->bind_param("i", $user_id);
$query->execute();
$user = $query->get_result()->fetch_assoc();

// Gestisci il cambio tema
if (isset($_POST['toggle_theme'])) {
    $_SESSION['theme'] = isset($_SESSION['theme']) && $_SESSION['theme'] === 'light' ? 'dark' : 'light';
    header('Content-Type: application/json');
    echo json_encode(['success' => true, 'theme' => $_SESSION['theme']]);
    exit();
}

// Ottieni tutti i metodi di caccia
$query = $conn->prepare("SELECT tblMetodi.*, COUNT(tblGiochi.idGioco) AS numeroGiochi 
                        FROM tblMetodi
                        LEFT JOIN tblGiochiMetodi ON tblMetodi.idMetodo = tblGiochiMetodi.metodoId
                        LEFT JOIN tblGiochi ON tblGiochiMetodi.giocoId = tblGiochi.idGioco
                        GROUP BY tblMetodi.idMetodo
                        ORDER BY tblMetodi.nome");
if ($query === false) {
    die("Errore nella preparazione della query: " . $conn->error);
}
$query->execute();
$methods = $query->get_result();

// Statistiche utente
$query = $conn->prepare("SELECT 
                        COUNT(CASE WHEN isCompleted = 0 THEN 1 END) AS cacce_attive,
                        COUNT(CASE WHEN isCompleted = 1 THEN 1 END) AS cacce_completate
                        FROM tblCacce WHERE utenteId = ?");
if ($query === false) {
    die("Errore nella preparazione della query: " . $conn->error);
}
$query->bind_param("i", $user_id);
$query->execute();
$stats = $query->get_result()->fetch_assoc();
$active_hunts = $stats['cacce_attive'];
$completed_hunts_count = $stats['cacce_completate'];

// Calcola la percentuale di completamento del Pokedex
$total_pokemon = 1008; // Numero totale di Pokémon fino alla nona generazione, ci accontentiamo così 
$stmt = $conn->prepare("SELECT COUNT(DISTINCT pokemon) AS found FROM tblCacce WHERE utenteId = ? AND isCompleted = 1");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$found_pokemon = $stmt->get_result()->fetch_assoc()['found'];
$completion_percentage = ($total_pokemon > 0) ? round(($found_pokemon / $total_pokemon) * 100, 1) : 0;
?>

<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SIVACACCIA! - Metodi</title>
    <link href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.1/css/all.min.css" rel="stylesheet">
    <link href="../assets/css/style.css" rel="stylesheet">
    <link href="../assets/css/account.css" rel="stylesheet">
</head>
<body class="<?php echo isset($_SESSION['theme']) && $_SESSION['theme'] === 'light' ? 'light-theme' : ''; ?>">
    <!-- Sidebar -->
    <div class="sidebar">
        <ul class="nav flex-column">
            <li class="nav-item">
                <a class="nav-link" href="../index.php">
                    <i class="fas fa-stopwatch"></i> Cacce
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="collection.php">
                    <i class="fas fa-trophy"></i> Collezione
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link active" href="methods.php">
                    <i class="fas fa-search"></i> Metodi
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="leaderboard.php">
                    <i class="fas fa-medal"></i> Classifica
                </a>
            </li>
        </ul>
        
        <div class="sidebar-stats text-center">
            <div class="mb-4">
                <div class="stat-value"><?php echo $active_hunts; ?></div>
                <div class="stat-label">CACCE</div>
            </div>
            <div class="mb-4">
                <div class="stat-value"><?php echo $completed_hunts_count; ?></div>
                <div class="stat-label">CATTURATI</div>
            </div>
            <div>
                <div class="stat-value"><?php echo $completion_percentage; ?>%</div>
                <div class="stat-label">DEX COMPLETATO</div>
            </div>
        </div>
    </div>
    
    <!-- Header -->
    <div class="header">
        <div class="logo">
            <img src="../img/logo.svg" alt="SIVACACCIA! Logo">
            <h4 class="mb-0">SIVACACCIA!</h4>
        </div>
        <div>
            <a href="../api/logout.php" class="btn btn-link" title="Logout">
                <i class="fas fa-sign-out-alt text-white"></i>
            </a>
            <button class="btn btn-link theme-toggle" title="Modalità Chiara/Scura">
                <i class="fas <?php echo isset($_SESSION['theme']) && $_SESSION['theme'] === 'light' ? 'fa-moon' : 'fa-sun'; ?> text-white"></i>
            </button>
            <button class="btn btn-link account-btn" title="Account">
                <i class="fas fa-user text-white"></i>
            </button>
        </div>
    </div>
    
    <!-- Main Content -->
    <div class="content">
        <div class="main-content">
            <div class="search-container">
                <input type="text" placeholder="Cerca metodi...">
            </div>
            
            <div class="row">
                <?php if ($methods->num_rows > 0): ?>
                    <?php while ($method = $methods->fetch_assoc()): ?>
                        <div class="col-md-6">
                            <div class="method-card">
                                <div class="d-flex align-items-center">
                                    <i class="method-icon fas fa-dice"></i>
                                    <h4><?php echo $method['nome']; ?></h4>
                                </div>
                                <p><?php echo $method['descrizione']; ?></p>
                                
                                <?php if (!empty($method['probabilita'])): ?>
                                <div class="progress mb-3" style="height: 8px; background-color: #333;">
                                    <div class="progress-bar bg-success" role="progressbar" style="width: <?php echo min($method['probabilita'] * 1000, 100); ?>%;" aria-valuenow="<?php echo $method['probabilita']; ?>" aria-valuemin="0" aria-valuemax="1"></div>
                                </div>
                                <?php endif; ?>
                                
                                <div class="stats">
                                    <div>
                                        <i class="fas fa-gamepad"></i> Disponibile in <?php echo $method['numeroGiochi']; ?> giochi
                                    </div>
                                    <div>
                                        <i class="fas fa-percentage"></i> 
                                        <?php 
                                        if (!empty($method['probabilita'])) {
                                            echo "1:" . number_format(1/$method['probabilita'], 0);
                                        } else {
                                            echo "Odds variabili";
                                        }
                                        ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endwhile; ?>
                <?php else: ?>
                    <div class="col-12 text-center">
                        <p>Nessun metodo disponibile al momento.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.9.3/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
    <script src="../assets/js/main.js"></script>
    <script src="../assets/js/methods.js"></script>
    <script src="../assets/js/account.js"></script>
    <div class="modal fade account-modal" id="accountModal" tabindex="-1" role="dialog" aria-labelledby="accountModalLabel" aria-hidden="true">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="accountModalLabel">ACCOUNT</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <div id="alertContainer"></div>
                    <div class="account-info-section">
                        <h4>Email</h4>
                        <p id="userEmail">Caricamento...</p>
                    </div>
                    <div class="account-info-section">
                        <h4>Nome Utente</h4>
                        <div id="currentNameDisplay">
                            <p id="userName">Caricamento...</p>
                            <button id="changeNameBtn" class="account-button">Cambia Nome</button>
                        </div>
                        <div id="changeNameForm" style="display: none;">
                            <div class="account-form-group">
                                <label for="newUsername">Nuovo Nome Utente</label>
                                <input type="text" id="newUsername" class="form-control">
                            </div>
                            <div class="d-flex">
                                <button id="saveNameBtn" class="account-button">Salva</button>
                                <button id="cancelNameBtn" class="account-button secondary">Annulla</button>
                            </div>
                        </div>
                    </div>
                    <div class="account-info-section">
                        <h4>Password</h4>
                        <div id="passwordSection">
                            <p>********</p>
                            <button id="changePasswordBtn" class="account-button">Cambia Password</button>
                        </div>
                        <div id="changePasswordForm" style="display: none;">
                            <div class="account-form-group">
                                <label for="currentPassword">Password Attuale</label>
                                <input type="password" id="currentPassword" class="form-control">
                            </div>
                            <div class="account-form-group">
                                <label for="newPassword">Nuova Password</label>
                                <input type="password" id="newPassword" class="form-control">
                            </div>
                            <div class="account-form-group">
                                <label for="confirmPassword">Conferma Password</label>
                                <input type="password" id="confirmPassword" class="form-control">
                            </div>
                            <div class="d-flex">
                                <button id="savePasswordBtn" class="account-button">Salva</button>
                                <button id="cancelPasswordBtn" class="account-button secondary">Annulla</button>
                            </div>
                        </div>
                    </div>
                    <div class="account-info-section">
                        <h4>Informazioni Account</h4>
                        <p>
                            <span class="info-label">Account ID:</span>
                            <span id="accountId" class="info-value">Caricamento...</span>
                        </p>
                        <p>
                            <span class="info-label">Data Creazione:</span>
                            <span id="accountCreationDate" class="info-value">Caricamento...</span>
                        </p>
                    </div>
                    <div class="account-info-section">
                        <h4>Disattivazione Account</h4>
                        <p>Attenzione: questa operazione non può essere annullata.</p>
                        <button id="deactivateAccountBtn" class="account-button danger">Disattiva Account</button>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="modal fade account-modal" id="confirmDeactivationModal" tabindex="-1" role="dialog" aria-hidden="true">
        <div class="modal-dialog modal-sm" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Conferma Disattivazione</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <div id="deactivateAlertContainer"></div>
                    <p>Inserisci la tua password per confermare la disattivazione dell'account.</p>
                    <div class="account-form-group">
                        <label for="deactivatePassword">Password</label>
                        <input type="password" id="deactivatePassword" class="form-control">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="account-button secondary" data-dismiss="modal">Annulla</button>
                    <button type="button" id="confirmDeactivateBtn" class="account-button danger">Conferma</button>
                </div>
            </div>
        </div>
    </div>
</body>
</html> 