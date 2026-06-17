<?php
require_once __DIR__ . '/../shared/bootstrap.php';
require_once __DIR__ . '/estadisticas_controller.php';

handle_estadisticas_request($_GET['action'] ?? '');
