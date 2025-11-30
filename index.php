<?php
$uri = $_SERVER['REQUEST_URI'];

if ($uri === '/') {
    echo "Welcome to the Home Page!";
} elseif ($uri === '/about') {
    echo "This is the About Page.";
} else {
    http_response_code(404);
    echo "404 Not Found";
}
?>