<?php
include('../../../configs/database.php');
include('../../../configs/clients/authorization.php');
// $person = $_SESSION['userLogin'];
if (isset($_GET['delete'])) {
    $id = $_GET['delete'];

    $delete = mysqli_query($conn, "DELETE FROM `clients` WHERE `id`='$id'");

    if ($delete) {
        header('location: ./index.php');
    } else {
        echo 'cant delete';
    }
}

?>


<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <script src="https://cdn.tailwindcss.com"></script>
    <title>Delete Account</title>
    <style>
        /* #popup-modal{
            text-align: center;
            display: flex;
            justify-content: center;
            position: fixed;
            top: 200%;
        } */
    </style>
</head>

<body>

    <?php include('../../../includes/navbar.php') ?>

    <a href="./index.php?delete=<?php echo $id ?>"><button onclick="return MyFunction()">delete</button></a>




    <div id="popup-modal" tabindex="-1" class=" overflow-y-auto overflow-x-hidden fixed top-0 right-0 left-0 z-50 justify-center items-center w-full md:inset-0 h-[calc(100%-1rem)] max-h-full">
        <div class="relative p-4 w-full max-w-md max-h-full">
            <div class="relative bg-white rounded-lg shadow dark:bg-gray-700">
                <button type="button" class="absolute top-3 end-2.5 text-gray-400 bg-transparent hover:bg-gray-200 hover:text-gray-900 rounded-lg text-sm w-8 h-8 ms-auto inline-flex justify-center items-center dark:hover:bg-gray-600 dark:hover:text-white" data-modal-hide="popup-modal">
                    <svg id="popup_cancel_btn1" class="w-3 h-3" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 14 14">
                        <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m1 1 6 6m0 0 6 6M7 7l6-6M7 7l-6 6" />
                    </svg>
                    <span class="sr-only">Close modal</span>
                </button>
                <div class="p-4 md:p-5 text-center">
                    <svg class="mx-auto mb-4 text-gray-400 w-12 h-12 dark:text-gray-200" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 20 20">
                        <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 11V6m0 8h.01M19 10a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                    </svg>
                    <h3 class="mb-5 text-lg font-normal text-gray-500 dark:text-gray-400">Are you sure you want to delete this product?</h3>
                    <button id="popup_verify_btn" data-modal-hide="popup-modal" type="button" class="text-white bg-red-600 hover:bg-red-800 focus:ring-4 focus:outline-none focus:ring-red-300 dark:focus:ring-red-800 font-medium rounded-lg text-sm inline-flex items-center px-5 py-2.5 text-center me-2">
                        Yes, I'm sure
                    </button>
                    <button id="popup_cancel_btn2" data-modal-hide="popup-modal" type="button" class="text-gray-500 bg-white hover:bg-gray-100 focus:ring-4 focus:outline-none focus:ring-gray-200 rounded-lg border border-gray-200 text-sm font-medium px-5 py-2.5 hover:text-gray-900 focus:z-10 dark:bg-gray-700 dark:text-gray-300 dark:border-gray-500 dark:hover:text-white dark:hover:bg-gray-600 dark:focus:ring-gray-600">No, cancel</button>
                </div>
            </div>
        </div>
    </div>




    <!-- Modal toggle -->

    <!-- Main modal -->
    <div id="static-modal" data-modal-backdrop="static" tabindex="-1" aria-hidden="true" class="hidden overflow-y-auto overflow-x-hidden fixed top-0 right-0 left-0 z-50 justify-center items-center w-full md:inset-0 h-[calc(100%-1rem)] max-h-full">
        <div class="relative p-4 w-full max-w-2xl max-h-full">
            <!-- Modal content -->
            <div class="relative bg-white rounded-lg shadow dark:bg-gray-700">
                <!-- Modal header -->
                <div class="flex items-center justify-between p-4 md:p-5 border-b rounded-t dark:border-gray-600">
                    <h3 class="text-xl font-semibold text-gray-900 dark:text-white">
                        Static modal
                    </h3>
                    <button type="button" class="text-gray-400 bg-transparent hover:bg-gray-200 hover:text-gray-900 rounded-lg text-sm w-8 h-8 ms-auto inline-flex justify-center items-center dark:hover:bg-gray-600 dark:hover:text-white" data-modal-hide="static-modal">
                        <svg class="w-3 h-3" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 14 14">
                            <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m1 1 6 6m0 0 6 6M7 7l6-6M7 7l-6 6" />
                        </svg>
                        <span class="sr-only">Close modal</span>
                    </button>
                </div>
                <!-- Modal body -->
                <p><?php echo $name ?></p>
                <input type="text" id="confirm" value=" <?php echo $name ?>" style="display: none;">
                <input type="text" id="cheack">

                <button></button>
                <!-- Modal footer -->
                <div class="flex items-center p-4 md:p-5 border-t border-gray-200 rounded-b dark:border-gray-600">
                    <a href="./index.php?delete=<?php echo $id ?>">
                        <button data-modal-hide="static-modal" type="button" class="text-white bg-blue-700 hover:bg-blue-800 focus:ring-4 focus:outline-none focus:ring-blue-300 font-medium rounded-lg text-sm px-5 py-2.5 text-center dark:bg-blue-600 dark:hover:bg-blue-700 dark:focus:ring-blue-800" onclick="  return cheack2()">confirm</button>
                    </a>

                </div>
            </div>
        </div>
    </div>

    <?php include('../../../includes/footer.php') ?>


    <script>
        let popup_modal = document.getElementById('popup-modal');
        let static_modal = document.getElementById('static-modal');
        let popup_verify_btn = document.getElementById('popup_verify_btn');
        let popup_cancel_btn1 = document.getElementById('popup_cancel_btn1');
        let popup_cancel_btn2 = document.getElementById('popup_cancel_btn2');
        popup_modal.style.display = "none";

        function MyFunction() {
            popup_modal.style.display = "block";
            return false;
        }

        popup_cancel_btn1.onclick = () => {
            popup_modal.style.display = "none";
        }

        popup_cancel_btn2.onclick = () => {
            popup_modal.style.display = "none";
        }

        popup_verify_btn.onclick = () => {
            static_modal.style.display = "block"
            return true;
        }


        function cheack2() {

            let cheack = document.getElementById('cheack').value;
            let confirm = document.getElementById('confirm').value;

            if (cheack === confirm) {

                return true

            } else {

                return false;
            }
        }
    </script>
</body>

</html>