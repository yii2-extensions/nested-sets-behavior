<?php

declare(strict_types=1);

namespace yii2\extensions\nestedsets\tests;

use LogicException;
use yii\helpers\ArrayHelper;
use yii2\extensions\nestedsets\NestedSetsQueryBehavior;
use yii2\extensions\nestedsets\tests\support\model\{MultipleTree, Tree, TreeQuery, TreeWithStrictValidation};

final class NestedSetsQueryBehaviorTest extends TestCase
{
    public function testReturnLeavesForSingleAndMultipleTreeModels(): void
    {
        $this->generateFixtureTree();

        self::assertEquals(
            require "{$this->fixtureDirectory}/test-leaves-query.php",
            ArrayHelper::toArray(Tree::find()->leaves()->all()),
            'Should return correct leaf nodes for \'Tree\' model.',
        );
        self::assertEquals(
            require "{$this->fixtureDirectory}/test-leaves-multiple-tree-query.php",
            ArrayHelper::toArray(MultipleTree::find()->leaves()->all()),
            'Should return correct leaf nodes for \'MultipleTree\' model.',
        );
    }

    public function testReturnRootsForSingleAndMultipleTreeModels(): void
    {
        $this->generateFixtureTree();

        self::assertEquals(
            require "{$this->fixtureDirectory}/test-roots-query.php",
            ArrayHelper::toArray(Tree::find()->roots()->all()),
            'Should return correct root nodes for \'Tree\' model.',
        );
        self::assertEquals(
            require "{$this->fixtureDirectory}/test-roots-multiple-tree-query.php",
            ArrayHelper::toArray(MultipleTree::find()->roots()->all()),
            'Should return correct root nodes for \'MultipleTree\' model.',
        );
    }

    public function testThrowLogicExceptionWhenBehaviorIsNotAttachedToOwner(): void
    {
        $behavior = new NestedSetsQueryBehavior();

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('The "owner" property must be set before using the behavior.');

        $behavior->leaves();
    }

    public function testThrowLogicExceptionWhenBehaviorIsDetachedFromOwner(): void
    {
        $this->createDatabase();

        $node = new TreeQuery(Tree::class);

        $behavior = $node->getBehavior('nestedSetsQueryBehavior');

        self::assertInstanceOf(NestedSetsQueryBehavior::class, $behavior);

        $node->detachBehavior('nestedSetsQueryBehavior');

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('The "owner" property must be set before using the behavior.');

        $behavior->leaves();
    }

    public function testAppendToWithRunValidationParameterUsingStrictValidation(): void
    {
        $this->generateFixtureTree();

        $targetNode = Tree::findOne(2);

        self::assertNotNull(
            $targetNode,
            'Target node with ID \'2\' should exist before calling \'appendTo\'.',
        );

        $invalidNode = new TreeWithStrictValidation(['name' => 'x']);

        $result1 = $invalidNode->appendTo($targetNode);
        $hasError1 = $invalidNode->hasErrors();

        self::assertFalse(
            $result1,
            '\'appendTo()\' should return \'false\' when \'runValidation=true\' and data fails validation.',
        );
        self::assertTrue(
            $hasError1,
            'Node should have validation errors when \'runValidation=true\' and data is invalid.',
        );

        $invalidNode2 = new TreeWithStrictValidation(['name' => 'x']);

        $result2 = $invalidNode2->appendTo($targetNode, false);
        $hasError2 = $invalidNode2->hasErrors();

        self::assertTrue(
            $result2,
            '\'appendTo()\' should return \'true\' when \'runValidation=false\', even with invalid data ' .
            'that would fail validation.',
        );
        self::assertFalse(
            $hasError2,
            'Node should not have validation errors when \'runValidation=false\' because validation was skipped.',
        );

        $persistedNode = TreeWithStrictValidation::findOne($invalidNode2->id);

        self::assertNotNull(
            $persistedNode,
            "Node with ID '{$persistedNode?->id}' should exist after appending to target node.",
        );
        self::assertNotEquals(
            $hasError1,
            $hasError2,
            'Validation error states should differ between \'runValidation=true\' and \'runValidation=false\'.',
        );
    }
}
