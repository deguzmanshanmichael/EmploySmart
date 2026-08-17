<?php
$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['REQUEST_URI'] = '/EmploySmart/server/auth/me';
$_SERVER['HTTP_AUTHORIZATION'] = 'Bearer invalid';
require 'server/index.php';
