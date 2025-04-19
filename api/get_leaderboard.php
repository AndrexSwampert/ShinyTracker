<?php
session_start();
require '../config.php';

// Verifica che l'utente sia loggato
if (!isset($_SESSION['user_id'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Utente non autenticato']);
    exit();
}

// Recupera l'ID dell'utente dalla sessione
$user_id = $_SESSION['user_id'];

// Verifica che sia stata specificata un'azione
if (!isset($_POST['filter'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Nessun filtro specificato']);
    exit();
}

$filter = $_POST['filter'];
$data = [];
$user_rank = 0;

// Query in base al filtro selezionato
switch ($filter) {
    case 'Più Shinies':
        // Query per il numero totale di shinies catturati
        $query = $conn->prepare("SELECT 
                                tblUtenti.idUtente, 
                                tblUtenti.username, 
                                COUNT(CASE WHEN tblCacce.isCompleted = 1 THEN 1 END) AS shinies_catturati,
                                COUNT(DISTINCT CASE WHEN tblCacce.isCompleted = 1 THEN tblCacce.pokemon END) AS pokemon_diversi,
                                MIN(tblCacce.DataFine) AS prima_cattura,
                                IFNULL(AVG(tblCacce.tentativi), 0) AS media_incontri
                                FROM tblUtenti
                                LEFT JOIN tblCacce ON tblUtenti.idUtente = tblCacce.utenteId
                                GROUP BY tblUtenti.idUtente
                                ORDER BY shinies_catturati DESC
                                LIMIT 50");
        
        // Query per il rank dell'utente corrente
        $rank_query = $conn->prepare("SELECT tblUtenti.idUtente, 
                                    COUNT(CASE WHEN tblCacce.isCompleted = 1 THEN 1 END) AS shinies_catturati
                                    FROM tblUtenti
                                    LEFT JOIN tblCacce ON tblUtenti.idUtente = tblCacce.utenteId
                                    GROUP BY tblUtenti.idUtente
                                    ORDER BY shinies_catturati DESC");
        break;
        
    case 'Pokémon Unici':
        // Query per il numero di specie di Pokémon uniche catturate
        $query = $conn->prepare("SELECT 
                                tblUtenti.idUtente, 
                                tblUtenti.username, 
                                COUNT(CASE WHEN tblCacce.isCompleted = 1 THEN 1 END) AS shinies_catturati,
                                COUNT(DISTINCT CASE WHEN tblCacce.isCompleted = 1 THEN tblCacce.pokemon END) AS pokemon_diversi,
                                MIN(tblCacce.DataFine) AS prima_cattura,
                                IFNULL(AVG(tblCacce.tentativi), 0) AS media_incontri
                                FROM tblUtenti
                                LEFT JOIN tblCacce ON tblUtenti.idUtente = tblCacce.utenteId
                                GROUP BY tblUtenti.idUtente
                                ORDER BY pokemon_diversi DESC
                                LIMIT 50");
        
        // Query per il rank dell'utente corrente
        $rank_query = $conn->prepare("SELECT tblUtenti.idUtente, 
                                    COUNT(DISTINCT CASE WHEN tblCacce.isCompleted = 1 THEN tblCacce.pokemon END) AS pokemon_diversi
                                    FROM tblUtenti
                                    LEFT JOIN tblCacce ON tblUtenti.idUtente = tblCacce.utenteId
                                    GROUP BY tblUtenti.idUtente
                                    ORDER BY pokemon_diversi DESC");
        break;
        
    case 'Velocità Media':
        // Query per la velocità media di cattura (media incontri più bassa)
        $query = $conn->prepare("SELECT 
                                tblUtenti.idUtente, 
                                tblUtenti.username, 
                                COUNT(CASE WHEN tblCacce.isCompleted = 1 THEN 1 END) AS shinies_catturati,
                                COUNT(DISTINCT CASE WHEN tblCacce.isCompleted = 1 THEN tblCacce.pokemon END) AS pokemon_diversi,
                                MIN(tblCacce.DataFine) AS prima_cattura,
                                IFNULL(AVG(CASE WHEN tblCacce.isCompleted = 1 THEN tblCacce.tentativi ELSE NULL END), 0) AS media_incontri
                                FROM tblUtenti
                                LEFT JOIN tblCacce ON tblUtenti.idUtente = tblCacce.utenteId
                                GROUP BY tblUtenti.idUtente
                                HAVING shinies_catturati > 0
                                ORDER BY media_incontri ASC
                                LIMIT 50");
        
        // Query per il rank dell'utente corrente
        $rank_query = $conn->prepare("SELECT tblUtenti.idUtente, 
                                    IFNULL(AVG(CASE WHEN tblCacce.isCompleted = 1 THEN tblCacce.tentativi ELSE NULL END), 0) AS media_incontri,
                                    COUNT(CASE WHEN tblCacce.isCompleted = 1 THEN 1 END) AS shinies_catturati
                                    FROM tblUtenti
                                    LEFT JOIN tblCacce ON tblUtenti.idUtente = tblCacce.utenteId
                                    GROUP BY tblUtenti.idUtente
                                    HAVING shinies_catturati > 0
                                    ORDER BY media_incontri ASC");
        break;
        
    case 'Per Metodo':
        // Query per le statistiche raggruppate per metodo
        $query = $conn->prepare("SELECT 
                                tblUtenti.idUtente, 
                                tblUtenti.username,
                                tblMetodi.nome AS metodo,
                                COUNT(CASE WHEN tblCacce.isCompleted = 1 THEN 1 END) AS shinies_per_metodo,
                                COUNT(DISTINCT CASE WHEN tblCacce.isCompleted = 1 THEN tblCacce.pokemon END) AS pokemon_diversi_per_metodo,
                                IFNULL(AVG(CASE WHEN tblCacce.isCompleted = 1 THEN tblCacce.tentativi ELSE NULL END), 0) AS media_incontri
                                FROM tblUtenti
                                INNER JOIN tblCacce ON tblUtenti.idUtente = tblCacce.utenteId
                                INNER JOIN tblMetodi ON tblCacce.metodoId = tblMetodi.idMetodo
                                WHERE tblCacce.isCompleted = 1
                                GROUP BY tblUtenti.idUtente, tblMetodi.idMetodo
                                ORDER BY shinies_per_metodo DESC
                                LIMIT 50");
        
        // Query per il rank dell'utente corrente per il metodo più utilizzato
        $rank_query = $conn->prepare("SELECT sub.idUtente, sub.shinies_per_metodo
                                    FROM (
                                        SELECT 
                                            tblUtenti.idUtente, 
                                            tblMetodi.idMetodo,
                                            COUNT(CASE WHEN tblCacce.isCompleted = 1 THEN 1 END) AS shinies_per_metodo
                                        FROM tblUtenti
                                        INNER JOIN tblCacce ON tblUtenti.idUtente = tblCacce.utenteId
                                        INNER JOIN tblMetodi ON tblCacce.metodoId = tblMetodi.idMetodo
                                        WHERE tblCacce.isCompleted = 1
                                        GROUP BY tblUtenti.idUtente, tblMetodi.idMetodo
                                    ) AS sub
                                    INNER JOIN (
                                        SELECT idUtente, MAX(shinies_per_metodo) AS max_shinies
                                        FROM (
                                            SELECT 
                                                tblUtenti.idUtente, 
                                                tblMetodi.idMetodo,
                                                COUNT(CASE WHEN tblCacce.isCompleted = 1 THEN 1 END) AS shinies_per_metodo
                                            FROM tblUtenti
                                            INNER JOIN tblCacce ON tblUtenti.idUtente = tblCacce.utenteId
                                            INNER JOIN tblMetodi ON tblCacce.metodoId = tblMetodi.idMetodo
                                            WHERE tblCacce.isCompleted = 1
                                            GROUP BY tblUtenti.idUtente, tblMetodi.idMetodo
                                        ) AS temp
                                        GROUP BY idUtente
                                    ) AS max_sub ON sub.idUtente = max_sub.idUtente AND sub.shinies_per_metodo = max_sub.max_shinies
                                    ORDER BY sub.shinies_per_metodo DESC");
        break;
    
    // Filtro specifico per metodo selezionato
    case 'method_specific':
        if (!isset($_POST['method_id']) || empty($_POST['method_id'])) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'ID metodo non specificato']);
            exit();
        }
        
        $method_id = $_POST['method_id'];
        
        // Query per le statistiche degli utenti con il metodo selezionato
        $query = $conn->prepare("SELECT 
                                tblUtenti.idUtente, 
                                tblUtenti.username,
                                tblMetodi.nome AS metodo,
                                COUNT(CASE WHEN tblCacce.isCompleted = 1 THEN 1 END) AS shinies_per_metodo,
                                COUNT(DISTINCT CASE WHEN tblCacce.isCompleted = 1 THEN tblCacce.pokemon END) AS pokemon_diversi_per_metodo,
                                IFNULL(AVG(CASE WHEN tblCacce.isCompleted = 1 THEN tblCacce.tentativi ELSE NULL END), 0) AS media_incontri
                                FROM tblUtenti
                                INNER JOIN tblCacce ON tblUtenti.idUtente = tblCacce.utenteId
                                INNER JOIN tblMetodi ON tblCacce.metodoId = tblMetodi.idMetodo
                                WHERE tblCacce.isCompleted = 1 AND tblMetodi.idMetodo = ?
                                GROUP BY tblUtenti.idUtente
                                ORDER BY shinies_per_metodo DESC
                                LIMIT 50");
        
        $query->bind_param("i", $method_id);
        
        // Query per il rank dell'utente corrente per il metodo selezionato
        $rank_query = $conn->prepare("SELECT 
                                    tblUtenti.idUtente,
                                    COUNT(CASE WHEN tblCacce.isCompleted = 1 THEN 1 END) AS shinies_per_metodo
                                    FROM tblUtenti
                                    LEFT JOIN tblCacce ON tblUtenti.idUtente = tblCacce.utenteId
                                    WHERE tblCacce.isCompleted = 1 AND tblCacce.metodoId = ?
                                    GROUP BY tblUtenti.idUtente
                                    ORDER BY shinies_per_metodo DESC");
        
        $rank_query->bind_param("i", $method_id);
        break;
        
    // Endpoint per ottenere tutti i metodi disponibili
    case 'get_all_methods':
        $query = $conn->prepare("SELECT 
                               tblMetodi.idMetodo AS id,
                               tblMetodi.nome,
                               COUNT(DISTINCT CASE WHEN tblCacce.isCompleted = 1 THEN tblCacce.utenteId END) AS utenti_count
                               FROM tblMetodi
                               LEFT JOIN tblCacce ON tblMetodi.idMetodo = tblCacce.metodoId
                               GROUP BY tblMetodi.idMetodo
                               HAVING utenti_count > 0
                               ORDER BY tblMetodi.nome");
        
        $query->execute();
        $result = $query->get_result();
        
        $methods = [];
        while ($row = $result->fetch_assoc()) {
            $methods[] = $row;
        }
        
        header('Content-Type: application/json');
        echo json_encode([
            'success' => true,
            'methods' => $methods
        ]);
        exit();
    
    default:
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Filtro non valido']);
        exit();
}

// Esegui la query principale
if ($query === false) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Errore nella preparazione della query: ' . $conn->error]);
    exit();
}

$query->execute();
$result = $query->get_result();

// Prepara l'array dei dati
while ($row = $result->fetch_assoc()) {
    $data[] = $row;
}

// Determina il rank dell'utente corrente
if ($rank_query === false) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Errore nella preparazione della query: ' . $conn->error]);
    exit();
}

$rank_query->execute();
$all_users = $rank_query->get_result();

$count = 0;
while ($row = $all_users->fetch_assoc()) {
    $count++;
    if ($row['idUtente'] == $user_id) {
        $user_rank = $count;
        break;
    }
}

// Restituisci i dati in formato JSON
header('Content-Type: application/json');
echo json_encode([
    'success' => true,
    'data' => $data,
    'user_rank' => $user_rank
]);
exit(); 