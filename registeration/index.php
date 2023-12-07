<?php


include('../configs/database.php');
include('../configs/style.config.php');
include('../configs/style.config.php');
// header('Location: http://localhost/zoefit');

$url = "";
if (isset($_SESSION['url'])) {
       $url =  $_SESSION['url'];
} else {
       $url = "http://localhost/zoefit/";
}
$location = 'location:' . $url;


?>

<!DOCTYPE html>
<html lang="en">

<head>
       <meta charset="UTF-8">
       <meta name="viewport" content="width=device-width, initial-scale=1.0">
       <title>ZOEFEED - REGERATION</title>
       <script src="https://cdn.tailwindcss.com"></script>
       <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-EVSTQN3/azprG1Anm3QDgpJLIm9Nao0Yz1ztcQTwFspd3yD65VohhpuuCOmLASjC" crossorigin="anonymous">
       <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/js/bootstrap.bundle.min.js" integrity="sha384-MrcW6ZMFYlzcLA8Nl+NtUVF0sA7MsXsP1UyJoMp4YLEuNSfAP+JcXn/tWtIaxVXM" crossorigin="anonymous"></script>
       <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.2/font/bootstrap-icons.min.css">
       <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</head>

<body>
       <?php

       ob_start();


       if (isset($_POST['reg'])) {
              $name = $_POST['name'];
              $email = $_POST['email'];
              $phone = $_POST['phone'];
              $password = $_POST['password'];

              $hash = md5($password);


              if (!empty($name) && !empty($email) && !empty($phone) && !empty($password)) {

                     $select = mysqli_query($conn, "SELECT * FROM `clients` WHERE `phone`='$phone' OR `email`='$email'");



                     if (mysqli_num_rows($select)) {
                            echo '<script>
                                      window.onload = function(){
                                             Swal.fire({
                                             title: "Email/Phone Already Taken",
                                             text: "Sorry, this email/phone is already taken.",
                                             icon: "error"
                                             });
                                          }
                                   </script>';
                     } else {
                            $insert = mysqli_query($conn, "INSERT INTO `clients`(`id`, `name`, `email`, `phone`, `password`) VALUES ('','$name','$email','$phone','$password')");

                            if ($insert) {
                                   $id = $conn->insert_id;
                                   $_SESSION['userLogin'] = $id;
                                   header('location: ../index.php');
                                   exit();
                                   ob_end_flush();
                            } else {
                                   echo '<script>
                                      window.onload = function(){
                                             Swal.fire({
                                             title: "Internal Server Error",
                                             text: "A generic error indicating that something went wrong on the server side. It could be due to misconfiguration, server overload, or unexpected issues",
                                             icon: "error"
                                             });
                                      }
                                    </script>';
                            }
                     }

                     // header('location: ../index.php');
                     // exit();
              }
       }
       ?>
       <?php include('../includes/navbar.php') ?>



       <div class="flex min-h-full items-end justify-center px-2 text-center sm:items-center sm:p-0">
              <div class="relative transform overflow-hidden rounded-lg  text-left shadow-xl transition-all sm:my-8 sm:w-full sm:max-w-lg">
                     <div class="bg-white px-4 pb-4 pt-2 sm:p-6 sm:pb-4">
                            <div class="flex min-h-full flex-col justify-center px-6 py-1 lg:px-8">
                                   <div class="sm:mx-auto sm:w-full sm:max-w-sm">
                                          <h2 class="mt-10 text-center text-2xl  leading-9 tracking-tight text-gray-900">Sign up to your account</h2>
                                   </div>

                                   <div class="mt-10 sm:mx-auto sm:w-full sm:max-w-sm">
                                          <form class="space-y-6" action="#" method="POST">
                                                 <div>
                                                        <label for="email" class="block text-sm  leading-6 text-gray-900">Name</label>
                                                        <div class="mt-2">
                                                               <input id="name" name="name" type="=name" autocomplete="name" class="block w-full px-2 rounded-md border-2 py-1.5 text-gray-900 border-gray-600  placeholder:text-gray-400  sm:text-sm sm:leading-6">
                                                        </div>
                                                 </div>
                                                 <div>
                                                        <label for="email" class="block text-sm  leading-6 text-gray-900">Email address</label>
                                                        <div class="mt-2">
                                                               <input id="email" name="email" type="email" autocomplete="email" class="block w-full px-2 rounded-md border-2 py-1.5 text-gray-900 border-gray-600 placeholder:text-gray-400  sm:text-sm sm:leading-6">
                                                        </div>
                                                 </div>
                                                 <div>
                                                        <label for="phone" class="block text-sm  leading-6 text-gray-900">Phone Number</label>
                                                        <div class="mt-2">
                                                               <input id="phone" name="phone" type="number" autocomplete="phone" class="block w-full px-2 rounded-md border-2 py-1.5 text-gray-900 border-gray-600 placeholder:text-gray-400  sm:text-sm sm:leading-6">
                                                        </div>
                                                 </div>

                                                 <div>
                                                        <div class="flex items-center justify-between">
                                                               <label for="password" class="block text-sm font-medium leading-6 text-gray-900">Password</label>

                                                        </div>
                                                        <div class="mt-2">
                                                               <input id="password" name="password" type="password" autocomplete="current-password" class="block w-full px-2 rounded-md border-2 py-1.5 text-gray-900 border-gray-600  placeholder:text-gray-400  sm:text-sm sm:leading-6">
                                                        </div>
                                                 </div>

                                                 <div>
                                                        <button name="reg" style="background:#0A96ADff" type="submit" class="flex w-full justify-center rounded-md  bg-clifford px-3 py-1.5 text-sm  leading-6 text-white shadow-sm hover:bg-indigo-500 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600">Sign in</button>
                                                 </div>
                                          </form>

                                          <p class="mt-10 text-center text-sm text-black">
                                                 A member?
                                                 <a href="../login/index.php" id="login" class="font-semibold leading-6 text-clifford hover:text-blue-600">Login now</a>
                                          </p>
                                   </div>
                            </div>
                     </div>

              </div>
       </div>




       <?php include('../includes/footer.php') ?>



</body>

</html>