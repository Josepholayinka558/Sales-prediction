<?php
require_once dirname(__FILE__, 2) . '/app/bootstrap.php';
auth()->logout();
Session::flash('success', 'You have been logged out successfully.');
redirect('/login.php');
