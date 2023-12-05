<?php
// session_start();
if(isset($_SESSION['userLogin'])){    
    $id = $_SESSION['userLogin'];

    $sql = "SELECT * FROM clients WHERE id = $id";
    $result = mysqli_query($conn, $sql);

    if ($result) {
        // Check if there are rows returned
        if (mysqli_num_rows($result) > 0) {
            $row = mysqli_fetch_assoc($result);
            $id = $row['id'];
            $name = $row['name'];
            $phone = $row['phone'];
            $email= $row['email'];
            $password = $row['password'];
        }
    } 

    
    
}else{
     $currentUrl = "http://$_SERVER[HTTP_HOST]$_SERVER[REQUEST_URI]";
     $_SESSION['url'] = $currentUrl;
     header('location: http://localhost/zoefit/errors/ERROR-403/');
}

?>

