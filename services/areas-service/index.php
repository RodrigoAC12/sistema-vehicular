<?php
require_once __DIR__ . '/../shared/bootstrap.php';
require_once __DIR__ . '/areas_controller.php';

handle_areas_request($_GET['action'] ?? '');
