<?php
// Avvia la sessione per poterla distruggere
session_start();

// Pulisci tutte le variabili di sessione
$_SESSION = array();

// Distruggi la sessione (kaboom)
session_destroy();

// Reindirizza al login
header("Location: ../pages/Login.php");
exit();