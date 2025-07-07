<?php

declare(strict_types=1);

namespace yii2\extensions\nestedsets\tests\base;

use yii2\extensions\nestedsets\NestedSetsBehavior;
use yii2\extensions\nestedsets\tests\support\model\{Tree, TreeWithStrictValidation};
use yii2\extensions\nestedsets\tests\TestCase;

/**
 * Base class for validation and structural integrity tests in nested sets tree behaviors.
 *
 * Provides a focused suite of unit tests for validating node creation, root assignment, and structural attribute
 * correctness in nested sets tree models, including strict validation scenarios and direct manipulation of node
 * attributes during insertion.
 *
 * This class ensures that node validation logic, left/right attribute shifting, and depth assignment are correctly
 * handled when creating root nodes, appending children, and invoking internal behavior methods.
 *
 * It covers both validation-enabled and validation-bypassed operations, as well as direct calls to behavior hooks for
 * attribute initialization.
 *
 * Key features.
 * - Ensures correct attribute assignment when appending children to root nodes.
 * - Tests strict validation logic for root node creation with and without validation enforcement.
 * - Validates direct invocation of behavior hooks for node attribute initialization.
 * - Verifies left, right, and depth attribute values after root and child node operations.
 *
 * @see NestedSetsBehavior for behavior implementation and hooks.
 * @see Tree for standard nested sets model.
 * @see TreeWithStrictValidation for strict validation scenarios.
 *
 * @copyright Copyright (C) 2023 Terabytesoftw.
 * @license https://opensource.org/license/bsd-3-clause BSD 3-Clause License.
 */
abstract class AbstractValidationAndStructure extends TestCase
{
    public function testMakeRootWithRunValidationParameterUsingStrictValidation(): void
    {
        $this->createDatabase();

        $invalidNode = new TreeWithStrictValidation(['name' => 'x']);

        $result1 = $invalidNode->makeRoot();
        $hasError1 = $invalidNode->hasErrors();

        self::assertFalse(
            $result1,
            "'makeRoot()' should return 'false' when 'runValidation=true' and data fails validation.",
        );
        self::assertTrue(
            $hasError1,
            "Node should have validation errors when 'runValidation=true' and data is invalid.",
        );

        $invalidNode2 = new TreeWithStrictValidation(['name' => 'x']);

        $result2 = $invalidNode2->makeRoot(false);
        $hasError2 = $invalidNode2->hasErrors();

        self::assertTrue(
            $result2,
            "'makeRoot()' should return 'true' when 'runValidation=false', even with invalid data that would fail " .
            'validation.',
        );
        self::assertFalse(
            $hasError2,
            "Node should not have validation errors when 'runValidation=false' because validation was skipped.",
        );

        $persistedNode = TreeWithStrictValidation::findOne($invalidNode2->id);

        self::assertNotNull(
            $persistedNode,
            "Node should exist in database after 'makeRoot()' with validation disabled.",
        );
        self::assertTrue(
            $persistedNode->isRoot(),
            "Node should be a root node after 'makeRoot()' operation.",
        );
        self::assertEquals(
            1,
            $persistedNode->lft,
            "Root node should have left value of '1'.",
        );
        self::assertEquals(
            2,
            $persistedNode->rgt,
            "Root node should have right value of '2'.",
        );
        self::assertEquals(
            0,
            $persistedNode->depth,
            "Root node should have depth of '0'.",
        );
    }

    public function testReturnShiftedLeftRightAttributesWhenChildAppendedToRoot(): void
    {
        $this->createDatabase();

        $root = new Tree(['name' => 'Root']);

        $root->makeRoot();
        $root->refresh();

        $child = new Tree(['name' => 'Child']);

        $child->appendTo($root);
        $child->refresh();

        self::assertEquals(
            1,
            $root->lft,
            "Root node left value should be '1' after 'makeRoot()' and appending a child.",
        );
        self::assertEquals(
            4,
            $root->rgt,
            "Root node right value should be '4' after 'makeRoot()' and appending a child.",
        );
        self::assertEquals(
            2,
            $child->lft,
            "Child node left value should be '2' after being 'appendTo()' to the root node.",
        );
        self::assertEquals(
            3,
            $child->rgt,
            "Child node right value should be '3' after being 'appendTo()' the root node.",
        );
        self::assertNotEquals(
            0,
            $child->lft,
            "Child node left value should not be '0' after 'appendTo()' operation.",
        );
        self::assertNotEquals(
            1,
            $child->rgt,
            "Child node right value should not be '1' after 'appendTo()' operation.",
        );
    }

    public function testSetNodeToNullAndCallBeforeInsertNodeSetsLftRgtAndDepth(): void
    {
        $this->createDatabase();

        $behavior = new class extends NestedSetsBehavior {
            public function callBeforeInsertNode(int $value, int $depth): void
            {
                $this->beforeInsertNode($value, $depth);
            }

            public function setNodeToNull(): void
            {
                $this->node = null;
            }

            public function getNodeDepth(): int|null
            {
                return $this->node?->getAttribute($this->depthAttribute);
            }
        };

        $newNode = new Tree(['name' => 'Test Node']);

        $newNode->attachBehavior('testBehavior', $behavior);
        $behavior->setNodeToNull();
        $behavior->callBeforeInsertNode(5, 1);

        self::assertEquals(
            5,
            $newNode->lft,
            "'beforeInsertNode' should set 'lft' attribute to '5' on the new node.",
        );
        self::assertEquals(
            6,
            $newNode->rgt,
            "'beforeInsertNode' should set 'rgt' attribute to '6' on the new node.",
        );

        $actualDepth = $newNode->getAttribute('depth');

        self::assertEquals(
            1,
            $actualDepth,
            "'beforeInsertNode' method should set 'depth' attribute to '1' on the new node.",
        );
    }
}
