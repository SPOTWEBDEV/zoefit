<?php



// Create a connection to the MySQL database
$conn = mysqli_connect('localhost','root','','zoefit');

// Check the connection
if (!$conn) {
         die("Connection failed: " . mysqli_connect_error());
}




?>