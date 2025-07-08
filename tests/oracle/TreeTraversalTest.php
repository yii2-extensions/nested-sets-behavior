<?php

declare(strict_types=1);

namespace yii2\extensions\nestedsets\tests\oracle;

use PHPUnit\Framework\Attributes\Group;
use yii2\extensions\nestedsets\tests\base\AbstractTreeTraversal;
use yii2\extensions\nestedsets\tests\support\DatabaseConnection;

#[Group('oci')]
final class TreeTraversalTest extends AbstractTreeTraversal
{
    protected function setUp(): void
    {
        $this->connection = DatabaseConnection::ORACLE->connection();

        parent::setUp();
    }
}
