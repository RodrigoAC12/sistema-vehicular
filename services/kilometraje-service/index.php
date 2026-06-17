<?php
require_once __DIR__ . '/../shared/bootstrap.php';
require_once __DIR__ . '/kilometraje_controller.php';

handle_kilometraje_request($_GET['action'] ?? '');
