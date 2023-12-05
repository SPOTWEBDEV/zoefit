<?php
session_start();

if(isset($_SESSION['userLogin'])){    
    $id = $_SESSION['userLogin'];
    
}else{
    $currentURL = $_SERVER['REQUEST_URI'];
    $_SESSION['url'] = $currentURL;
     header('location: http://localhost/zoefit/errors/ERROR-403/');
}

?>

