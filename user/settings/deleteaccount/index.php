<?php

include('../../../configs/database.php');
include('../../../configs/clients/authorization.php');

if(isset($_GET['delete'])){
    $id = $_GET['delete'];

    $delete = mysqli_query($conn, "DELETE FROM `clients` WHERE `id`='$id'");

    if($delete){
        header('location: ./index.php');
    }else{
        echo 'cant delete';
    }
}

?>


<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Delete Account</title>
</head>

<body>
    <a href="./index.php?delete=<?php echo $row['id'] ?>"><button onclick="return confirm('are you sure');">delete</button></a>
</body>

</html>