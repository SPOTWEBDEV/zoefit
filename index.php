<?php

include('./configs/style.config.php');

include('./configs/clients/authorization.php')


?>

<!DOCTYPE html>
<html lang="en">

<head>
         <meta charset="UTF-8">
         <meta name="viewport" content="width=device-width, initial-scale=1.0">
         <title>Document</title>
         <script src="https://cdn.tailwindcss.com"></script>
         <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-EVSTQN3/azprG1Anm3QDgpJLIm9Nao0Yz1ztcQTwFspd3yD65VohhpuuCOmLASjC" crossorigin="anonymous">
         <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/js/bootstrap.bundle.min.js" integrity="sha384-MrcW6ZMFYlzcLA8Nl+NtUVF0sA7MsXsP1UyJoMp4YLEuNSfAP+JcXn/tWtIaxVXM" crossorigin="anonymous"></script>
         <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.2/font/bootstrap-icons.min.css">
</head>

<body>


         <?php include('./includes/navbar.php') ?>

         <div class="overflow-hidden bg-transparent text-white py-24 sm:py-32">
                  <div class="mx-auto max-w-7xl px-6 lg:px-8">
                           <div class="mx-auto grid max-w-2xl grid-cols-1 gap-x-8 gap-y-16 sm:gap-y-20 lg:mx-0 lg:max-w-none lg:grid-cols-2">
                                    <div class="lg:pr-8 lg:pt-4">
                                             <div class="lg:max-w-lg">
                                                      <h2 class="text-base  leading-7 text-white text-indigo-600">Zoefit</h2>
                                                      <p class="mt-2 text-3xl  tracking-tight text-white sm:text-4xl">Zoefit Buy In Style</p>
                                                      <p class="mt-6 text-lg leading-8 text-white text-gray-600">Are you tired of running out of data at the most inconvenient times? Look no further – ZOEFIT is your go-to destination for hassle-free data bundle purchases, ensuring you stay connected whenever and wherever you need it.</p>
                                                      <dl class="mt-10 max-w-xl space-y-8 text-base leading-7 text-white lg:max-w-none">
                                                               <div class="relative pl-9">
                                                                        <dt class="inline  text-white">
                                                                                 <svg class="absolute left-1 top-1 h-5 w-5 text-white" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                                                                          <path fill-rule="evenodd" d="M5.5 17a4.5 4.5 0 01-1.44-8.765 4.5 4.5 0 018.302-3.046 3.5 3.5 0 014.504 4.272A4 4 0 0115 17H5.5zm3.75-2.75a.75.75 0 001.5 0V9.66l1.95 2.1a.75.75 0 101.1-1.02l-3.25-3.5a.75.75 0 00-1.1 0l-3.25 3.5a.75.75 0 101.1 1.02l1.95-2.1v4.59z" clip-rule="evenodd" />
                                                                                 </svg>
                                                                                 Airtime Topup
                                                                        </dt>
                                                                        <dd class="inline">In a world that moves at the speed of communication, having a reliable source for airtime top-ups is essential. Enter ZOEFIT – your destination for seamless and instant airtime recharges, ensuring you're always connected with those who matter.</dd>
                                                               </div>
                                                               <div class="relative pl-9">
                                                                        <dt class="inline  text-white">
                                                                                 <svg class="absolute left-1 top-1 h-5 w-5 text-white" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                                                                          <path fill-rule="evenodd" d="M10 1a4.5 4.5 0 00-4.5 4.5V9H5a2 2 0 00-2 2v6a2 2 0 002 2h10a2 2 0 002-2v-6a2 2 0 00-2-2h-.5V5.5A4.5 4.5 0 0010 1zm3 8V5.5a3 3 0 10-6 0V9h6z" clip-rule="evenodd" />
                                                                                 </svg>
                                                                                 Data Bundle
                                                                        </dt>
                                                                        <dd class="inline">In a world where connectivity is key, ZOEFIT is your gateway to affordable and reliable data bundles. Say goodbye to expensive data plans and hello to uninterrupted connectivity without breaking the bank.</dd>
                                                               </div>

                                                      </dl>
                                             </div>
                                    </div>
                                    <img src="https://tailwindui.com/img/component-images/dark-project-app-screenshot.png" alt="Product screenshot" class="w-[48rem] max-w-none rounded-xl shadow-xl ring-1 ring-gray-400/10 sm:w-[57rem] md:-ml-4 lg:-ml-0" width="2432" height="1442">
                           </div>
                  </div>
         </div>


         <div class=" py-24 sm:py-32">
                  <div class="mx-auto max-w-7xl px-6 lg:px-8">
                           <div class="mx-auto max-w-2xl lg:text-center">
                                    <h2 class="text-4xl leading-7 text-white">service</h2>
                                    <!-- <p class="mt-2 text-2xl  tracking-tight text-gray-900 sm:text-4xl">Zoefit's services include facilitating seamless maritime purchases for clients, ensuring efficient and reliable transactions in the maritime industry. </p> -->

                           </div>
                           <div class="mx-auto mt-16 max-w-2xl sm:mt-20 lg:mt-24 lg:max-w-4xl">
                                    <dl class="grid max-w-xl grid-cols-1 gap-x-8 gap-y-10 lg:max-w-none lg:grid-cols-2 lg:gap-y-16">
                                             <div class="relative pl-16 py-3 bg-white rounded-lg" style="box-sizing:border-box;">
                                                      <dt class="text-base font-semibold leading-7 text-gray-900">
                                                               <div class="absolute left-4 top-4 flex h-10 w-10 items-center justify-center rounded-lg bg-indigo-600">
                                                                        <i class="bi bi-trophy-fill text-white"></i>
                                                               </div>
                                                               Join the Fun Today
                                                      </dt>
                                                      <dd class="mt-2 text-base leading-7 text-gray-600">Don't miss out on the chance to turn your routine data or airtime purchase into an exhilarating win. Head over to ZOEFIT, top up your data or airtime, and automatically enter the raffle competition. It's that easy!</dd>
                                             </div>
                                             <div class="relative pl-16 py-3 bg-white rounded-lg" style="box-sizing:border-box;">
                                                      <dt class="text-base font-semibold leading-7 text-gray-900">
                                                               <div class="absolute left-4 top-4 flex h-10 w-10 items-center justify-center rounded-lg bg-indigo-600">
                                                                        <i class="bi bi-wallet2 text-white"></i>
                                                               </div>
                                                               Airtime Topup
                                                      </dt>
                                                      <dd class="mt-2 text-base leading-7 text-gray-600">In a world that moves at the speed of communication, having a reliable source for airtime top-ups is essential. Enter ZOEFIT – your destination for seamless and instant airtime recharges, ensuring you're always connected with those who matter.</dd>
                                             </div>
                                             <div class="relative pl-16 py-3 bg-white rounded-lg" style="box-sizing:border-box;">
                                                      <dt class="text-base font-semibold leading-7 text-gray-900">
                                                               <div class="absolute left-4 top-4 flex h-10 w-10 items-center justify-center rounded-lg bg-indigo-600">
                                                                        <i class="bi bi-database-fill-check text-white"></i>
                                                               </div>
                                                               Data Bundle
                                                      </dt>
                                                      <dd class="mt-2 text-base leading-7 text-gray-600"> In a world where connectivity is key, ZOEFIT is your gateway to affordable and reliable data bundles. Say goodbye to expensive data plans and hello to uninterrupted connectivity without breaking the bank.</dd>
                                             </div>
                                             <div class="relative pl-16 py-3 bg-white rounded-lg" style="box-sizing:border-box;">
                                                      <dt class="text-base font-semibold leading-7 text-gray-900">
                                                               <div class="absolute left-4 top-4 flex h-10 w-10 items-center justify-center rounded-lg bg-indigo-600">
                                                                        <svg class="h-6 w-6 text-white" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                                                                                 <path stroke-linecap="round" stroke-linejoin="round" d="M7.864 4.243A7.5 7.5 0 0119.5 10.5c0 2.92-.556 5.709-1.568 8.268M5.742 6.364A7.465 7.465 0 004.5 10.5a7.464 7.464 0 01-1.15 3.993m1.989 3.559A11.209 11.209 0 008.25 10.5a3.75 3.75 0 117.5 0c0 .527-.021 1.049-.064 1.565M12 10.5a14.94 14.94 0 01-3.6 9.75m6.633-4.596a18.666 18.666 0 01-2.485 5.33" />
                                                                        </svg>
                                                               </div>
                                                               Advanced security
                                                      </dt>
                                                      <dd class="mt-2 text-base leading-7 text-gray-600">Arcu egestas dolor vel iaculis in ipsum mauris. Tincidunt mattis aliquet hac quis. Id hac maecenas ac donec pharetra eget.</dd>
                                             </div>
                                    </dl>
                           </div>
                  </div>
         </div>

         <section class="relative isolate overflow-hidden bg-white px-6 py-24 sm:py-32 lg:px-8">
                  <div class="absolute inset-0 -z-10 bg-[radial-gradient(45rem_50rem_at_top,theme(colors.indigo.100),white)] opacity-20"></div>
                  <div class="absolute inset-y-0 right-1/2 -z-10 mr-16 w-[200%] origin-bottom-left skew-x-[-30deg] bg-white shadow-xl shadow-indigo-600/10 ring-1 ring-indigo-50 sm:mr-28 lg:mr-0 xl:mr-16 xl:origin-center"></div>
                  <div class="mx-auto max-w-2xl lg:max-w-4xl">
                           <img class="mx-auto h-12" src="https://tailwindui.com/img/logos/workcation-logo-indigo-600.svg" alt="">
                           <figure class="mt-10">
                                    <blockquote class="text-center text-xl  leading-8 text-gray-900 sm:text-2xl sm:leading-9">
                                             <p class="text">“Zoefit has been a game-changer for me in managing my mobile expenses. This online platform has revolutionized the way I buy airtime and data, offering a seamless and convenient experience. With Zoefit, I can effortlessly recharge my phone and purchase data bundles with just a few clicks, saving me time and effort. The user-friendly interface and reliable service have made it my go-to platform for all my mobile needs. Thanks to Zoefit, staying connected has never been easier.”</p>
                                    </blockquote>
                                    <figcaption class="mt-10">
                                             <img class="mx-auto h-10 w-10 rounded-full" src="https://images.unsplash.com/photo-1494790108377-be9c29b29330?ixlib=rb-1.2.1&ixid=eyJhcHBfaWQiOjEyMDd9&auto=format&fit=facearea&facepad=2&w=256&h=256&q=80" alt="">
                                             <div class="mt-4 flex items-center justify-center space-x-3 text-base">
                                                      <div class="font-semibold text-gray-900">Judith Black</div>
                                                      <svg viewBox="0 0 2 2" width="3" height="3" aria-hidden="true" class="fill-gray-900">
                                                               <circle cx="1" cy="1" r="1" />
                                                      </svg>
                                                      <div class="text-gray-600">CEO of Workcation</div>
                                             </div>
                                    </figcaption>
                           </figure>
                  </div>
         </section>


         <div class="relative isolate overflow-hidden  py-24 sm:py-32">


                  <div class="mx-auto max-w-7xl px-6 lg:px-8">
                           <div class="mx-auto">
                                    <h2 class="text-4xl font-bold tracking-tight text-white sm:text-6xl">Work with us</h2>
                                    <p class="mt-6 text-lg leading-8 text-gray-300">Experience the convenience of staying connected with Zoefit! With us, your online journey for airtime and data purchases becomes effortless. Navigate our user-friendly website to quickly and securely buy airtime and data, ensuring you're always in control of your connectivity needs. Join Zoefit for a seamless online experience that puts the power of communication at your fingertips.</p>
                           </div>
                           <div class="mx-auto mt-10 max-w-2xl lg:mx-0 lg:max-w-none">

                                    <dl class="mt-16 grid grid-cols-1 gap-8 sm:mt-20 sm:grid-cols-2 lg:grid-cols-4">
                                             <div class="flex flex-col-reverse">
                                                      <dt class="font-light text-white">Clients</dt>
                                                      <dd class="text-2xl  leading-9 tracking-tight text-white">3.4K</dd>
                                             </div>
                                             <div class="flex flex-col-reverse">
                                                      <dt class=" font-light leading-7 text-white">Revence Per Day</dt>
                                                      <dd class="text-2xl  leading-9 tracking-tight text-white">9.2K</dd>
                                             </div>
                                             <div class="flex flex-col-reverse">
                                                      <dt class=" font-light leading-7 text-white">Hours per week</dt>
                                                      <dd class="text-2xl  leading-9 tracking-tight text-white">22/7 Hours Per Day</dd>
                                             </div>

                                    </dl>
                           </div>
                  </div>
         </div>

         <?php

         include('./includes/footer.php')


         ?>

         



</body>

</html>