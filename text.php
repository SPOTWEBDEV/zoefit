<?php

$url = "";
if (isset($_SESSION['url'])) {
        $url = $_SESSION['url'];
} else {
        $url = "http://localhost/zoefit/";
}
$location = 'location:' . $url;
header($location);

?>