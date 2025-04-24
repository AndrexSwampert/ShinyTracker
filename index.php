<?php
session_start();
require 'config.php';

// Controlla se l'utente è loggato
if (!isset($_SESSION['user_id'])) {
    header("Location: Login.php");
    exit();
}

// Ottieni informazioni sull'utente
$user_id = $_SESSION['user_id'];
$query = $conn->prepare("SELECT * FROM tblUtenti WHERE idUtente = ?");
$query->bind_param("i", $user_id);
$query->execute();
$user = $query->get_result()->fetch_assoc();

// Imposta lo username nella sessione se non è già impostato
if (!isset($_SESSION['username']) && isset($user['username'])) {
    $_SESSION['username'] = $user['username'];
}

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

// Gestisci le azioni POST
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    
    // Gestione richieste AJAX
    if (isset($_POST['ajax_action'])) {
        $response = ['success' => false, 'message' => ''];
        
        // Azione: Incrementa tentativi via AJAX
        if ($_POST['ajax_action'] === 'increment') {
            $hunt_id = $_POST['hunt_id'];
            $increment = $_POST['increment'] ?? 1;
            
            $query = $conn->prepare("UPDATE tblCacce SET tentativi = tentativi + ? WHERE idCaccia = ? AND utenteId = ?");
            if ($query === false) {
                $response['message'] = "Errore nella preparazione della query: " . $conn->error;
            } else {
                $query->bind_param("iii", $increment, $hunt_id, $user_id);
                if ($query->execute()) {
                    // Ottieni il nuovo valore
                    $query = $conn->prepare("SELECT tentativi FROM tblCacce WHERE idCaccia = ? AND utenteId = ?");
                    $query->bind_param("ii", $hunt_id, $user_id);
                    $query->execute();
                    $result = $query->get_result();
                    if ($row = $result->fetch_assoc()) {
                        $response['success'] = true;
                        $response['tentativi'] = $row['tentativi'];
                    }
                } else {
                    $response['message'] = "Errore nell'aggiornamento: " . $conn->error;
                }
            }
            
            // Invia risposta JSON
            header('Content-Type: application/json');
            echo json_encode($response);
            exit();
        }
        
        // Azione: Completa caccia via AJAX
        if ($_POST['ajax_action'] === 'complete') {
            $hunt_id = $_POST['hunt_id'];
            
            $query = $conn->prepare("UPDATE tblCacce SET isCompleted = 1, DataFine = NOW() WHERE idCaccia = ? AND utenteId = ?");
            if ($query === false) {
                $response['message'] = "Errore nella preparazione della query: " . $conn->error;
            } else {
                $query->bind_param("ii", $hunt_id, $user_id);
                if ($query->execute()) {
                    $response['success'] = true;
                } else {
                    $response['message'] = "Errore nel completamento: " . $conn->error;
                }
            }
            
            // Invia risposta JSON
            header('Content-Type: application/json');
            echo json_encode($response);
            exit();
        }
        
        // Azione: Ottieni metodi per un gioco specifico
        if ($_POST['ajax_action'] === 'get_methods') {
            $game_id = $_POST['game_id'] ?? 0;
            
            if ($game_id) {
                // Modifica della query per usare la tabella di relazione tblGiochiMetodi
                $methods_query = $conn->prepare("
                    SELECT tblMetodi.idMetodo as id, tblMetodi.nome 
                    FROM tblMetodi
                    INNER JOIN tblGiochiMetodi ON tblMetodi.idMetodo = tblGiochiMetodi.metodoId
                    WHERE tblGiochiMetodi.giocoId = ?
                    ORDER BY tblMetodi.nome
                ");
                
                if ($methods_query === false) {
                    $response['message'] = "Errore nella preparazione della query: " . $conn->error;
                } else {
                    $methods_query->bind_param("i", $game_id);
                    $methods_query->execute();
                    $result = $methods_query->get_result();
                    
                    $methods = [];
                    while ($method = $result->fetch_assoc()) {
                        $methods[] = $method;
                    }
                    
                    $response['success'] = true;
                    $response['methods'] = $methods;
                }
            } else {
                $response['message'] = "ID gioco non valido";
            }
            
            // Invia risposta JSON
            header('Content-Type: application/json');
            echo json_encode($response);
            exit();
        }
        
        // Azione: Filtraggio cacce per gioco
        if ($_POST['ajax_action'] === 'filter_hunts_by_game' && isset($_POST['game_id'])) {
            $game_id = $_POST['game_id'];
            $user_id = $_SESSION['user_id'];
            
            $query = "SELECT idCaccia FROM tblCacce WHERE utenteId = ? AND giocoId = ? AND isCompleted = 0";
            $stmt = $conn->prepare($query);
            $stmt->bind_param("ii", $user_id, $game_id);
            $stmt->execute();
            $result = $stmt->get_result();
            
            $hunt_ids = [];
            while ($row = $result->fetch_assoc()) {
                $hunt_ids[] = $row['idCaccia'];
            }
            
            $response['success'] = true;
            $response['hunt_ids'] = $hunt_ids;
            
            header('Content-Type: application/json');
            echo json_encode($response);
            exit();
        }
        
        // Azione: Filtraggio cacce per metodo
        if ($_POST['ajax_action'] === 'filter_hunts_by_method' && isset($_POST['method_id'])) {
            $method_id = $_POST['method_id'];
            $user_id = $_SESSION['user_id'];
            
            $query = "SELECT idCaccia FROM tblCacce WHERE utenteId = ? AND metodoId = ? AND isCompleted = 0";
            $stmt = $conn->prepare($query);
            $stmt->bind_param("ii", $user_id, $method_id);
            $stmt->execute();
            $result = $stmt->get_result();
            
            $hunt_ids = [];
            while ($row = $result->fetch_assoc()) {
                $hunt_ids[] = $row['idCaccia'];
            }
            
            $response['success'] = true;
            $response['hunt_ids'] = $hunt_ids;
            
            header('Content-Type: application/json');
            echo json_encode($response);
            exit();
        }
        
        // Azione: Ottieni tutti i metodi disponibili
        if ($_POST['ajax_action'] === 'get_all_methods') {
            $query = "SELECT idMetodo as id, nome FROM tblMetodi ORDER BY nome";
            $stmt = $conn->prepare($query);
            $stmt->execute();
            $result = $stmt->get_result();
            
            $methods = [];
            while ($method = $result->fetch_assoc()) {
                $methods[] = $method;
            }
            
            $response['success'] = true;
            $response['methods'] = $methods;
            
            header('Content-Type: application/json');
            echo json_encode($response);
            exit();
        }
        
        // Azione: Ottieni tutti i giochi disponibili
        if ($_POST['ajax_action'] === 'get_all_games') {
            $query = "SELECT idGioco as id, nome FROM tblGiochi ORDER BY nome";
            $stmt = $conn->prepare($query);
            $stmt->execute();
            $result = $stmt->get_result();
            
            $games = [];
            while ($game = $result->fetch_assoc()) {
                $games[] = $game;
            }
            
            $response['success'] = true;
            $response['games'] = $games;
            
            header('Content-Type: application/json');
            echo json_encode($response);
            exit();
        }
        
        // Azione: Elimina caccia via AJAX
        if ($_POST['ajax_action'] === 'delete_hunt') {
            $hunt_id = $_POST['hunt_id'];
            
            $query = $conn->prepare("DELETE FROM tblCacce WHERE idCaccia = ? AND utenteId = ?");
            if ($query === false) {
                $response['message'] = "Errore nella preparazione della query: " . $conn->error;
            } else {
                $query->bind_param("ii", $hunt_id, $user_id);
                if ($query->execute()) {
                    $response['success'] = true;
                } else {
                    $response['message'] = "Errore nell'eliminazione: " . $conn->error;
                }
            }
            
            // Invia risposta JSON
            header('Content-Type: application/json');
            echo json_encode($response);
            exit();
        }
    }
    
    // Azione: Incrementa tentativi tradizionale (fallback se JS disabilitato)
    if (isset($_POST['increment_counter'])) {
        $hunt_id = $_POST['hunt_id'];
        $increment = $_POST['increment'] ?? 1;
        
        $query = $conn->prepare("UPDATE tblCacce SET tentativi = tentativi + ? WHERE idCaccia = ? AND utenteId = ?");
        if ($query === false) {
            die("Errore nella preparazione della query: " . $conn->error);
        }
        $query->bind_param("iii", $increment, $hunt_id, $user_id);
        $query->execute();
        
        // Reindirizza per evitare il riinvio del form
        header("Location: index.php");
        exit();
    }
    
    // Azione: Crea nuova caccia
    if (isset($_POST['create_hunt'])) {
        $pokemon = trim($_POST['pokemon']);
        $game_id = $_POST['game_id'];
        $method_id = $_POST['method_id'];
        $hasShinyCharm = isset($_POST['hasShinyCharm']) && $_POST['hasShinyCharm'] === '1';
        
        if (!empty($pokemon) && $game_id && $method_id) {
            $query = $conn->prepare("INSERT INTO tblCacce (pokemon, giocoId, metodoId, utenteId, tentativi, DataInizio, isCompleted, hasShinyCharm) VALUES (?, ?, ?, ?, 0, NOW(), 0, ?)");
            if ($query === false) {
                die("Errore nella preparazione della query: " . $conn->error);
            }
            $query->bind_param("siiii", $pokemon, $game_id, $method_id, $user_id, $hasShinyCharm);
            $query->execute();
            
            // Reindirizza per evitare il riinvio del form
            header("Location: index.php");
            exit();
        }
    }
    
    // Azione: Elimina caccia
    if (isset($_POST['delete_hunt'])) {
        $hunt_id = $_POST['hunt_id'];
        
        $query = $conn->prepare("DELETE FROM tblCacce WHERE idCaccia = ? AND utenteId = ?");
        if ($query === false) {
            die("Errore nella preparazione della query: " . $conn->error);
        }
        $query->bind_param("ii", $hunt_id, $user_id);
        $query->execute();
        
        // Reindirizza per evitare il riinvio del form
        header("Location: index.php");
        exit();
    }
}

// Ottieni cacce attive
$query = $conn->prepare("
    SELECT 
        tblCacce.*, 
        tblGiochi.nome AS gioco, 
        tblMetodi.nome AS metodo,
        CASE 
            WHEN tblCacce.hasShinyCharm = 1 THEN tblOddsMetodi.oddsWithCharm 
            ELSE tblOddsMetodi.oddsWithoutCharm 
        END AS odds
    FROM tblCacce
    INNER JOIN tblGiochi ON tblCacce.giocoId = tblGiochi.idGioco
    INNER JOIN tblMetodi ON tblCacce.metodoId = tblMetodi.idMetodo
    LEFT JOIN tblOddsMetodi ON tblCacce.metodoId = tblOddsMetodi.metodoId 
        AND tblCacce.giocoId = tblOddsMetodi.giocoId
    WHERE tblCacce.utenteId = ? AND tblCacce.isCompleted = 0
    ORDER BY tblCacce.DataInizio DESC
");
if ($query === false) {
    die("Errore nella preparazione della query: " . $conn->error);
}
$query->bind_param("i", $user_id);
$query->execute();
$active_hunts = $query->get_result();

// Ottieni lista giochi per il form di nuova caccia
$games_query = $conn->query("SELECT idGioco, nome FROM tblGiochi ORDER BY nome");
if ($games_query === false) {
    die("Errore nella query: " . $conn->error);
}

// Ottieni lista metodi per il form di nuova caccia
$methods_query = $conn->query("SELECT idMetodo, nome FROM tblMetodi ORDER BY nome");
if ($methods_query === false) {
    die("Errore nella query: " . $conn->error);
}

// Statistiche utente
$query = $conn->prepare("SELECT 
                        COUNT(CASE WHEN isCompleted = 0 THEN 1 END) AS cacce_attive,
                        COUNT(CASE WHEN isCompleted = 1 THEN 1 END) AS cacce_completate
                        FROM tblCacce WHERE utenteId = ?");
$query->bind_param("i", $user_id);
$query->execute();
$stats = $query->get_result()->fetch_assoc();
$active_hunts_count = $stats['cacce_attive'];
$completed_hunts_count = $stats['cacce_completate'];

// Calcola la percentuale di completamento della collezione
$total_pokemon = 1008; // Numero totale di Pokémon fino alla nona generazione
$query = $conn->prepare("SELECT COUNT(DISTINCT pokemon) AS found FROM tblCacce WHERE utenteId = ? AND isCompleted = 1");
$query->bind_param("i", $user_id);
$query->execute();
$found_pokemon = $query->get_result()->fetch_assoc()['found'];
$completion_percentage = ($total_pokemon > 0) ? round(($found_pokemon / $total_pokemon) * 100, 1) : 0;
?>

<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SIVACACCIA! - Cacce</title>
    <link href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.1/css/all.min.css" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">
    <link href="assets/css/account.css" rel="stylesheet">
</head>
<body class="<?php echo isset($_SESSION['theme']) && $_SESSION['theme'] === 'light' ? 'light-theme' : ''; ?>">
    <!-- Sidebar -->
    <div class="sidebar">
        <ul class="nav flex-column">
            <li class="nav-item">
                <a class="nav-link active" href="index.php">
                    <i class="fas fa-stopwatch"></i> Cacce
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="pages/collection.php">
                    <i class="fas fa-trophy"></i> Collezione
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="pages/methods.php">
                    <i class="fas fa-search"></i> Metodi
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="pages/leaderboard.php">
                    <i class="fas fa-medal"></i> Classifica
                </a>
            </li>
        </ul>
        
        <div class="sidebar-stats">
            <div class="mb-4">
                <div class="stat-value"><?php echo $active_hunts_count; ?></div>
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
    <header class="header">
        <div class="logo">
            <img src="img/logo.svg" alt="SIVACACCIA! Logo" onerror="this.src='https://via.placeholder.com/30'">
            <h4 class="mb-0">SIVACACCIA!</h4>
        </div>
        <div>
            <a href="logout.php" class="btn btn-link" title="Logout">
                <i class="fas fa-sign-out-alt text-white"></i>
            </a>
            <button class="btn btn-link theme-toggle" title="Dark/Light Mode">
                <i class="fas <?php echo isset($_SESSION['theme']) && $_SESSION['theme'] === 'light' ? 'fa-moon' : 'fa-sun'; ?> text-white"></i>
            </button>
            <button class="btn btn-link account-btn" title="Account">
                <i class="fas fa-user text-white"></i>
            </button>
        </div>
    </header>
    
    <!-- Main Content -->
    <div class="content">
        <div class="main-content">
            <div class="search-container">
                <input type="text" id="searchHunt" placeholder="Cerca...">
            </div>
            
            <!-- Nuova caccia form con autocompletamento -->
            <div class="new-hunt-form">
                <h5 class="mb-3"><i class="fas fa-plus-circle mr-2"></i>Nuova Caccia</h5>
                <form action="index.php" method="post" id="new-hunt-form">
                    <div class="row">
                        <div class="col-md-4 mb-2">
                            <div class="autocomplete-container">
                                <input type="text" id="pokemon-search" name="pokemon" class="form-control bg-dark text-white" placeholder="Nome Pokémon" required autocomplete="off" data-valid="false">
                                <div id="pokemon-results" class="autocomplete-results"></div>
                                <div class="invalid-feedback">
                                    Seleziona un Pokémon valido dall'elenco
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3 mb-2">
                            <select name="game_id" id="game-select" class="form-control bg-dark text-white" required disabled>
                                <option value="">Seleziona gioco</option>
                                <?php while ($game = $games_query->fetch_assoc()): ?>
                                    <option value="<?php echo $game['idGioco']; ?>"><?php echo $game['nome']; ?></option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        <div class="col-md-3 mb-2">
                            <select name="method_id" id="method-select" class="form-control bg-dark text-white" required disabled>
                                <option value="">Seleziona metodo</option>
                            </select>
                        </div>
                        <div class="col-md-2 mb-2">
                            <div class="d-flex flex-column">
                                <div class="form-check mb-2">
                                    <input class="form-check-input" type="checkbox" value="1" id="hasShinyCharm" name="hasShinyCharm">
                                    <label class="form-check-label text-white" for="hasShinyCharm">
                                        <i class="fas fa-star mr-1"></i> Cromamuleto
                                    </label>
                                </div>
                                <button type="submit" name="create_hunt" class="btn btn-primary">Inizia</button>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
            
            <!-- Filtri -->
            <div class="filter-buttons">
                <button class="active">Tutti</button>
                <button>Recenti</button>
                <button>Meno Recenti</button>
                <button>Per Gioco</button>
                <button>Per Metodo</button>
            </div>
            
            <!-- Contenitore per i filtri dropdown con stile migliorato -->
            <div class="filter-dropdown-container mb-3" style="position: static; min-height: 40px; margin-top: 10px;"></div>
            
            <!-- Lista cacce attive -->
            <div class="row">
                <?php if ($active_hunts->num_rows == 0): ?>
                    <div class="col-md-12">
                        <div class="alert alert-info">
                            Non hai cacce attive. Inizia una nuova caccia!
                        </div>
                    </div>
                <?php else: ?>
                    <?php while ($hunt = $active_hunts->fetch_assoc()): ?>
                        <div class="col-md-3 mb-4 hunt-card" data-pokemon="<?php echo strtolower($hunt['pokemon']); ?>" data-method="<?php echo strtolower($hunt['metodo']); ?>">
                            <div class="pokemon-card">
                                <div class="dropdown float-right">
                                    <button class="btn btn-link dropdown-toggle" type="button" data-toggle="dropdown">
                                        <i class="fas fa-ellipsis-v text-white"></i>
                                    </button>
                                    <div class="dropdown-menu dropdown-menu-right">
                                        <form action="index.php" method="post" class="d-inline">
                                            <input type="hidden" name="hunt_id" value="<?php echo $hunt['idCaccia']; ?>">
                                            <button type="submit" name="delete_hunt" class="dropdown-item text-danger">Elimina</button>
                                        </form>
                                    </div>
                                </div>
                                <div class="text-center mb-0">
                                    <img class="pokemon-img" src="" alt="<?php echo $hunt['pokemon']; ?>" data-pokemon="<?php echo $hunt['pokemon']; ?>">
                                </div>
                                <h4 class="text-center"><?php echo $hunt['pokemon']; ?></h4>
                                <div class="odds-info text-center mb-2">
                                    <span class="badge badge-primary">
                                        <i class="fas fa-dice"></i> <?php echo $hunt['odds'] ? $hunt['odds'] : 'Odds N/A'; ?>
                                    </span>
                                </div>
                                <div class="timer" id="timer-<?php echo $hunt['idCaccia']; ?>" data-start="<?php echo $hunt['DataInizio']; ?>">
                                    <i class="far fa-clock"></i> <span>Caricamento...</span>
                                    <button class="btn btn-sm btn-dark pause-timer ml-2" data-hunt-id="<?php echo $hunt['idCaccia']; ?>" title="Pausa">
                                        <i class="fas fa-pause"></i>
                                    </button>
                                    <button class="btn btn-sm btn-dark auto-increment ml-1" data-hunt-id="<?php echo $hunt['idCaccia']; ?>" title="Auto">
                                        <i class="fas fa-sync-alt"></i>
                                    </button>
                                </div>
                                <div class="last-encounter" id="last-encounter-<?php echo $hunt['idCaccia']; ?>">
                                    <i class="fas fa-stopwatch"></i> <span>-</span>
                                </div>
                                <div class="counter-value" id="counter-<?php echo $hunt['idCaccia']; ?>"><?php echo number_format($hunt['tentativi']); ?></div>
                                
                                <div class="d-flex mb-2">
                                    <input type="number" id="increment-<?php echo $hunt['idCaccia']; ?>" class="form-control bg-dark text-white mr-2" value="1" min="1" max="100" style="height: auto; font-size: 1.1rem;">
                                    <button type="button" class="btn btn-primary flex-grow-1 increment-btn" data-hunt-id="<?php echo $hunt['idCaccia']; ?>">
                                        <i class="fas fa-plus-circle"></i> Incrementa
                                    </button>
                                </div>
                                
                                <button type="button" class="btn btn-success w-100 complete-btn" data-hunt-id="<?php echo $hunt['idCaccia']; ?>">
                                    <i class="fas fa-check-circle"></i> Completa!
                                </button>
                            </div>
                        </div>
                    <?php endwhile; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- Scripts -->
    <script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/popper.js@1.16.1/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
    <script src="assets/js/main.js"></script>
    <script src="assets/js/account.js"></script>
    <script>
        $(document).ready(function() {
            // Gestione eliminazione caccia via AJAX
            $('.dropdown-item[name="delete_hunt"]').on('click', function(e) {
                e.preventDefault();
                var huntId = $(this).closest('form').find('input[name="hunt_id"]').val();
                var card = $(this).closest('.hunt-card');
                
                $.ajax({
                    url: 'index.php',
                    type: 'POST',
                    data: {
                        ajax_action: 'delete_hunt',
                        hunt_id: huntId
                    },
                    success: function(response) {
                        if (response.success) {
                            card.fadeOut(300, function() {
                                $(this).remove();
                            });
                        } else {
                            alert('Errore durante l\'eliminazione della caccia');
                        }
                    },
                    error: function() {
                        alert('Errore durante l\'eliminazione della caccia');
                    }
                });
            });
        });
    </script>

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