<?php
$authorization = 'false';
if(isset($_SESSION['userLogin'])){
    $authorization  = 'true';    
}


if (isset($_POST['login'])) {
    $phone = $_POST['phone'];
    $password = $_POST['password'];

    if (!empty($phone) && !empty($password)) {
        $select = mysqli_query($connection, "SELECT * FROM `clients` WHERE `phone`='$phone' AND `password`='$password'");

        if (mysqli_num_rows($select)) {
            while($row=mysqli_fetch_assoc($select)){
                $id = $row['id'];
                $_SESSION['userLogin'] = $id;
            }
        } else {
        
        }
    }
}


?>