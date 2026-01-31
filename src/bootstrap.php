<?php
use Twig\Loader\FilesystemLoader;
use Twig\Environment;

// DB
$dsn = "mysql:host=localhost;dbname=improtresk2026;charset=utf8mb4";
$db = new PDO($dsn, "root", "rootpassword", [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
]);

// Twig
$loader = new FilesystemLoader(__DIR__ . '/../templates');
$twig = new Environment($loader);

// Sessions
session_start();
