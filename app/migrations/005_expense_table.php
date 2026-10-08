<?php

class Expense_table {

    private $_lava;
    protected $dbforge;

    public function __construct()
    {
        $this->_lava = lava_instance();
        $this->_lava->call->dbforge();
    }

    public function up()
    {
        // Write your "UP" migration here
        $this->_lava->dbforge->add_field([
            'id' => [
                'type'           => 'INT',
                'constraint'     => 11,
                'unsigned'       => TRUE,
                'auto_increment' => TRUE
            ],
            'user_id' => [
                'type' => 'INT',
                'constraint' => 11,
                'unsigned' => TRUE
            ],
            'description' => [
                'type' => 'VARCHAR',
                'constraint' => 255,
                'null'       => FALSE
            ],
            'amount' => [
                'type'       => 'DECIMAL',
                'constraint' => '10,2',
                'null'       => FALSE
            ],
            'category' => [
                'type'       => 'VARCHAR',
                'constraint' => 50,
                'null'       => FALSE,
                'default'    => 'general'
            ],
            'date' => [
                'type' => 'DATETIME',
                'null' => FALSE
            ],
            'created_at' => [
                'type'    => 'DATETIME',
                'null'    => FALSE,
                'default' => 'CURRENT_TIMESTAMP'
            ]
        ]);

        $this->_lava->dbforge->add_key('id', TRUE);
        $this->_lava->dbforge->add_key('user_id', name: 'expense_table_user_id_idx');
        $this->_lava->dbforge->add_foreign_key('user_id', 'users_table', 'id', 'CASCADE', 'CASCADE');
        $this->_lava->dbforge->create_table('expense_table');
    }

    public function down()
    {
        // Write your "DOWN" migration here
        $this->_lava->dbforge->drop_table('expense_table');
    }
}
