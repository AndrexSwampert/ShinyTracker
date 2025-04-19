<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

error_log("config.php - Avvio del file di configurazione del database");

// Configurazione del database
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "shiny_tracker";

// Crea connessione
$conn = new mysqli($servername, $username, $password, $dbname);
?>
