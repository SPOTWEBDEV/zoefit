<?php

if(isset($_POST['reg'])){
         $name = $_POST['name'];
         $email = $_POST['email'];
         $phone = $_POST['phone'];
         $password = $_POST['password'];

         $hash = md5($password);

         if(!empty($name) && !empty($email)&& !empty($phone)&& !empty($password)){
                $select = mysqli_query($connection,"SELECT * FROM `clients` WHERE `phone`='$phone' OR `email`='$email'");

                if(mysqli_num_rows($select)){

                }else{
                    $insert = mysqli_query($connection, "INSERT INTO `clients`(`id`, `name`, `email`, `phone`, `password`) VALUES ('','$name','$email','$phone','$hash')");

                    if($insert){
                           $id = $connection->insert_id;
                           $_SESSION['userLogin'] = $id;
                    }
                }
         }
}

?>