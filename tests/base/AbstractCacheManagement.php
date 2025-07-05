<?php

declare(strict_types=1);

namespace yii2\extensions\nestedsets\tests\base;

use PHPForge\Support\Assert;
use yii\base\Behavior;
use yii\db\ActiveRecord;
use yii2\extensions\nestedsets\NestedSetsBehavior;
use yii2\extensions\nestedsets\tests\support\model\{ExtendableMultipleTree, MultipleTree, Tree};
use yii2\extensions\nestedsets\tests\support\stub\ExtendableNestedSetsBehavior;
use yii2\extensions\nestedsets\tests\TestCase;

abstract class AbstractCacheManagement extends TestCase
{
    public function testAfterInsertCacheInvalidationIntegration(): void
    {
        $this->createDatabase();

        $root = new MultipleTree(['name' => 'Original Root']);

        $root->makeRoot();

        $child = new MultipleTree(['name' => 'Child Node']);

        $child->appendTo($root);

        $behavior = $child->getBehavior('nestedSetsBehavior');

        self::assertNotNull(
            $behavior,
            'Behavior should be attached to the child node.',
        );
        self::assertEquals(
            1,
            $child->getAttribute('depth'),
            "Child should start at depth '1'.",
        );
        self::assertEquals(
            2,
            $child->getAttribute('lft'),
            "Child should start with 'lft=2'.",
        );
        self::assertEquals(
            3,
            $child->getAttribute('rgt'),
            "Child should start with 'rgt=3'.",
        );

        $this->populateAndVerifyCache($behavior);

        $child->makeRoot();

        $this->verifyCacheInvalidation($behavior);

        self::assertEquals(
            0,
            $child->getAttribute('depth'),
            "Child should be at depth '0' after becoming root.",
        );
        self::assertEquals(
            1,
            $child->getAttribute('lft'),
            "Child should have 'lft=1' after becoming root.",
        );
        self::assertEquals(
            2,
            $child->getAttribute('rgt'),
            "Child should have 'rgt=2' after becoming root.",
        );
        self::assertEquals(
            0,
            Assert::invokeMethod($behavior, 'getDepthValue'),
            "New cached depth should be '0'.",
        );
        self::assertEquals(
            1,
            Assert::invokeMethod($behavior, 'getLeftValue'),
            "New cached left should be '1'.",
        );
        self::assertEquals(
            2,
            Assert::invokeMethod($behavior, 'getRightValue'),
            "New cached right should be '2'.",
        );
    }

    public function testAfterInsertCallsInvalidateCache(): void
    {
        $this->createDatabase();

        $node = new ExtendableMultipleTree(['name' => 'Root Node']);

        $behavior = $node->getBehavior('nestedSetsBehavior');

        self::assertInstanceOf(
            ExtendableNestedSetsBehavior::class,
            $behavior,
            "'ExtendableMultipleTree' should use 'ExtendableNestedSetsBehavior'.",
        );

        $node->makeRoot();

        self::assertTrue(
            $behavior->invalidateCacheCalled,
            "'invalidateCache()' should be called during 'afterInsert()'.",
        );
        self::assertNotFalse(
            $node->treeAttribute,
            'Tree attribute should be set.',
        );
        self::assertNotNull(
            $node->getAttribute($node->treeAttribute),
            "Tree attribute should be set after 'afterInsert()'.",
        );
        self::assertEquals(
            $node->getPrimaryKey(),
            $node->getAttribute($node->treeAttribute),
            'Tree attribute should equal primary key for root node.',
        );
    }

    public function testAfterUpdateCacheInvalidationWhenMakeRoot(): void
    {
        $this->createDatabase();

        $root = new ExtendableMultipleTree(['name' => 'Root']);

        $root->makeRoot();

        $child = new ExtendableMultipleTree(['name' => 'Child']);

        $child->appendTo($root);

        $behavior = $child->getBehavior('nestedSetsBehavior');

        self::assertInstanceOf(
            ExtendableNestedSetsBehavior::class,
            $behavior,
            "'ExtendableMultipleTree' should use 'ExtendableNestedSetsBehavior'.",
        );

        $this->populateAndVerifyCache($behavior);

        $behavior->setOperation(NestedSetsBehavior::OPERATION_MAKE_ROOT);
        $behavior->afterUpdate();

        $this->verifyCacheInvalidation($behavior);
    }

    public function testAfterUpdateCacheInvalidationWhenMakeRootAndNodeItsNull(): void
    {
        $this->createDatabase();

        $root = new ExtendableMultipleTree(['name' => 'Root']);

        $root->makeRoot();

        $child = new ExtendableMultipleTree(['name' => 'Child']);

        $child->appendTo($root);

        $behavior = $child->getBehavior('nestedSetsBehavior');

        self::assertInstanceOf(
            ExtendableNestedSetsBehavior::class,
            $behavior,
            "'ExtendableMultipleTree' should use 'ExtendableNestedSetsBehavior'.",
        );

        $this->populateAndVerifyCache($behavior);

        $behavior->setNode(null);
        $behavior->afterUpdate();

        $this->verifyCacheInvalidation($behavior);
    }

    public function testCacheInvalidationAfterAppendTo(): void
    {
        $this->createDatabase();

        $root = new MultipleTree(['name' => 'Root']);

        $root->makeRoot();

        $child1 = new MultipleTree(['name' => 'Child 1']);

        $child1->appendTo($root);

        $child2 = new MultipleTree(['name' => 'Child 2']);

        $behavior = $child2->getBehavior('nestedSetsBehavior');

        self::assertNotNull($behavior, 'Behavior should be attached to the child node.');

        $child2->appendTo($root);

        $this->populateAndVerifyCache($behavior);

        $child2->setAttribute('lft', 3);
        $child2->save();

        $child2->appendTo($child1);

        $this->verifyCacheInvalidation($behavior);
    }

    public function testCacheInvalidationAfterDeleteWithChildren(): void
    {
        $this->createDatabase();

        $root = new MultipleTree(['name' => 'Root']);

        $root->makeRoot();

        $child = new MultipleTree(['name' => 'Child']);

        $child->appendTo($root);

        $grandchild = new MultipleTree(['name' => 'Grandchild']);

        $grandchild->appendTo($child);
        $behavior = $child->getBehavior('nestedSetsBehavior');

        self::assertNotNull($behavior, 'Behavior should be attached to the child node.');

        $this->populateAndVerifyCache($behavior);

        $child->deleteWithChildren();

        $this->verifyCacheInvalidation($behavior);
    }

    public function testCacheInvalidationAfterInsertWithoutTreeAttribute(): void
    {
        $this->createDatabase();

        $node = new Tree(['name' => 'Root Node']);

        $behavior = $node->getBehavior('nestedSetsBehavior');

        self::assertNotNull(
            $behavior,
            'Behavior should be attached to the node.',
        );

        $node->makeRoot();

        $this->populateAndVerifyCache($behavior);

        $node->invalidateCache();

        $this->verifyCacheInvalidation($behavior);

        self::assertEquals(
            0,
            Assert::invokeMethod($behavior, 'getDepthValue'),
            "New cached depth value should be '0' for root.",
        );
        self::assertEquals(
            1,
            Assert::invokeMethod($behavior, 'getLeftValue'),
            "New cached left value should be '1' for root.",
        );
        self::assertEquals(
            2,
            Assert::invokeMethod($behavior, 'getRightValue'),
            "New cached right value should be '2' for root.",
        );
    }

    public function testCacheInvalidationAfterInsertWithTreeAttribute(): void
    {
        $this->createDatabase();

        $node = new MultipleTree(['name' => 'Root Node']);

        $behavior = $node->getBehavior('nestedSetsBehavior');

        self::assertNotNull(
            $behavior,
            'Behavior should be attached to the node.',
        );

        $node->makeRoot();

        $this->populateAndVerifyCache($behavior);

        $node->invalidateCache();

        $this->verifyCacheInvalidation($behavior);

        self::assertEquals(
            0,
            Assert::invokeMethod($behavior, 'getDepthValue'),
            "New cached depth value should be '0' for root.",
        );
        self::assertEquals(
            1,
            Assert::invokeMethod($behavior, 'getLeftValue'),
            "New cached left value should be '1' for root.",
        );
        self::assertEquals(
            2,
            Assert::invokeMethod($behavior, 'getRightValue'),
            "New cached right value should be '2' for root.",
        );
        self::assertNotFalse(
            $node->treeAttribute,
            'Tree attribute should be set.',
        );
        self::assertNotNull(
            $node->getAttribute($node->treeAttribute),
            "Tree attribute should be set after 'afterInsert()'.",
        );
        self::assertNotNull(
            $node->owner,
            "Node owner should not be null after 'makeRoot()'.",
        );
        self::assertEquals(
            $node->owner->getPrimaryKey(),
            $node->getAttribute($node->treeAttribute),
            'Tree attribute should equal primary key for root node.',
        );
    }

    public function testCacheInvalidationAfterMakeRoot(): void
    {
        $this->createDatabase();

        $root = new MultipleTree(['name' => 'Original Root']);

        $root->makeRoot();

        $child = new MultipleTree(['name' => 'Child']);

        $child->appendTo($root);

        $behavior = $child->getBehavior('nestedSetsBehavior');

        self::assertNotNull(
            $behavior,
            'Behavior should be attached to the child node.',
        );

        self::assertEquals(
            $child->getAttribute('depth'),
            Assert::invokeMethod($behavior, 'getDepthValue'),
            'Initial cached depth value should match attribute.',
        );
        self::assertEquals(
            $child->getAttribute('lft'),
            Assert::invokeMethod($behavior, 'getLeftValue'),
            'Initial cached left value should match attribute.',
        );
        self::assertEquals(
            $child->getAttribute('rgt'),
            Assert::invokeMethod($behavior, 'getRightValue'),
            'Initial cached right value should match attribute.',
        );

        $child->makeRoot();

        $this->verifyCacheInvalidation($behavior);

        self::assertEquals(
            0,
            Assert::invokeMethod($behavior, 'getDepthValue'),
            "New cached depth value should be '0' for root.",
        );
        self::assertEquals(
            1,
            Assert::invokeMethod($behavior, 'getLeftValue'),
            "New cached left value should be '1' for root.",
        );
        self::assertEquals(
            2,
            Assert::invokeMethod($behavior, 'getRightValue'),
            "New cached right value should be '2' for root.",
        );
    }

    public function testGetDepthValueMemoization(): void
    {
        $this->createDatabase();

        $node = new Tree(['name' => 'Root']);

        $node->makeRoot();

        $mock = $this->getMockBuilder(Tree::class)
            ->onlyMethods(['getAttribute'])
            ->getMock();

        $mock->expects(self::once())
            ->method('getAttribute')
            ->with('depth')
            ->willReturn(42);

        $behavior = $mock->getBehavior('nestedSetsBehavior');

        self::assertNotNull(
            $behavior,
            'Behavior should be attached to the node.',
        );

        $firstCall = Assert::invokeMethod($behavior, 'getDepthValue');

        self::assertSame(
            42,
            $firstCall,
            'First call should return the mocked value.',
        );

        $secondCall = Assert::invokeMethod($behavior, 'getDepthValue');

        self::assertSame(
            42,
            $secondCall,
            'Second call should return the same cached value.',
        );
        self::assertSame(
            42,
            Assert::inaccessibleProperty($behavior, 'depthValue'),
            'Depth value should be cached after first access.',
        );
    }

    public function testGetLeftValueMemoization(): void
    {
        $this->createDatabase();

        $node = new Tree(['name' => 'Root']);

        $node->makeRoot();

        $mock = $this->getMockBuilder(Tree::class)
            ->onlyMethods(['getAttribute'])
            ->getMock();

        $mock->expects(self::once())
            ->method('getAttribute')
            ->with('lft')
            ->willReturn(123);

        $behavior = $mock->getBehavior('nestedSetsBehavior');

        self::assertNotNull(
            $behavior,
            'Behavior should be attached to the node.',
        );

        $firstCall = Assert::invokeMethod($behavior, 'getLeftValue');

        self::assertSame(
            123,
            $firstCall,
            'First call should return the mocked value.',
        );

        $secondCall = Assert::invokeMethod($behavior, 'getLeftValue');

        self::assertSame(
            123,
            $secondCall,
            'Second call should return the same cached value.',
        );
        self::assertSame(
            123,
            Assert::inaccessibleProperty($behavior, 'leftValue'),
            'Left value should be cached after first access.',
        );
    }

    public function testGetRightValueMemoization(): void
    {
        $this->createDatabase();

        $node = new Tree(['name' => 'Root']);
        $node->makeRoot();

        $mock = $this->getMockBuilder(Tree::class)
            ->onlyMethods(['getAttribute'])
            ->getMock();

        $mock->expects(self::once())
            ->method('getAttribute')
            ->with('rgt')
            ->willReturn(456);

        $behavior = $mock->getBehavior('nestedSetsBehavior');

        self::assertNotNull(
            $behavior,
            'Behavior should be attached to the node.',
        );

        $firstCall = Assert::invokeMethod($behavior, 'getRightValue');

        self::assertSame(
            456,
            $firstCall,
            'First call should return the mocked value.',
        );

        $secondCall = Assert::invokeMethod($behavior, 'getRightValue');

        self::assertSame(
            456,
            $secondCall,
            'Second call should return the same cached value.',
        );
        self::assertSame(
            456,
            Assert::inaccessibleProperty($behavior, 'rightValue'),
            'Right value should be cached after first access.',
        );
    }

    public function testManualCacheInvalidation(): void
    {
        $this->createDatabase();

        $root = new MultipleTree(['name' => 'Root']);

        $root->makeRoot();

        $behavior = $root->getBehavior('nestedSetsBehavior');

        self::assertNotNull(
            $behavior,
            'Behavior should be attached to the root node.',
        );

        $this->populateAndVerifyCache($behavior);

        $root->invalidateCache();

        $this->verifyCacheInvalidation($behavior);

        self::assertEquals(
            0,
            Assert::invokeMethod($behavior, 'getDepthValue'),
            'Depth value should be correctly retrieved after invalidation.',
        );
        self::assertEquals(
            1,
            Assert::invokeMethod($behavior, 'getLeftValue'),
            'Left value should be correctly retrieved after invalidation.',
        );
        self::assertEquals(
            2,
            Assert::invokeMethod($behavior, 'getRightValue'),
            'Right value should be correctly retrieved after invalidation.',
        );
    }

    /**
     * @phpstan-param Behavior<ActiveRecord> $behavior
     */
    private function populateAndVerifyCache(Behavior $behavior): void
    {
        Assert::invokeMethod($behavior, 'getDepthValue');
        Assert::invokeMethod($behavior, 'getLeftValue');
        Assert::invokeMethod($behavior, 'getRightValue');

        self::assertNotNull(
            Assert::inaccessibleProperty($behavior, 'depthValue'),
            'Depth value cache should be populated.',
        );
        self::assertNotNull(
            Assert::inaccessibleProperty($behavior, 'leftValue'),
            'Left value cache should be populated.',
        );
        self::assertNotNull(
            Assert::inaccessibleProperty($behavior, 'rightValue'),
            'Right value cache should be populated.',
        );
    }

    /**
     * @phpstan-param Behavior<ActiveRecord> $behavior
     */
    private function verifyCacheInvalidation(Behavior $behavior): void
    {
        self::assertNull(
            Assert::inaccessibleProperty($behavior, 'depthValue'),
            "Depth value cache should be invalidated after 'makeRoot()'/'afterInsert()'.",
        );
        self::assertNull(
            Assert::inaccessibleProperty($behavior, 'leftValue'),
            "Left value cache should be invalidated after 'makeRoot()'/'afterInsert()'.",
        );
        self::assertNull(
            Assert::inaccessibleProperty($behavior, 'node'),
            "Node cache should be 'null' after manual invalidation.",
        );
        self::assertNull(
            Assert::inaccessibleProperty($behavior, 'operation'),
            "Operation cache should be 'null' after manual invalidation.",
        );
        self::assertNull(
            Assert::inaccessibleProperty($behavior, 'rightValue'),
            "Right value cache should be invalidated after 'makeRoot()'/'afterInsert()'.",
        );
    }
}
