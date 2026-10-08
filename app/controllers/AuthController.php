<?php
defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');

/**
 * Controller: AuthController
 * 
 * Handles user registration and login with JWT authentication
 */
class AuthController extends Controller {
    public function __construct()
    {
        parent::__construct();
        $this->_lava = lava_instance();
        $this->_lava->call->library('api');
    }

    /**
     * Register a new user
     * POST /api/register
     */
    public function register()
    {
        $api = $this->_lava->api;
        $api->require_method('POST');
        $api->rate_limit();

        $body = $api->body();

        // Validate required fields
        $username = trim((string) ($body['username'] ?? ''));
        $email = trim((string) ($body['email'] ?? ''));
        $password = (string) ($body['password'] ?? '');

        if ($username === '' || $password === '' || $email === '') {
            $api->respond_error('Username, password, and email are required', 400);
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $api->respond_error('A valid email address is required', 422);
        }

        if (strlen($username) > 100 || strlen($password) < 8) {
            $api->respond_error('Username must be at most 100 characters and password at least 8 characters', 422);
        }

        // Check if user already exists
        $stmt = $this->_lava->db->raw(
            "SELECT id FROM users_table WHERE username = ? OR email = ? LIMIT 1",
            [$username, $email]
        );
        
        if ($stmt->fetch()) {
            $api->respond_error('Username or email already exists', 409);
        }

        // Hash password
        $password_hash = password_hash($password, PASSWORD_DEFAULT);

        // Insert user
        $this->_lava->db->raw(
            "INSERT INTO users_table (username, password, email, role, created_at) VALUES (?, ?, ?, 'user', NOW())",
            [$username, $password_hash, $email]
        );

        $user_id = $this->_lava->db->insert_id();

        // Issue tokens
        $tokens = $api->issue_tokens([
            'id' => $user_id,
            'role' => 'user',
            'scopes' => ['read', 'write']
        ]);

        $api->respond([
            'message' => 'User registered successfully',
            'user_id' => $user_id,
            'tokens' => $tokens
        ], 201);
    }

    /**
     * Login user
     * POST /api/login
     */
    public function login()
    {
        $api = $this->_lava->api;
        $api->require_method('POST');
        $api->rate_limit();

        $body = $api->body();

        // Validate required fields
        $username = trim((string) ($body['username'] ?? ''));
        $password = (string) ($body['password'] ?? '');

        if ($username === '' || $password === '') {
            $api->respond_error('Username and password are required', 400);
        }

        // Find user
        $stmt = $this->_lava->db->raw(
            "SELECT id, username, password, role FROM users_table WHERE username = ? LIMIT 1",
            [$username]
        );
        
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user || !password_verify($body['password'], $user['password'])) {
            $api->respond_error('Invalid credentials', 401);
        }

        // Issue tokens
        $tokens = $api->issue_tokens([
            'id' => $user['id'],
            'role' => $user['role'],
            'scopes' => ['read', 'write']
        ]);

        $api->respond([
            'message' => 'Login successful',
            'user' => [
                'id' => $user['id'],
                'username' => $user['username'],
                'role' => $user['role']
            ],
            'tokens' => $tokens
        ]);
    }

    /**
     * Refresh access token
     * POST /api/refresh
     */
    public function refresh()
    {
        $api = $this->_lava->api;
        $api->require_method('POST');
        $api->rate_limit();

        $body = $api->body();

        if (empty($body['refresh_token'])) {
            $api->respond_error('Refresh token is required', 400);
        }

        $api->refresh_access_token($body['refresh_token']);
    }
}
