<?php
require_once __DIR__ . '/../shared/bootstrap.php';
require_once __DIR__ . '/retorno_controller.php';

handle_retorno_request($_GET['action'] ?? '');
