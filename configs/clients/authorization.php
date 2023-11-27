<?php
session_start();

if(isset($_SESSION['userLogin'])){    
    $id = $_SESSION['userLogin'];
    
}else{
     header('location: http://localhost/zoefit/errors/ERROR-404/');
}

?>

