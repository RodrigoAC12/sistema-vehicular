<?php
require_once __DIR__ . '/../shared/bootstrap.php';
require_once __DIR__ . '/vehiculos_controller.php';

handle_vehiculos_request($_GET['action'] ?? '');
