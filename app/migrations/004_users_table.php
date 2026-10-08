<?php

class Users_table {

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
            'username' => [
                'type'       => 'VARCHAR',
                'constraint' => 100,
                'null'       => FALSE
            ],
            'password' => [
                'type'       => 'VARCHAR',
                'constraint' => 255,
                'null'       => FALSE
            ],
            'email' => [
                'type'       => 'VARCHAR',
                'constraint' => 255,
                'null'       => FALSE
            ],
            'created_at' => [
                'type'    => 'DATETIME',
                'null'    => FALSE,
                'default' => 'CURRENT_TIMESTAMP'
            ],
            'role' => [
                'type'       => 'VARCHAR',
                'constraint' => 50,
                'null'       => FALSE,
                'default'    => 'user'
            ]
        ]);

        $this->_lava->dbforge->add_key('id', TRUE);
        $this->_lava->dbforge->add_key('username', unique: TRUE, name: 'users_table_username_unique');
        $this->_lava->dbforge->add_key('email', unique: TRUE, name: 'users_table_email_unique');
        $this->_lava->dbforge->create_table('users_table');
    }

    public function down()
    {
        // Write your "DOWN" migration here
        $this->_lava->dbforge->drop_table('users_table');
    }
}
