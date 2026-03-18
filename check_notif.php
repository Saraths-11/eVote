<?php
file_put_contents('db_dump.txt', print_r(array_map(fn($row) => $row[0], (new mysqli('localhost', 'root', '', 'evote_db'))->query("SHOW TABLES")->fetch_all()), true));
?>
