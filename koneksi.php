<?php
$con = new mysqli("localhost", "root", "", "test");
if ($con->connect_error) {
    die("Koneksi gagal: " . $con->connect_error);
}
?>
