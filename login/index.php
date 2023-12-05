<?php

include('../configs/database.php');
include('../configs/style.config.php');
$url = "";
if (isset($_SESSION['url'])) {
       $url =  $_SESSION['url'];
} else {
       $url = "http://localhost/zoefit/";
}
$location = 'location:' . $url;
echo $location;



?>
<a href="../user/"></a>

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
</head>

<body>
       <?php
       if (isset($_POST['reg'])) {
              $email = $_POST['email'];
              $password = $_POST['password'];

              if (!empty($email) && !empty($password)) {
                     $select = mysqli_query($conn, "SELECT * FROM `clients` WHERE `email`='$email' AND `password`='$password'");

                     if (mysqli_num_rows($select)) {
                            while ($row = mysqli_fetch_assoc($select)) {
                                   $id = $row['id'];
                                   $_SESSION['userLogin'] = $id;
                                   echo '<script>alert("hi")</script>';
                                   header('location: ../user/profile');
                            }
                     } else {
                            echo 'error on login';
                     }
              }
       }

       ?>
       <?php include('../includes/navbar.php') ?>




       <div class="flex min-h-full items-end justify-center p-4 text-center sm:items-center sm:p-0">
              <div class="relative transform overflow-hidden rounded-lg  text-left shadow-xl transition-all sm:my-8 sm:w-full sm:max-w-lg">
                     <div class="bg-white px-4 pb-4 pt-5 sm:p-6 sm:pb-4">
                            <div class="flex min-h-full flex-col justify-center px-6 py-2 lg:px-8">
                                   <div class="sm:mx-auto sm:w-full sm:max-w-sm">
                                          <h2 class="mt-10 text-center text-2xl  leading-9 tracking-tight text-gray-900">Sign in to your account</h2>
                                   </div>

                                   <div class="mt-10 sm:mx-auto sm:w-full sm:max-w-sm">
                                          <form method="POST" class="space-y-6">
                                                 <div>
                                                        <label for="email" class="block text-sm  leading-6 text-gray-900">Email address</label>
                                                        <div class="mt-2">
                                                               <input id="email" name="email" type="email" autocomplete="email" required class="block w-full px-2 rounded-md border-2 py-1.5 text-gray-900 border-black  placeholder:text-gray-400  sm:text-sm sm:leading-6">
                                                        </div>
                                                 </div>

                                                 <div>
                                                        <div class="flex items-center justify-between">
                                                               <label for="password" class="block text-sm font-medium leading-6 text-gray-900">Password</label>
                                                               <div class="text-sm">
                                                                      <a href="#" class="text-blue-600">Forgot password?</a>
                                                               </div>
                                                        </div>
                                                        <div class="mt-2">
                                                               <input id="password" name="password" type="password" autocomplete="current-password" required class="block w-full px-2 rounded-md border-2 py-1.5 text-gray-900 border-black  placeholder:text-gray-400  sm:text-sm sm:leading-6">
                                                        </div>
                                                 </div>

                                                 <div>
                                                        <button name="reg" style="background:#0A96ADff" type="submit" class="flex w-full justify-center rounded-md  bg-clifford px-3 py-1.5 text-sm  leading-6 text-white shadow-sm hover:bg-indigo-500 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600">Sign in</button>
                                                 </div>
                                          </form>

                                          <p class="mt-10 text-center text-sm text-black">
                                                 Not a member?
                                                 <a href="../registeration/" id="register" class="font-semibold leading-6 text-clifford hover:text-blue-600">create an account now</a>
                                          </p>
                                   </div>
                            </div>
                     </div>

              </div>
       </div>





       <?php include('../includes/footer.php') ?>

</body>

</html>