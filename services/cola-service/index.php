<?php
require_once __DIR__ . '/../shared/bootstrap.php';
require_once __DIR__ . '/cola_controller.php';

handle_cola_request($_GET['action'] ?? '');
