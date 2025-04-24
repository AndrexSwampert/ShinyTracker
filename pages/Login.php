<?php
session_start();
require_once '../config.php';

// Se l'utente è già loggato, reindirizza alla home
if (isset($_SESSION['user_id'])) {
    header("Location: ../index.php");
    exit();
}

$error = '';

// Verifica se il form è stato inviato
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    
    // Verifica se l'utente esiste
    $query = $conn->prepare("SELECT idUtente, username, passwordHash FROM tblUtenti WHERE email = ?");
    if (!$query) {
        die("Errore nella preparazione della query: " . $conn->error);
    }
    
    $query->bind_param("s", $email);
    $query->execute();
    $result = $query->get_result();
    
    if ($result->num_rows == 1) {
        $user = $result->fetch_assoc();
        
        // Verifica la password
        if (password_verify($password, $user['passwordHash'])) {
            // Password corretta, crea la sessione
            $_SESSION['user_id'] = $user['idUtente'];
            $_SESSION['username'] = $user['username'];
            
            // Reindirizza alla home
            header("Location: ../index.php");
            exit();
        } else {
            $error = 'Credenziali non valide. Riprova.';
        }
    } else {
        $error = 'Account non registrato. <a href="register.php">Registrati</a>';
    }
    
    $query->close();
}
?>

<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - SIVACACCIA!</title>
    <!-- Bootstrap CSS -->
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.1/css/all.min.css">
    <!-- Custom CSS -->
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="bg-dark">
    <div class="container">
        <div class="row justify-content-center mt-5">
            <div class="col-lg-5 col-md-7">
                <div class="card bg-dark text-white border-primary">
                    <div class="card-header bg-primary text-white text-center py-3">
                        <h4 class="mb-0 d-flex justify-content-center align-items-center">
                            <img src="../img/logo.svg" height="40" class="mr-2" alt="Logo" style="filter: brightness(0) invert(1);">
                            <span>SIVACACCIA!</span>
                        </h4>
                    </div>
                    <div class="card-body">
                        <h5 class="card-title text-center mb-4">Accedi al tuo account</h5>
                        
                        <?php if(!empty($error)): ?>
                            <div class="alert alert-danger text-center"><?php echo $error; ?></div>
                        <?php endif; ?>
                        
                        <form action="login.php" method="post">
                            <div class="form-group">
                                <label for="email"><i class="fas fa-envelope mr-1"></i> Email</label>
                                <input type="email" class="form-control bg-dark text-white" id="email" name="email" required>
                            </div>
                            <div class="form-group">
                                <label for="password"><i class="fas fa-lock mr-1"></i> Password</label>
                                <input type="password" class="form-control bg-dark text-white" id="password" name="password" required>
                            </div>
                            <button type="submit" class="btn btn-primary btn-block">Accedi</button>
                        </form>
                    </div>
                    <div class="card-footer text-center">
                        Non hai un account? <a href="register.php" class="text-primary">Registrati</a>
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