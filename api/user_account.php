<?php

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();
require '../config.php';

// Verifica connessione database
if ($conn->connect_error) {
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false, 
        'message' => 'Errore di connessione al database: ' . $conn->connect_error
    ]);
    exit();
}

// Verifica che l'utente sia loggato
if (!isset($_SESSION['user_id'])) {
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false, 
        'message' => 'Utente non autenticato'
    ]);
    exit();
}

// Recupera l'ID dell'utente dalla sessione
$user_id = $_SESSION['user_id'];
error_log("user_account.php - User ID: " . $user_id);

// Verifica che sia stata specificata un'azione
if (!isset($_POST['action'])) {
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false, 
        'message' => 'Nessuna azione specificata'
    ]);
    exit();
}

$action = $_POST['action'];
$response = ['success' => false, 'message' => 'Azione non riconosciuta'];

error_log("user_account.php - Azione richiesta: " . $action);

// Verifica e ottiene informazioni sulla tabella utenti
$tableColumns = [];
$checkTableQuery = $conn->query("DESCRIBE tblUtenti");
if ($checkTableQuery) {
    while ($col = $checkTableQuery->fetch_assoc()) {
        $tableColumns[] = $col['Field'];
    }
    error_log("user_account.php - Colonne nella tabella tblUtenti: " . implode(', ', $tableColumns));
} else {
    error_log("user_account.php - Impossibile ottenere informazioni sulla struttura della tabella: " . $conn->error);
}



switch ($action) {
    case 'get_user_data':
        try {
            $query = $conn->prepare("SELECT idUtente, username, email, DATE_FORMAT(dataCreazione, '%d/%m/%Y, %H:%i:%s') AS registrationDate 
                                FROM tblUtenti WHERE idUtente = ?");
            if ($query === false) {
                throw new Exception('Errore nella preparazione della query: ' . $conn->error);
            }
            
            $query->bind_param("i", $user_id);
            if (!$query->execute()) {
                throw new Exception('Errore nell\'esecuzione della query: ' . $query->error);
            }
            
            $result = $query->get_result();
            
            if ($result->num_rows === 1) {
                $user_data = $result->fetch_assoc();
                $response = [
                    'success' => true,
                    'user_data' => $user_data
                ];
            } else {
                throw new Exception('Utente non trovato');
            }
        } catch (Exception $e) {
            error_log("user_account.php - Errore in get_user_data: " . $e->getMessage());
            $response = ['success' => false, 'message' => $e->getMessage()];
        }
        break;
        
    // Cambia il nome utente
    case 'change_username':
        try {
            if (!isset($_POST['new_username']) || empty($_POST['new_username'])) {
                throw new Exception('Nome utente mancante');
            }
            
            $new_username = trim($_POST['new_username']);
            
            // Verifica che il nome utente non sia già in uso
            $check_query = $conn->prepare("SELECT idUtente FROM tblUtenti WHERE username = ? AND idUtente != ?");
            if ($check_query === false) {
                throw new Exception('Errore nella preparazione della query: ' . $conn->error);
            }
            
            $check_query->bind_param("si", $new_username, $user_id);
            if (!$check_query->execute()) {
                throw new Exception('Errore nell\'esecuzione della query: ' . $check_query->error);
            }
            
            $check_result = $check_query->get_result();
            
            if ($check_result->num_rows > 0) {
                throw new Exception('Nome utente già in uso');
            }
            
            // Aggiorna il nome utente
            $update_query = $conn->prepare("UPDATE tblUtenti SET username = ? WHERE idUtente = ?");
            if ($update_query === false) {
                throw new Exception('Errore nella preparazione della query: ' . $conn->error);
            }
            
            $update_query->bind_param("si", $new_username, $user_id);
            if (!$update_query->execute()) {
                throw new Exception('Errore nell\'aggiornamento del nome utente: ' . $update_query->error);
            }
            
            $response = [
                'success' => true,
                'message' => 'Nome utente aggiornato con successo',
                'new_username' => $new_username
            ];
        } catch (Exception $e) {
            error_log("user_account.php - Errore in change_username: " . $e->getMessage());
            $response = ['success' => false, 'message' => $e->getMessage()];
        }
        break;
        
    // Cambia la password
    case 'change_password':
        try {
            if (!isset($_POST['current_password']) || empty($_POST['current_password']) || 
                !isset($_POST['new_password']) || empty($_POST['new_password']) || 
                !isset($_POST['confirm_password']) || empty($_POST['confirm_password'])) {
                throw new Exception('Tutti i campi sono obbligatori');
            }
            
            $current_password = $_POST['current_password'];
            $new_password = $_POST['new_password'];
            $confirm_password = $_POST['confirm_password'];
            
            if ($new_password !== $confirm_password) {
                throw new Exception('Le nuove password non corrispondono');
            }
            
            // Verifica la password corrente
            $query = $conn->prepare("SELECT passwordHash FROM tblUtenti WHERE idUtente = ?");
            if ($query === false) {
                throw new Exception('Errore nella preparazione della query: ' . $conn->error);
            }
            
            $query->bind_param("i", $user_id);
            if (!$query->execute()) {
                throw new Exception('Errore nell\'esecuzione della query: ' . $query->error);
            }
            
            $result = $query->get_result();
            if ($result->num_rows !== 1) {
                throw new Exception('Utente non trovato');
            }
            
            $user = $result->fetch_assoc();
            
            if (!password_verify($current_password, $user['passwordHash'])) {
                throw new Exception('Password attuale non corretta');
            }
            
            // Aggiorna la password
            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
            $update_query = $conn->prepare("UPDATE tblUtenti SET passwordHash = ? WHERE idUtente = ?");
            if ($update_query === false) {
                throw new Exception('Errore nella preparazione della query: ' . $conn->error);
            }
            
            $update_query->bind_param("si", $hashed_password, $user_id);
            if (!$update_query->execute()) {
                throw new Exception('Errore nell\'aggiornamento della password: ' . $update_query->error);
            }
            
            $response = [
                'success' => true,
                'message' => 'Password aggiornata con successo'
            ];
        } catch (Exception $e) {
            error_log("user_account.php - Errore in change_password: " . $e->getMessage());
            $response = ['success' => false, 'message' => $e->getMessage()];
        }
        break;
        
    // Disattiva l'account
    case 'deactivate_account':
        try {
            if (!isset($_POST['confirm_password']) || empty($_POST['confirm_password'])) {
                throw new Exception('Password di conferma mancante');
            }
            
            $confirm_password = $_POST['confirm_password'];
            
            // Verifica la password
            $query = $conn->prepare("SELECT passwordHash FROM tblUtenti WHERE idUtente = ?");
            if ($query === false) {
                throw new Exception('Errore nella preparazione della query: ' . $conn->error);
            }
            
            $query->bind_param("i", $user_id);
            if (!$query->execute()) {
                throw new Exception('Errore nell\'esecuzione della query: ' . $query->error);
            }
            
            $result = $query->get_result();
            if ($result->num_rows !== 1) {
                throw new Exception('Utente non trovato');
            }
            
            $user = $result->fetch_assoc();
            
            if (!password_verify($confirm_password, $user['passwordHash'])) {
                throw new Exception('Password non corretta');
            }
            
            // Elimina completamente l'account dal database
            $delete_query = $conn->prepare("DELETE FROM tblUtenti WHERE idUtente = ?");
            if ($delete_query === false) {
                throw new Exception('Errore nella preparazione della query di eliminazione: ' . $conn->error);
            }
            
            $delete_query->bind_param("i", $user_id);
            if (!$delete_query->execute()) {
                throw new Exception('Errore nell\'eliminazione dell\'account: ' . $delete_query->error);
            }
            
            error_log("user_account.php - Account eliminato permanentemente. User ID: " . $user_id);
            
            // Rimuovi la sessione
            session_destroy();
            $response = [
                'success' => true,
                'message' => 'Account eliminato con successo',
                'redirect' => 'pages/Login.php'
            ];
        } catch (Exception $e) {
            error_log("user_account.php - Errore in deactivate_account: " . $e->getMessage());
            $response = ['success' => false, 'message' => $e->getMessage()];
        }
        break;
}

// Log della risposta
error_log("user_account.php - Risposta: " . json_encode($response));

// Invia la risposta JSON
header('Content-Type: application/json');
echo json_encode($response);
exit();