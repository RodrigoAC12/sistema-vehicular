<?php
require_once __DIR__ . '/../shared/bootstrap.php';
require_once __DIR__ . '/auth_controller.php';

handle_auth_request($_GET['action'] ?? '');
