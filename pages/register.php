<?php
session_start();
require_once '../config.php';

// Se l'utente è già loggato, reindirizza alla home
if (isset($_SESSION['user_id'])) {
    header("Location: ../index.php");
    exit();
}

$error = '';
$success = '';

// Verifica se il form è stato inviato
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = trim($_POST['email']);
    $username = trim($_POST['username']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    
    // Verifica se le password corrispondono
    if ($password !== $confirm_password) {
        $error = "Le password non corrispondono.";
    } else {
        // Verifica se l'email o l'username sono già in uso
        $query = $conn->prepare("SELECT * FROM tblUtenti WHERE email = ? OR username = ?");
        if (!$query) {
            die("Errore nella preparazione della query: " . $conn->error);
        }
        
        $query->bind_param("ss", $email, $username);
        $query->execute();
        $result = $query->get_result();
        
        if ($result->num_rows > 0) {
            $user = $result->fetch_assoc();
            if ($user['email'] === $email) {
                $error = "Email già in uso.";
            } else {
                $error = "Username già in uso.";
            }
        } else {
            // Hash della password
            $password_hash = password_hash($password, PASSWORD_DEFAULT);
            
            // Inserisci il nuovo utente
            $insert_query = $conn->prepare("INSERT INTO tblUtenti (email, username, passwordHash, dataCreazione) VALUES (?, ?, ?, NOW())");
            if (!$insert_query) {
                die("Errore nella preparazione della query: " . $conn->error);
            }
            
            $insert_query->bind_param("sss", $email, $username, $password_hash);
            
            if ($insert_query->execute()) {
                $success = "Registrazione completata con successo!";
                // Reindirizza alla pagina di login dopo 2 secondi
                header("Refresh: 2; URL=login.php");
            } else {
                $error = "Errore durante la registrazione: " . $insert_query->error;
            }
            
            $insert_query->close();
        }
        
        $query->close();
    }
}
?>

<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registrazione - SiVACACCIA!</title>
    <!-- Bootstrap CSS -->
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.1/css/all.min.css">
    <!-- Custom CSS -->
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body class="bg-dark">
    <div class="container">
        <div class="row justify-content-center mt-5">
            <div class="col-lg-6 col-md-8">
                <div class="card bg-dark text-white border-primary">
                    <div class="card-header bg-primary text-white text-center py-3">
                        <h4 class="mb-0 d-flex justify-content-center align-items-center">
                            <img src="../img/logo.svg" height="40" class="mr-2" alt="Logo" style="filter: brightness(0) invert(1);">
                            <span>SIVACACCIA!</span>
                        </h4>
                    </div>
                    <div class="card-body">
                        <h5 class="card-title text-center mb-4">Crea un nuovo account</h5>
                        
                        <?php if(!empty($error)): ?>
                            <div class="alert alert-danger text-center"><?php echo $error; ?></div>
                        <?php endif; ?>
                        
                        <?php if(!empty($success)): ?>
                            <div class="alert alert-success text-center"><?php echo $success; ?></div>
                        <?php endif; ?>
                        
                        <form action="register.php" method="post">
                            <div class="form-group">
                                <label for="email"><i class="fas fa-envelope mr-1"></i> Email</label>
                                <input type="email" class="form-control bg-dark text-white" id="email" name="email" required>
                            </div>
                            <div class="form-group">
                                <label for="username"><i class="fas fa-user mr-1"></i> Username</label>
                                <input type="text" class="form-control bg-dark text-white" id="username" name="username" required>
                            </div>
                            <div class="form-group">
                                <label for="password"><i class="fas fa-lock mr-1"></i> Password</label>
                                <input type="password" class="form-control bg-dark text-white" id="password" name="password" required>
                            </div>
                            <div class="form-group">
                                <label for="confirm_password"><i class="fas fa-check-circle mr-1"></i> Conferma Password</label>
                                <input type="password" class="form-control bg-dark text-white" id="confirm_password" name="confirm_password" required>
                            </div>
                            <button type="submit" class="btn btn-primary btn-block">Registrati</button>
                        </form>
                    </div>
                    <div class="card-footer text-center">
                        Hai già un account? <a href="login.php" class="text-primary">Accedi</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Bootstrap JS -->
    <script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/popper.js@1.16.1/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
</body>
</html>