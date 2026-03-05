<?php
$conn = mysqli_connect("localhost", "root", "jouw_wachtwoord", "sportflow");
if (!$conn) {
    die("Verbinding mislukt: " . mysqli_connect_error());
}
?>