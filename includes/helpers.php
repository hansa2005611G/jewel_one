<?php
/**
 * Helper Functions
 * Shared utilities for all pages
 */

// Database query helper - execute a query and get a single column value
if (!function_exists('queryStat')) {
    function queryStat($db, $sql, $params = []) {
        $s = $db->prepare($sql);
        $s->execute($params);
        return $s->fetchColumn();
    }
}

// Date formatting helper
if (!function_exists('formatDate')) {
    function formatDate($date, $format = 'l, d M Y h:i A') {
        return $date ? date($format, strtotime($date)) : 'N/A';
    }
}
?>
