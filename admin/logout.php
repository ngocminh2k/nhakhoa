<?php
// admin/logout.php

require_once __DIR__ . '/../backend/auth/AdminAuth.php';

AdminAuth::logout();
header('Location: /admin/login.php');
exit;
