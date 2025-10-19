<?php
class UsersModel {
    private $db;

    public function __construct() {
        $this->db = new Database();
    }

    public function getUserById($id) {
        return $this->db->fetch(
            "SELECT id, name, email, role, status, created_at, last_login 
             FROM users WHERE id = ?",
            [$id]
        );
    }

    public function updateUser($id, $data) {
        $allowedFields = ['name', 'email', 'role', 'status'];
        $updates = [];
        $params = [];

        foreach ($data as $field => $value) {
            if (in_array($field, $allowedFields)) {
                $updates[] = "$field = ?";
                $params[] = $value;
            }
        }

        if (empty($updates)) {
            return false;
        }

        $params[] = $id;
        $sql = "UPDATE users SET " . implode(', ', $updates) . " WHERE id = ?";
        
        $this->db->query($sql, $params);
        return $this->db->query->rowCount() > 0;
    }

    public function deleteUser($id) {
        // Don't allow deleting your own account
        $auth = new Auth();
        $currentUser = $auth->getUser();
        if ($currentUser && $currentUser['id'] == $id) {
            throw new Exception("Cannot delete your own account");
        }

        $this->db->query("DELETE FROM users WHERE id = ?", [$id]);
        return $this->db->query->rowCount() > 0;
    }
}
?>