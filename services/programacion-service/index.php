<?php
require_once __DIR__ . '/../shared/bootstrap.php';
require_once __DIR__ . '/programacion_controller.php';

handle_programacion_request($_GET['action'] ?? '');
