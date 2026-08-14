<?php
require 'includes/configdb.php';
$res = $conn->query("SELECT id, class_name FROM classrooms ORDER BY id DESC LIMIT 5");
while($row = $res->fetch_assoc()) {
    print_r($row);
}
?>
