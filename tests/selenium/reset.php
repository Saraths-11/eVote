<?php
$conn = new mysqli('localhost', 'root', '', 'evote');
$conn->query('DELETE FROM elections');
echo "Elections cleared.";
?>