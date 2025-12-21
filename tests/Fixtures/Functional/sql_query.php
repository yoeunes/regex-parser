<?php
// SQL query - should NOT be detected as regex
$sql = "SELECT * FROM table WHERE id = 1";
$query = 'INSERT INTO users (name, email) VALUES ("John", "john@example.com")';
$update = "UPDATE products SET price = 19.99 WHERE category = 'electronics'";
