<?php
/**
 * Tasks Helper Functions
 * Utility functions for task management
 */

/**
 * Get status badge color based on calf status
 */
function getCalfStatusColor($status) {
    $colors = array(
        'active' => '#28a745',
        'sold' => '#17a2b8',
        'deceased' => '#6c757d',
        'deleted' => '#dc3545'
    );
    
    if (isset($colors[$status])) {
        return $colors[$status];
    }
    return '#6c757d';
}

/**
 * Get status badge HTML
 */
function renderCalfStatusBadge($status) {
    $colors = array(
        'active' => array('bg' => '#d4edda', 'text' => '#155724', 'icon' => '✓'),
        'sold' => array('bg' => '#d1ecf1', 'text' => '#0c5460', 'icon' => '💰'),
        'deceased' => array('bg' => '#e2e3e5', 'text' => '#383d41', 'icon' => '✝'),
        'deleted' => array('bg' => '#f8d7da', 'text' => '#721c24', 'icon' => '🗑')
    );
    
    $style = isset($colors[$status]) ? $colors[$status] : $colors['active'];
    $label = ucfirst($status);

    return sprintf(
        '<span style="background: %s; color: %s; padding: 0.25rem 0.75rem; border-radius: 12px; font-size: 0.8rem; font-weight: 600; display: inline-flex; align-items: center; gap: 0.25rem;">%s %s</span>',
        $style['bg'],
        $style['text'],
        $style['icon'],
        $label
    );
}

/**
 * Format due status
 */
function getDueStatusLabel($dueDate, $status) {
    if ($status !== 'pending') {
        return ucfirst($status);
    }
    
    $today = date('Y-m-d');
    $due = date('Y-m-d', strtotime($dueDate));

    if ($due < $today) {
        $daysOverdue = round((strtotime($today) - strtotime($due)) / 86400);
        return "Overdue (" . $daysOverdue . " days)";
    } elseif ($due == $today) {
        return "Due Today";
    } else {
        $daysUntil = round((strtotime($due) - strtotime($today)) / 86400);
        return "In " . $daysUntil . " days";
    }
}

/**
 * Get task priority color
 */
function getTaskPriorityColor($dueDate) {
    $today = date('Y-m-d');
    $due = date('Y-m-d', strtotime($dueDate));

    if ($due < $today) {
        return '#dc3545'; // Red - Overdue
    } elseif ($due == $today) {
        return '#ffc107'; // Yellow - Due today
    } else {
        return '#28a745'; // Green - Upcoming
    }
}

/**
 * Format task count badge
 */
function renderTaskCountBadge($count, $type = 'due') {
    $colors = array(
        'due' => array('bg' => '#f8d7da', 'text' => '#721c24'),
        'upcoming' => array('bg' => '#d1ecf1', 'text' => '#0c5460'),
        'completed' => array('bg' => '#d4edda', 'text' => '#155724')
    );
    
    $style = isset($colors[$type]) ? $colors[$type] : $colors['due'];

    return sprintf(
        '<span style="background: %s; color: %s; padding: 0.5rem 1rem; border-radius: 20px; font-weight: 600; font-size: 0.9rem;">%d %s</span>',
        $style['bg'],
        $style['text'],
        $count,
        ucfirst($type)
    );
}
?>