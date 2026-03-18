<?php
$conn = new mysqli('localhost', 'root', '', 'evote_db');
$conn->query("DELETE FROM votes");
echo "Votes deleted.\n";
