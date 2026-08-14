<?php
require 'includes/configdb.php';
$res = $conn->query("SELECT classroom_id, COUNT(*) as c FROM users WHERE role='student' GROUP BY classroom_id");
while($row = $res->fetch_assoc()) {
    print_r($row);
}
?>
