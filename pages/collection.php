<?php
session_start();
require '../config.php';

// Controlla se l'utente è loggato
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Inizializza la risposta JSON per AJAX
$response = ['success' => false, 'message' => 'Azione non riconosciuta'];

// Gestisci le richieste AJAX
if (isset($_POST['ajax_action']) || isset($_GET['ajax_action'])) {
    $ajax_action = isset($_POST['ajax_action']) ? $_POST['ajax_action'] : $_GET['ajax_action'];
    
    // Azione: Elimina caccia completata
    if ($ajax_action === 'delete_hunt' && isset($_POST['hunt_id'])) {
        $hunt_id = $_POST['hunt_id'];
        $user_id = $_SESSION['user_id'];
        
        // Verifica che la caccia appartenga all'utente
        $checkQuery = "SELECT idCaccia FROM tblCacce WHERE idCaccia = ? AND utenteId = ?";
        $stmt = $conn->prepare($checkQuery);
        $stmt->bind_param("ii", $hunt_id, $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 0) {
            $response = [
                'success' => false,
                'message' => 'Caccia non trovata o non autorizzata'
            ];
        } else {
            // Elimina la caccia
            $deleteQuery = "DELETE FROM tblCacce WHERE idCaccia = ? AND utenteId = ?";
            $stmt = $conn->prepare($deleteQuery);
            $stmt->bind_param("ii", $hunt_id, $user_id);
            
            if ($stmt->execute()) {
                $response = [
                    'success' => true,
                    'message' => 'Caccia eliminata con successo'
                ];
            } else {
                $response = [
                    'success' => false,
                    'message' => 'Errore durante l\'eliminazione: ' . $conn->error
                ];
            }
        }
        
        // Invia risposta JSON
        header('Content-Type: application/json');
        echo json_encode($response);
        exit();
    }
    
    // Azione: Recupera dettagli caccia per modifica
    if ($ajax_action === 'get_hunt_details' && isset($_POST['hunt_id'])) {
        $hunt_id = $_POST['hunt_id'];
        $user_id = $_SESSION['user_id'];
        
        // Verifica che la caccia appartenga all'utente
        $query = "
            SELECT 
                tblCacce.*, 
                tblGiochi.idGioco, 
                tblGiochi.nome AS nomeGioco, 
                tblMetodi.idMetodo, 
                tblMetodi.nome AS nomeMetodo
            FROM 
                tblCacce
                INNER JOIN tblGiochi ON tblCacce.giocoId = tblGiochi.idGioco
                INNER JOIN tblMetodi ON tblCacce.metodoId = tblMetodi.idMetodo
            WHERE 
                tblCacce.idCaccia = ? AND tblCacce.utenteId = ?";
                
        $stmt = $conn->prepare($query);
        $stmt->bind_param("ii", $hunt_id, $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 0) {
            $response = [
                'success' => false,
                'message' => 'Caccia non trovata o non autorizzata'
            ];
        } else {
            $hunt = $result->fetch_assoc();
            
            // Ottieni tutti i giochi disponibili
            $gamesQuery = "SELECT idGioco, nome FROM tblGiochi ORDER BY nome";
            $games = $conn->query($gamesQuery)->fetch_all(MYSQLI_ASSOC);
            
            // Ottieni tutti i metodi disponibili
            $methodsQuery = "SELECT idMetodo, nome FROM tblMetodi ORDER BY nome";
            $methods = $conn->query($methodsQuery)->fetch_all(MYSQLI_ASSOC);
            
            $response = [
                'success' => true,
                'hunt' => $hunt,
                'games' => $games,
                'methods' => $methods
            ];
        }
        
        // Invia risposta JSON
        header('Content-Type: application/json');
        echo json_encode($response);
        exit();
    }
    
    // Azione: Aggiorna dettagli caccia
    if ($ajax_action === 'update_hunt' && isset($_POST['hunt_id'])) {
        $hunt_id = $_POST['hunt_id'];
        $user_id = $_SESSION['user_id'];
        $pokemon = isset($_POST['pokemon_name']) ? $_POST['pokemon_name'] : null;
        $game_id = isset($_POST['game_id']) ? $_POST['game_id'] : null;
        $method_id = isset($_POST['method_id']) ? $_POST['method_id'] : null;
        $encounters = isset($_POST['encounters']) ? $_POST['encounters'] : null;
        $completion_date = isset($_POST['completion_date']) ? $_POST['completion_date'] : null;
        
        // Verifica che tutti i campi necessari siano presenti
        if (!$pokemon || !$game_id || !$method_id || !$encounters || !$completion_date) {
            $response = [
                'success' => false,
                'message' => 'Dati incompleti. Tutti i campi sono obbligatori.'
            ];
            header('Content-Type: application/json');
            echo json_encode($response);
            exit();
        }
        
        // Verifica che la caccia appartenga all'utente
        $checkQuery = "SELECT idCaccia FROM tblCacce WHERE idCaccia = ? AND utenteId = ?";
        $stmt = $conn->prepare($checkQuery);
        $stmt->bind_param("ii", $hunt_id, $user_id);
        $stmt->execute();
        
        if ($stmt->get_result()->num_rows === 0) {
            $response = [
                'success' => false,
                'message' => 'Caccia non trovata o non autorizzata'
            ];
        } else {
            // Aggiorna i dettagli della caccia
            $updateQuery = "
                UPDATE tblCacce 
                SET 
                    pokemon = ?,
                    giocoId = ?,
                    metodoId = ?,
                    tentativi = ?,
                    DataFine = ?
                WHERE 
                    idCaccia = ? AND utenteId = ?";
                    
            $stmt = $conn->prepare($updateQuery);
            $stmt->bind_param("siissis", $pokemon, $game_id, $method_id, $encounters, $completion_date, $hunt_id, $user_id);
            
            if ($stmt->execute()) {
                // Ottieni i dettagli aggiornati della caccia
                $query = "
                    SELECT 
                        tblCacce.*, 
                        tblGiochi.nome AS nomeGioco, 
                        tblMetodi.nome AS nomeMetodo,
                        DATE_FORMAT(tblCacce.DataFine, '%d/%m/%Y') AS dataFine,
                        CASE 
                            WHEN tblCacce.hasShinyCharm = 1 THEN tblOddsMetodi.oddsWithCharm 
                            ELSE tblOddsMetodi.oddsWithoutCharm 
                        END AS odds
                    FROM 
                        tblCacce
                        INNER JOIN tblGiochi ON tblCacce.giocoId = tblGiochi.idGioco
                        INNER JOIN tblMetodi ON tblCacce.metodoId = tblMetodi.idMetodo
                        LEFT JOIN tblOddsMetodi ON tblCacce.metodoId = tblOddsMetodi.metodoId 
                            AND tblCacce.giocoId = tblOddsMetodi.giocoId
                    WHERE 
                        tblCacce.idCaccia = ?";
                        
                $stmt = $conn->prepare($query);
                $stmt->bind_param("i", $hunt_id);
                $stmt->execute();
                $hunt = $stmt->get_result()->fetch_assoc();
                
                $response = [
                    'success' => true,
                    'message' => 'Caccia aggiornata con successo',
                    'hunt' => $hunt
                ];
            } else {
                $response = [
                    'success' => false,
                    'message' => 'Errore durante l\'aggiornamento: ' . $conn->error
                ];
            }
        }
        
        // Invia risposta JSON
        header('Content-Type: application/json');
        echo json_encode($response);
        exit();
    }
    
    // Azione: Filtra Pokémon per diversi criteric
    if ($ajax_action === 'filter_pokemon') {
        $user_id = $_SESSION['user_id'];
        $filter = isset($_POST['filter']) ? $_POST['filter'] : (isset($_GET['filter']) ? $_GET['filter'] : 'all');
        
        // Log per debug
        error_log("Richiesta filter_pokemon ricevuta con filtro: $filter");
        
        // Ottieni dati delle cacce completate con filtro
        if ($filter === 'recent') {
            $orderClause = "ORDER BY tblCacce.DataFine DESC";
        } elseif ($filter === 'oldest') {
            $orderClause = "ORDER BY tblCacce.DataFine ASC";
        } elseif ($filter === 'by_game') {
            $orderClause = "ORDER BY tblGiochi.nome ASC, tblCacce.DataFine DESC";
        } elseif ($filter === 'by_method') {
            $orderClause = "ORDER BY tblMetodi.nome ASC, tblCacce.DataFine DESC";
        } else {
            // Filtro predefinito 'all'
            $orderClause = "ORDER BY tblCacce.DataFine DESC";
        }

        // Query di base per ottenere le cacce completate
        $query = "
            SELECT 
                tblCacce.*,
                tblGiochi.nome as nomeGioco,
                tblMetodi.nome as nomeMetodo,
                CASE 
                    WHEN tblCacce.hasShinyCharm = 1 THEN tblOddsMetodi.oddsWithCharm 
                    ELSE tblOddsMetodi.oddsWithoutCharm 
                END AS odds,
                DATE_FORMAT(tblCacce.DataFine, '%d/%m/%Y') AS dataFine,
                TIMESTAMPDIFF(DAY, tblCacce.DataInizio, tblCacce.DataFine) AS intervaloDays,
                MOD(TIMESTAMPDIFF(HOUR, tblCacce.DataInizio, tblCacce.DataFine), 24) AS intervaloHours,
                MOD(TIMESTAMPDIFF(MINUTE, tblCacce.DataInizio, tblCacce.DataFine), 60) AS intervaloMinutes
            FROM 
                tblCacce
                INNER JOIN tblGiochi ON tblCacce.giocoId = tblGiochi.idGioco
                INNER JOIN tblMetodi ON tblCacce.metodoId = tblMetodi.idMetodo
                LEFT JOIN tblOddsMetodi ON tblCacce.metodoId = tblOddsMetodi.metodoId 
                    AND tblCacce.giocoId = tblOddsMetodi.giocoId
            WHERE 
                tblCacce.utenteId = ? AND tblCacce.isCompleted = 1
            $orderClause
        ";

        try {
        $stmt = $conn->prepare($query);
            if ($stmt === false) {
                throw new Exception("Errore nella preparazione della query: " . $conn->error);
            }
            
            $stmt->bind_param("i", $user_id);
            if (!$stmt->execute()) {
                throw new Exception("Errore nell'esecuzione della query: " . $stmt->error);
            }
            
        $completed_hunts = $stmt->get_result();
        
        // Prepara l'array per i dati
        $data = [];
        
        // Popola l'array con i risultati
        while ($row = $completed_hunts->fetch_assoc()) {
            $data[] = $row;
        }
        
        $response = [
            'success' => true,
            'data' => $data
        ];
        } catch (Exception $e) {
            error_log("Errore in filter_pokemon: " . $e->getMessage());
            $response = [
                'success' => false,
                'message' => "Si è verificato un errore: " . $e->getMessage()
            ];
        }
        
        // Invia risposta JSON
        header('Content-Type: application/json');
        echo json_encode($response);
        exit();
    }
}

// Ottieni informazioni sull'utente
$user_id = $_SESSION['user_id'];
$query = $conn->prepare("SELECT * FROM tblUtenti WHERE idUtente = ?");
$query->bind_param("i", $user_id);
$query->execute();
$user = $query->get_result()->fetch_assoc();

// Ottieni il tema dalla sessione o imposta il default
$theme = isset($_SESSION['theme']) ? $_SESSION['theme'] : 'dark';

// Gestisci il cambio tema via GET o POST
if (isset($_POST['toggle_theme']) || isset($_GET['toggle_theme'])) {
    $_SESSION['theme'] = isset($_SESSION['theme']) && $_SESSION['theme'] === 'light' ? 'dark' : 'light';
    
    // Se è una richiesta AJAX, restituisci una risposta JSON
    if (isset($_POST['toggle_theme'])) {
        header('Content-Type: application/json');
        echo json_encode(['success' => true, 'theme' => $_SESSION['theme']]);
        exit();
    }
    
    // Se è una richiesta GET, reindirizza alla pagina corrente senza il parametro toggle_theme
    if (isset($_GET['toggle_theme'])) {
        $redirectUrl = strtok($_SERVER['REQUEST_URI'], '?');
        header("Location: " . $redirectUrl);
        exit();
    }
}

// Ottieni le cacce completate dell'utente
$query = $conn->prepare("SELECT 
                        tblCacce.*, 
                        tblGiochi.nome AS nomeGioco, 
                        tblMetodi.nome AS nomeMetodo,
                        CASE 
                            WHEN tblCacce.hasShinyCharm = 1 THEN tblOddsMetodi.oddsWithCharm 
                            ELSE tblOddsMetodi.oddsWithoutCharm 
                        END AS odds
                    FROM 
                        tblCacce 
                        INNER JOIN tblGiochi ON tblCacce.giocoId = tblGiochi.idGioco 
                        INNER JOIN tblMetodi ON tblCacce.metodoId = tblMetodi.idMetodo
                        LEFT JOIN tblOddsMetodi ON tblCacce.metodoId = tblOddsMetodi.metodoId 
                            AND tblCacce.giocoId = tblOddsMetodi.giocoId
                    WHERE 
                        tblCacce.utenteId = ? AND tblCacce.isCompleted = 1
                    ORDER BY 
                        tblCacce.DataFine DESC");
if ($query === false) {
    die("Errore nella preparazione della query: " . $conn->error);
}
$query->bind_param("i", $user_id);
$query->execute();
$completed_hunts = $query->get_result();

// Statistiche utente
$query = $conn->prepare("SELECT 
                        COUNT(CASE WHEN tblCacce.isCompleted = 0 THEN 1 END) AS cacce_attive,
                        COUNT(CASE WHEN tblCacce.isCompleted = 1 THEN 1 END) AS cacce_completate
                    FROM 
                        tblCacce 
                    WHERE 
                        tblCacce.utenteId = ?");
if ($query === false) {
    die("Errore nella preparazione della query: " . $conn->error);
}
$query->bind_param("i", $user_id);
$query->execute();
$stats = $query->get_result()->fetch_assoc();
$active_hunts = $stats['cacce_attive'];
$completed_hunts_count = $stats['cacce_completate'];

// Calcola la percentuale di completamento del Pokedex
$total_pokemon = 1008; // Numero totale di Pokémon fino alla nona generazione
$stmt = $conn->prepare("SELECT 
                        COUNT(DISTINCT tblCacce.pokemon) AS found 
                    FROM 
                        tblCacce 
                    WHERE 
                        tblCacce.utenteId = ? AND tblCacce.isCompleted = 1");
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
    <title>SIVACACCIA! - Collezione</title>
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
                <a class="nav-link active" href="collection.php">
                    <i class="fas fa-trophy"></i> Collezione
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="methods.php">
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
            <button class="btn btn-link theme-toggle" title="Dark/Light Mode">
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
                <input type="text" placeholder="Cerca...">
            </div>
            
            <div class="filter-buttons">
                <button class="active">Tutti</button>
                <button>Recenti</button>
                <button>Meno Recenti</button>
                <button>Per Gioco</button>
                <button>Per Metodo</button>
            </div>
            
            <div class="row">
                <?php if ($completed_hunts->num_rows > 0): ?>
                    <?php while ($hunt = $completed_hunts->fetch_assoc()): ?>
                        <div class="col-md-3">
                            <div class="pokemon-card">
                                <div class="dropdown float-right">
                                    <button class="btn btn-link dropdown-toggle" type="button" id="dropdownMenu-<?php echo $hunt['idCaccia']; ?>" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                                        <i class="fas fa-ellipsis-v text-white"></i>
                                    </button>
                                    <div class="dropdown-menu dropdown-menu-right" aria-labelledby="dropdownMenu-<?php echo $hunt['idCaccia']; ?>">
                                        <a class="dropdown-item" href="#" data-hunt-id="<?php echo $hunt['idCaccia']; ?>">Dettagli</a>
                                        <form action="collection.php" method="post" class="d-inline">
                                            <input type="hidden" name="hunt_id" value="<?php echo $hunt['idCaccia']; ?>">
                                            <button type="button" name="delete_hunt" class="dropdown-item text-danger">Elimina</button>
                                        </form>
                                    </div>
                                </div>
                                <div class="text-center mb-2">
                                    <img class="pokemon-img" src="" alt="<?php echo $hunt['pokemon']; ?>" data-pokemon="<?php echo $hunt['pokemon']; ?>">
                                </div>
                                <h4><?php echo $hunt['pokemon']; ?></h4>
                                <div class="info"><?php echo $hunt['nomeGioco']; ?></div>
                                <div class="info">
                                    <span class="badge"><?php echo $hunt['nomeMetodo']; ?></span>
                                    <span class="badge"><?php echo $hunt['tentativi']; ?> incontri</span>
                                    <span class="badge"><i class="fas fa-dice"></i> <?php echo $hunt['odds'] ? $hunt['odds'] : 'Odds N/A'; ?></span>
                                </div>
                                <div class="completion-info">
                                    <small>Completato il: <?php echo date('d/m/Y', strtotime($hunt['DataFine'])); ?></small><br>
                                    <small>Tempo totale: 
                                        <?php 
                                        // Calcola il tempo trascorso tra inizio e fine caccia
                                        if (!empty($hunt['DataInizio']) && !empty($hunt['DataFine'])) {
                                            $start = new DateTime($hunt['DataInizio']);
                                            $end = new DateTime($hunt['DataFine']);
                                            $interval = $start->diff($end);
                                            echo $interval->days > 0 ? $interval->days . 'g ' : '';
                                            echo $interval->h . 'h ' . $interval->i . 'm';
                                        } else {
                                            echo "N/A";
                                        }
                                        ?>
                                    </small>
                                </div>
                            </div>
                        </div>
                    <?php endwhile; ?>
                <?php else: ?>
                    <div class="col-12 text-center">
                        <p>Non hai ancora completato nessuna caccia.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- Modal per modifica dettagli caccia -->
    <div class="modal fade" id="editHuntModal" tabindex="-1" role="dialog" aria-labelledby="editHuntModalLabel" aria-hidden="true">
        <div class="modal-dialog" role="document">
            <div class="modal-content bg-dark text-white">
                <div class="modal-header border-secondary">
                    <h5 class="modal-title" id="editHuntModalLabel">Modifica Dettagli Caccia</h5>
                    <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <form id="editHuntForm">
                        <input type="hidden" id="editHuntId" name="hunt_id">
                        
                        <div class="form-group">
                            <label for="editPokemonName">Nome Pokémon</label>
                            <div class="autocomplete-container">
                                <input type="text" class="form-control bg-secondary text-white" id="editPokemonName" name="pokemon_name" required data-valid="true">
                                <div id="edit-pokemon-results" class="autocomplete-results bg-dark"></div>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label for="editGameSelect">Gioco</label>
                            <select class="form-control bg-secondary text-white" id="editGameSelect" name="game_id" required>

                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="editMethodSelect">Metodo</label>
                            <select class="form-control bg-secondary text-white" id="editMethodSelect" name="method_id" required>

                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="editEncounters">Numero di incontri</label>
                            <input type="number" class="form-control bg-secondary text-white" id="editEncounters" name="encounters" min="1" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="editCompletionDate">Data completamento</label>
                            <input type="date" class="form-control bg-secondary text-white" id="editCompletionDate" name="completion_date" required>
                        </div>
                    </form>
                </div>
                <div class="modal-footer border-secondary">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Annulla</button>
                    <button type="button" class="btn btn-primary" id="saveHuntChanges">Salva modifiche</button>
                </div>
            </div>
        </div>
    </div>
    <script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/popper.js@1.16.1/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
    <script src="../assets/js/main.js"></script>
    <script src="../assets/js/collection.js"></script>
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