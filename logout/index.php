<?php
$base_url = $_SERVER['HTTP_HOST'] === 'localhost' 
    ? 'http://localhost/hadasyduendes/' 
    : 'https://www.hadasyduendes.cl/';

// Configurar cookies con dominio global antes de destruir
ini_set('session.cookie_domain', '.hadasyduendes.cl');
ini_set('session.cookie_samesite', 'None');
ini_set('session.cookie_secure', '1');
ini_set('session.cookie_httponly', '1');

session_start(); // Inicia la sesión actual

// Elimina todas las variables de sesión
$_SESSION = [];

// Destruye la sesión
session_destroy();

// Borra la cookie de sesión
if (isset($_COOKIE['PHPSESSID'])) {
    setcookie('PHPSESSID', '', time() - 3600, '/', '.hadasyduendes.cl', true, true);
}

// Redirige al usuario a la página de inicio de sesión
header('Location: ' . $base_url . 'auth/login');
exit;