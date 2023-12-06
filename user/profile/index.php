<?php
include('../../configs/database.php');
include('../../configs/style.config.php');
// include('../../configs/clients/authorization.php');

if(isset($_POST['save'])){
         $name = $_POST['name'];
         $email = $_POST['email'];
         $password = $_POST['password'];

         if(!empty($name) && !empty($email) && !empty($password) ){
               $update = mysqli_query($conn,"UPDATE `clients` SET `email`='$email',`password`='$password',`name`='$name'");
               
               if($update){
                   header('location: ./index.php');
               }

         }
}


// session_start();
if(isset($_POST['save'])){
         $name = $_POST['name'];
         $password = $_POST['password'];
         $email = $_POST['email'];

         if(!empty($name) && !empty($email) && !empty($password) ){
               $update = mysqli_query($conn,"UPDATE `clients` SET `name`='$name',`email`='$email',`password`='$password' WHERE `id`='$id'");
               
               if($update){
                   header('location: ./index.php');
               }

         }
}


?>


<!DOCTYPE html>
<html lang="en">

<head>
         <meta charset="UTF-8">
         <meta name="viewport" content="width=device-width, initial-scale=1.0">
         <title>ZOEFEEDS - PROFILE PAGE</title>
         <script src="https://cdn.tailwindcss.com"></script>
</head>

<body>



         <?php include('../../includes/navbar.php')  ?>

         <section class="w-full flex items-center justify-center py-6 mt-5">
                  <form method="POST" class="bg-white w-[400px] px-3 py-2 rounded-lg">
                           <div class="space-y-12 ">


                                    <div class="border-b border-gray-900/10 pb-12">
                                             <h2 class="text-base font-semibold leading-7 text-gray-900">Personal Information</h2>
                                             <p class="mt-1 text-sm leading-6 text-gray-600">Use a permanent address where you can receive mail.</p>

                                             <div class="mt-10 flex flex-col gap-y-3 w-full">
                                                      <div class="sm:col-span-3">
                                                               <label for="name" class="block text-sm font-medium leading-6 text-gray-900">Name</label>
                                                               <div class="mt-2">
                                                                        <input type="text" value="<?php echo $name ?>" name="name" id="name" autocomplete="given-name" class="h-12 block w-full rounded-md border-0 py-1.5 text-gray-900 shadow-sm placeholder:text-gray-400 border-2 px-2 sm:text-sm sm:leading-6">
                                                               </div>
                                                      </div>

                                                      <div class="sm:col-span-3">
                                                               <label for="phone" class="block text-sm font-medium leading-6 text-gray-900">Password</label>
                                                               <div class="mt-2">
                                                                        <input type="text" name="password" id="password" value="<?php echo $password ?>" autocomplete="family-name" class="h-12 block w-full rounded-md border-0 py-1.5 text-gray-900 shadow-sm placeholder:text-gray-400 border-2 px-2 sm:text-sm sm:leading-6">
                                                               </div>
                                                      </div>

                                                      <div class="w-full">
                                                               <label for="email" class="block text-sm font-medium leading-6 text-gray-900">Email address</label>
                                                               <div class="mt-2">
                                                                        <input id="email" value="<?php echo $email ?>" name="email" type="email" autocomplete="email" class="h-12 block w-full rounded-md border-0 py-1.5 text-gray-900 shadow-sm placeholder:text-gray-400 border-2 px-2 sm:text-sm sm:leading-6">
                                                               </div>
                                                      </div>









                                             </div>
                                    </div>


                           </div>

                           <div class="mt-6 flex items-center justify-end gap-x-6">
                                    <button type="button" class="text-sm font-semibold leading-6 text-gray-900">Cancel</button>
                                    <button name="save" type="submit" style="background: #0A96ADff;" type="submit" class="rounded-md px-3 py-2 text-sm  text-white shadow-sm focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600">Save</button>
                           </div>
                  </form>
         </section>

         <?php include('../../includes/footer.php')  ?>


</body>

</html>