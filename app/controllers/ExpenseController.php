<?php
defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');

/**
 * Controller: ExpenseController
 * 
 * Handles CRUD operations for expenses with JWT authentication
 */
class ExpenseController extends Controller {
    public function __construct()
    {
        parent::__construct();
        $this->_lava = lava_instance();
        $this->_lava->call->library('api');
    }

    /**
     * Get all expenses for authenticated user
     * GET /api/expenses
     */
    public function index()
    {
        $api = $this->_lava->api;
        $api->require_method('GET');
        
        // Require JWT authentication
        $payload = $api->require_jwt();
        $user_id = $payload['sub'];

        // Get query parameters for filtering
        $params = $api->get_query_params();
        $category = $params['category'] ?? null;
        $start_date = $params['start_date'] ?? null;
        $end_date = $params['end_date'] ?? null;

        // Build query
        $sql = "SELECT * FROM expense_table WHERE user_id = ?";
        $params_arr = [$user_id];

        if ($category) {
            $sql .= " AND category = ?";
            $params_arr[] = $category;
        }

        if ($start_date) {
            $sql .= " AND date >= ?";
            $params_arr[] = $start_date;
        }

        if ($end_date) {
            $sql .= " AND date <= ?";
            $params_arr[] = $end_date;
        }

        $sql .= " ORDER BY date DESC";

        $stmt = $this->_lava->db->raw($sql, $params_arr);
        $expenses = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $api->respond([
            'expenses' => $expenses,
            'count' => count($expenses)
        ]);
    }

    /**
     * Create a new expense
     * POST /api/expenses
     */
    public function create()
    {
        $api = $this->_lava->api;
        $api->require_method('POST');
        
        // Require JWT authentication
        $payload = $api->require_jwt();
        $user_id = $payload['sub'];

        $body = $api->body();

        // Validate required fields
        $description = trim((string) ($body['description'] ?? ''));

        if ($description === '' || !array_key_exists('amount', $body)) {
            $api->respond_error('Description and amount are required', 400);
        }

        // Validate amount
        if (!is_numeric($body['amount']) || (float) $body['amount'] <= 0) {
            $api->respond_error('Amount must be a positive number', 400);
        }

        // Set default values
        $category = $body['category'] ?? 'general';
        $date = $body['date'] ?? date('Y-m-d H:i:s');

        // Insert expense
        $this->_lava->db->raw(
            "INSERT INTO expense_table (user_id, description, amount, category, date, created_at) 
             VALUES (?, ?, ?, ?, ?, NOW())",
            [$user_id, $description, $body['amount'], $category, $date]
        );

        $expense_id = $this->_lava->db->insert_id();

        // Fetch the created expense
        $stmt = $this->_lava->db->raw(
            "SELECT * FROM expense_table WHERE id = ?",
            [$expense_id]
        );
        $expense = $stmt->fetch(PDO::FETCH_ASSOC);

        $api->respond([
            'message' => 'Expense created successfully',
            'expense' => $expense
        ], 201);
    }

    /**
     * Update an expense
     * PUT /api/expenses/{id}
     */
    public function update($id)
    {
        $api = $this->_lava->api;
        $api->require_method('PUT');
        
        // Require JWT authentication
        $payload = $api->require_jwt();
        $user_id = $payload['sub'];

        $body = $api->body();

        // Check if expense exists and belongs to user
        $stmt = $this->_lava->db->raw(
            "SELECT * FROM expense_table WHERE id = ? AND user_id = ? LIMIT 1",
            [$id, $user_id]
        );
        $expense = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$expense) {
            $api->respond_error('Expense not found or unauthorized', 404);
        }

        // Build update query dynamically
        $update_fields = [];
        $params = [];

        if (isset($body['description'])) {
            $description = trim((string) $body['description']);
            if ($description === '') {
                $api->respond_error('Description cannot be empty', 422);
            }
            $update_fields[] = "description = ?";
            $params[] = $description;
        }

        if (isset($body['amount'])) {
            if (!is_numeric($body['amount']) || (float) $body['amount'] <= 0) {
                $api->respond_error('Amount must be a positive number', 400);
            }
            $update_fields[] = "amount = ?";
            $params[] = $body['amount'];
        }

        if (isset($body['category'])) {
            $update_fields[] = "category = ?";
            $params[] = $body['category'];
        }

        if (isset($body['date'])) {
            $update_fields[] = "date = ?";
            $params[] = $body['date'];
        }

        if (empty($update_fields)) {
            $api->respond_error('No fields to update', 400);
        }

        $params[] = $id;
        $params[] = $user_id;

        $sql = "UPDATE expense_table SET " . implode(', ', $update_fields) . " WHERE id = ? AND user_id = ?";
        $this->_lava->db->raw($sql, $params);

        // Fetch updated expense
        $stmt = $this->_lava->db->raw(
            "SELECT * FROM expense_table WHERE id = ?",
            [$id]
        );
        $updated_expense = $stmt->fetch(PDO::FETCH_ASSOC);

        $api->respond([
            'message' => 'Expense updated successfully',
            'expense' => $updated_expense
        ]);
    }

    /**
     * Delete an expense
     * DELETE /api/expenses/{id}
     */
    public function delete($id)
    {
        $api = $this->_lava->api;
        $api->require_method('DELETE');
        
        // Require JWT authentication
        $payload = $api->require_jwt();
        $user_id = $payload['sub'];

        // Check if expense exists and belongs to user
        $stmt = $this->_lava->db->raw(
            "SELECT * FROM expense_table WHERE id = ? AND user_id = ? LIMIT 1",
            [$id, $user_id]
        );
        $expense = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$expense) {
            $api->respond_error('Expense not found or unauthorized', 404);
        }

        // Delete expense
        $this->_lava->db->raw(
            "DELETE FROM expense_table WHERE id = ? AND user_id = ?",
            [$id, $user_id]
        );

        $api->respond([
            'message' => 'Expense deleted successfully'
        ]);
    }

    /**
     * Get expense summary by category
     * GET /api/expenses/summary
     */
    public function summary()
    {
        $api = $this->_lava->api;
        $api->require_method('GET');
        
        // Require JWT authentication
        $payload = $api->require_jwt();
        $user_id = $payload['sub'];

        // Get query parameters
        $params = $api->get_query_params();
        $start_date = $params['start_date'] ?? null;
        $end_date = $params['end_date'] ?? null;

        // Build query
        $sql = "SELECT category, SUM(amount) as total, COUNT(*) as count 
                FROM expense_table WHERE user_id = ?";
        $query_params = [$user_id];

        if ($start_date) {
            $sql .= " AND date >= ?";
            $query_params[] = $start_date;
        }

        if ($end_date) {
            $sql .= " AND date <= ?";
            $query_params[] = $end_date;
        }

        $sql .= " GROUP BY category ORDER BY total DESC";

        $stmt = $this->_lava->db->raw($sql, $query_params);
        $summary = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Calculate total
        $total_sql = "SELECT SUM(amount) as total FROM expense_table WHERE user_id = ?";
        $total_params = [$user_id];

        if ($start_date) {
            $total_sql .= " AND date >= ?";
            $total_params[] = $start_date;
        }

        if ($end_date) {
            $total_sql .= " AND date <= ?";
            $total_params[] = $end_date;
        }

        $total_stmt = $this->_lava->db->raw($total_sql, $total_params);
        $total_result = $total_stmt->fetch(PDO::FETCH_ASSOC);

        $api->respond([
            'summary' => $summary,
            'total_amount' => $total_result['total'] ?? 0
        ]);
    }
}
