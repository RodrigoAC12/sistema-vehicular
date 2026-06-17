<?php
require_once __DIR__ . '/../shared/bootstrap.php';
require_once __DIR__ . '/solicitudes_controller.php';

handle_solicitudes_request($_GET['action'] ?? '');
