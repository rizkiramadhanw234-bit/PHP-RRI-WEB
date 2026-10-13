<?php
$servername = "localhost";
$username   = "root";
$password   = ""; // Ganti dengan password database Anda
$dbname     = "rri_music";   // Ganti dengan nama database Anda

// Buat koneksi
$conn = new mysqli($servername, $username, $password, $dbname);

// Cek koneksi
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Set charset
$conn->set_charset("utf8mb4");

