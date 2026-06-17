<?php
require_once __DIR__ . '/../shared/bootstrap.php';
require_once __DIR__ . '/conductores_controller.php';

handle_conductores_request($_GET['action'] ?? '');
