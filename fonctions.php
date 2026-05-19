<?php
// fonction.php

function connectDatabase() {
    $server = "localhost";
    $database = "bibliotheque";
    $user = "root";
    $pass = "";

    try {
        return new PDO(
            "mysql:host=$server;dbname=$database;charset=utf8mb4",
            $user,
            $pass,
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
            ]
        );
    } catch(PDOException $error) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => "Erreur de connexion BDD."]);
        exit;
    }
}

function checkBookData($title, $author, $year) {
    if (empty(trim($title)) || empty(trim($author))) {
        return "Le titre et l'auteur sont obligatoires.";
    }
    if (!empty($year) && (!is_numeric($year) || $year < 0 || $year > (int)date("Y"))) {
        return "L'année de publication est invalide.";
    }
    return true; // Tout est OK
}
?>