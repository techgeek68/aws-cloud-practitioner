<?php
// health.php, Health check endpoint for the ALB target group
header('Content-Type: text/plain');
http_response_code(200);
echo "OK\n";
