<?php
$db = new PDO('sqlite:c:\\dev\\statisty\\database.sqlite');
$stmt = $db->query("SELECT name FROM sqlite_master WHERE type='table'");
while($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    echo $row['name'] . "\n";
}
