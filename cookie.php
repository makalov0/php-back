<?php
ob_start();
if (isset($_COOKIE["id"]) == "" && isset($_COOKIE["firstname"]) == "" && isset($_COOKIE["lastname"]) == "" && isset($_COOKIE["role"]) == ""){
    header("Location: login.php");
}
?>