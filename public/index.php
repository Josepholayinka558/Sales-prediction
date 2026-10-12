<?php
require_once dirname(__FILE__, 2) . '/app/bootstrap.php';
if (auth()->check()) redirect('/dashboard.php');
redirect('/login.php');
