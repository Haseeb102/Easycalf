<artifact identifier="tasks-model" type="application/vnd.ant.code" language="php" title="Tasks Model - Database Operations">
<?php
/**
 * Tasks Model
 * Handles all database operations for tasks/events
 * Filters out deleted/sold/deceased calves from main views
 */
class TasksModel {
    private $db;public function __construct() {
    $this->db = new Database();
}/**
 * Get all task types with counts (excluding deleted/sold/deceased calves)
 */
public function getTasksSummary() {
    return $this->db->fetchAll("
        SELECT 
            e.name as event_name,
            e.type as event_type,
            COUNT(*) as total_count,
            SUM(CASE WHEN ce.status = 'pending' AND ce.due_date <= CURDATE() THEN 1 ELSE 0 END) as due_count,
            SUM(CASE WHEN ce.status = 'pending' AND ce.due_date > CURDATE() THEN 1 ELSE 0 END) as upcoming_count,
            MIN(ce.due_date) as earliest_due
        FROM calf_events ce
        JOIN events e ON ce.event_id = e.id
        JOIN calves c ON ce.calf_id = c.id
        WHERE ce.status = 'pending'
        AND c.status = 'active'
        GROUP BY e.id, e.name, e.type
        ORDER BY earliest_due ASC, e.name ASC
    ");
}/**
 * Get all due tasks (excluding deleted/sold/deceased calves)
 */
public function getAllDueTasks() {
    return $this->db->fetchAll("
        SELECT 
            ce.id as task_id,
            ce.due_date,
            c.id as calf_id,
            c.calf_id as calf_identifier,
            c.health_status,
            c.status as calf_status,
            e.name as event_name,
            e.type as event_type,
            b.name as batch_name,
            DATEDIFF(NOW(), c.birth_date) as age_days
        FROM calf_events ce
        JOIN events e ON ce.event_id = e.id
        JOIN calves c ON ce.calf_id = c.id
        LEFT JOIN batches b ON c.batch_id = b.id
        WHERE ce.status = 'pending' 
        AND ce.due_date <= CURDATE()
        AND c.status = 'active'
        ORDER BY ce.due_date ASC, c.calf_id ASC
    ");
}/**
 * Get all upcoming tasks (excluding deleted/sold/deceased calves)
 */
public function getAllUpcomingTasks() {
    return $this->db->fetchAll("
        SELECT 
            ce.id as task_id,
            ce.due_date,
            c.id as calf_id,
            c.calf_id as calf_identifier,
            c.health_status,
            c.status as calf_status,
            e.name as event_name,
            e.type as event_type,
            b.name as batch_name,
            DATEDIFF(NOW(), c.birth_date) as age_days
        FROM calf_events ce
        JOIN events e ON ce.event_id = e.id
        JOIN calves c ON ce.calf_id = c.id
        LEFT JOIN batches b ON c.batch_id = b.id
        WHERE ce.status = 'pending' 
        AND ce.due_date > CURDATE()
        AND c.status = 'active'
        ORDER BY ce.due_date ASC, c.calf_id ASC
    ");
}/**
 * Get task details for a specific event (excluding deleted/sold/deceased)
 */
public function getTaskDetailsByEvent($eventName, $type = 'due') {
    $query = "
        SELECT 
            ce.id as task_id,
            c.id as calf_id,
            c.calf_id as calf_identifier,
            c.birth_date,
            c.status as calf_status,
            DATEDIFF(NOW(), c.birth_date) as age_days,
            c.health_status,
            b.name as batch_name,
            e.name as event_name,
            e.type as event_type,
            ce.due_date,
            ce.status,
            CASE 
                WHEN ce.due_date < CURDATE() THEN 'overdue'
                WHEN ce.due_date = CURDATE() THEN 'due_today'
                ELSE 'upcoming'
            END as due_status
        FROM calf_events ce
        JOIN events e ON ce.event_id = e.id
        JOIN calves c ON ce.calf_id = c.id
        LEFT JOIN batches b ON c.batch_id = b.id
        WHERE e.name = ? 
        AND ce.status = 'pending'
        AND c.status = 'active'
    ";    if ($type === 'due') {
        $query .= " AND ce.due_date <= CURDATE()";
    } else {
        $query .= " AND ce.due_date > CURDATE()";
    }    $query .= " ORDER BY ce.due_date ASC, c.calf_id ASC";    return $this->db->fetchAll($query, [$eventName]);
}/**
 * Get tasks for calendar view (excluding deleted/sold/deceased)
 */
public function getTasksForMonth($startDate, $endDate) {
    return $this->db->fetchAll("
        SELECT 
            ce.due_date,
            COUNT(*) as task_count,
            SUM(CASE WHEN ce.due_date < CURDATE() THEN 1 ELSE 0 END) as overdue_count,
            SUM(CASE WHEN ce.due_date = CURDATE() THEN 1 ELSE 0 END) as today_count
        FROM calf_events ce
        JOIN calves c ON ce.calf_id = c.id
        WHERE ce.status = 'pending'
        AND c.status = 'active'
        AND ce.due_date BETWEEN ? AND ?
        GROUP BY ce.due_date
        ORDER BY ce.due_date ASC
    ", [$startDate, $endDate]);
}/**
 * Get count of due tasks (for dashboard - excluding deleted/sold/deceased)
 */
public function getDueTaskCount() {
    $result = $this->db->fetch("
        SELECT COUNT(*) as count 
        FROM calf_events ce
        JOIN calves c ON ce.calf_id = c.id
        WHERE ce.status = 'pending' 
        AND ce.due_date <= CURDATE()
        AND c.status = 'active'
    ");
    return $result['count'] ?? 0;
}/**
 * Complete a single task
 */
public function completeTask($taskId, $userId, $notes = null) {
    return $this->db->query("
        UPDATE calf_events 
        SET status = 'completed', 
            completed_date = NOW(),
            completed_by = ?,
            completed_notes = ?
        WHERE id = ?
    ", [$userId, $notes, $taskId]);
}/**
 * Complete all due tasks
 */
public function completeAllDueTasks($userId) {
    return $this->db->query("
        UPDATE calf_events ce
        JOIN calves c ON ce.calf_id = c.id
        SET ce.status = 'completed', 
            ce.completed_date = NOW(),
            ce.completed_by = ?
        WHERE ce.status = 'pending' 
        AND ce.due_date <= CURDATE()
        AND c.status = 'active'
    ", [$userId]);
}/**
 * Get tasks for a specific calf (INCLUDES sold/deceased for passport history)
 */
public function getTasksForCalf($calfId) {
    return $this->db->fetchAll("
        SELECT 
            ce.id as task_id,
            ce.due_date,
            ce.status,
            ce.completed_date,
            ce.completed_notes,
            e.name as event_name,
            e.type as event_type,
            u.name as completed_by_name
        FROM calf_events ce
        JOIN events e ON ce.event_id = e.id
        LEFT JOIN users u ON ce.completed_by = u.id
        WHERE ce.calf_id = ?
        ORDER BY ce.due_date DESC, ce.created_at DESC
    ", [$calfId]);
}
}
?>
</artifact>