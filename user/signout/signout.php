<?php


if(isset($_GET['sign-out'])){
    $sign_out = $_GET['sign-out'];

    if($sign_out == $id){
        session_destroy();
        header('location: ./index.php');
    }
}


?>




<a href="./signout.php?sign-out=<?php echo $row['id'] ?>"><button onclick="return confirm('do you wanna sign-out');">sign-out</button></a>