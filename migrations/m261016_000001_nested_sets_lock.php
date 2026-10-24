<?php

declare(strict_types=1);

use yii\db\Migration;

/**
 * Creates the per-table transactional writer registry in the connection's default schema.
 */
class m261016_000001_nested_sets_lock extends Migration
{
    public function safeUp(): void
    {
        $options = $this->db->driverName === 'mysql' ? 'ENGINE=InnoDB' : null;

        $this->createTable(
            'nested_sets_lock',
            [
                'resource' => $this->string(64)->notNull()->append('PRIMARY KEY'),
                'token' => $this->smallInteger()->notNull(),
            ],
            $options,
        );
        $this->insert('nested_sets_lock', ['resource' => '_bootstrap', 'token' => 0]);
    }

    public function safeDown(): void
    {
        $this->dropTable('nested_sets_lock');
    }
}
