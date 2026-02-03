<?php
/**
 * TESA Syllabus Monitor
 * Cerrar Sesión
 * 
 * @package TESASyllabusMonitor
 * @author Sistema TESA
 * @version 1.0
 */

require_once __DIR__ . '/config/config.php';
require_once CONFIG_PATH . '/database.php';
require_once INCLUDES_PATH . '/auth.php';

// Cerrar sesión
Auth::logout();

// Redirigir al login
header('Location: ' . BASE_URL . '/login.php');
exit;