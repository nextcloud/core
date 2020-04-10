<?php
/**
 * @copyright Copyright (c) 2017 Joas Schilling <coding@schilljs.com>
 *
 * @author Joas Schilling <coding@schilljs.com>
 * @author Lukas Reschke <lukas@statuscode.ch>
 *
 * @license GNU AGPL version 3 or any later version
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 *
 */

namespace OC\App\CodeChecker;

use PhpParser\Node;
use PhpParser\Node\Name;
use PhpParser\NodeVisitorAbstract;

class MigrationSchemaChecker extends NodeVisitorAbstract {

	/** @var string */
	protected $schemaVariableName = null;
	/** @var array */
	protected $tableVariableNames = [];
	/** @var array */
	public $errors = [];

	/**
	 * @param Node $node
	 * @return void
	 *
	 * @suppress PhanUndeclaredProperty
	 */
	public function enterNode(Node $node) {
		/**
		 * Check tables
		 */
		if ($this->schemaVariableName !== null &&
			 $node instanceof Node\Expr\Assign &&
			 $node->var instanceof Node\Expr\Variable &&
			 $node->expr instanceof Node\Expr\MethodCall &&
			 $node->expr->var instanceof Node\Expr\Variable &&
			 $node->expr->var->name === $this->schemaVariableName) {

			if ($node->expr->name === 'createTable') {
				if (isset($node->expr->args[0]) && $node->expr->args[0]->value instanceof Node\Scalar\String_) {
					if (!$this->checkNameLength($node->expr->args[0]->value->value)) {
						$this->errors[] = [
							'line' => $node->getLine(),
							'disallowedToken' => $node->expr->args[0]->value->value,
							'reason' => 'Table name is too long (max. 27)',
						];
					} else {
						$this->tableVariableNames[$node->var->name] = $node->expr->args[0]->value->value;
					}
				}
			} else if ($node->expr->name === 'getTable') {
				if (isset($node->expr->args[0]) && $node->expr->args[0]->value instanceof Node\Scalar\String_) {
					$this->tableVariableNames[$node->var->name] = $node->expr->args[0]->value->value;
				}
			}
		} else if ($this->schemaVariableName !== null &&
			 $node instanceof Node\Expr\MethodCall &&
			 $node->var instanceof Node\Expr\Variable &&
			 $node->var->name === $this->schemaVariableName) {

			if ($node->name === 'renameTable') {
				$this->errors[] = [
					'line' => $node->getLine(),
					'disallowedToken' => 'Deprecated method',
					'reason' => sprintf(
						'`$%s->renameTable()` must not be used',
						$node->var->name
					),
				];
			}

		/**
		 * Check columns and Indexes
		 */
		} else if (!empty($this->tableVariableNames) &&
			 $node instanceof Node\Expr\MethodCall &&
			 $node->var instanceof Node\Expr\Variable &&
			 isset($this->tableVariableNames[$node->var->name])) {

			if ($node->name === 'addColumn' || $node->name === 'changeColumn') {
				if (isset($node->args[0]) && $node->args[0]->value instanceof Node\Scalar\String_) {
					if (!$this->checkNameLength($node->args[0]->value->value)) {
						$this->errors[] = [
							'line' => $node->getLine(),
							'disallowedToken' => $node->args[0]->value->value,
							'reason' => sprintf(
								'Column name is too long on table `%s` (max. 27)',
								$this->tableVariableNames[$node->var->name]
							),
						];
					}

					// On autoincrement the max length of the table name is 21 instead of 27
					if (isset($node->args[2]) && $node->args[2]->value instanceof Node\Expr\Array_) {
						/** @var Node\Expr\Array_ $options */
						$options = $node->args[2]->value;
						if ($this->checkColumnForAutoincrement($options)) {
							if (!$this->checkNameLength($this->tableVariableNames[$node->var->name], true)) {
								$this->errors[] = [
									'line' => $node->getLine(),
									'disallowedToken' => $this->tableVariableNames[$node->var->name],
									'reason' => 'Table name is too long because of autoincrement (max. 21)',
								];
							}
						}
					}
				}
			} else if ($node->name === 'addIndex' ||
				 $node->name === 'addUniqueIndex' ||
				 $node->name === 'renameIndex' ||
				 $node->name === 'setPrimaryKey') {
				if (isset($node->args[1]) && $node->args[1]->value instanceof Node\Scalar\String_) {
					if (!$this->checkNameLength($node->args[1]->value->value)) {
						$this->errors[] = [
							'line' => $node->getLine(),
							'disallowedToken' => $node->args[1]->value->value,
							'reason' => sprintf(
								'Index name is too long on table `%s` (max. 27)',
								$this->tableVariableNames[$node->var->name]
							),
						];
					}
				}
			} else if ($node->name === 'addForeignKeyConstraint') {
				if (isset($node->args[4]) && $node->args[4]->value instanceof Node\Scalar\String_) {
					if (!$this->checkNameLength($node->args[4]->value->value)) {
						$this->errors[] = [
							'line' => $node->getLine(),
							'disallowedToken' => $node->args[4]->value->value,
							'reason' => sprintf(
								'Constraint name is too long on table `%s` (max. 27)',
								$this->tableVariableNames[$node->var->name]
							),
						];
					}
				}
			} else if ($node->name === 'renameColumn') {
				$this->errors[] = [
					'line' => $node->getLine(),
					'disallowedToken' => 'Deprecated method',
					'reason' => sprintf(
						'`$%s->renameColumn()` must not be used',
						$node->var->name
					),
				];
			}

		/**
		 * Find the schema
		 */
		} else if ($node instanceof Node\Expr\Assign &&
			 $node->expr instanceof Node\Expr\FuncCall &&
			 $node->var instanceof Node\Expr\Variable &&
			 $node->expr->name instanceof Node\Expr\Variable &&
			 $node->expr->name->name === 'schemaClosure') {
			// E.g. $schema = $schemaClosure();
			$this->schemaVariableName = $node->var->name;
		}
	}

	protected function checkNameLength($tableName, $hasAutoincrement = false) {
		if ($hasAutoincrement) {
			return strlen($tableName) <= 21;
		}
		return strlen($tableName) <= 27;
	}

	/**
	 * @param Node\Expr\Array_ $optionsArray
	 * @return bool Whether the column is an autoincrement column
	 */
	protected function checkColumnForAutoincrement(Node\Expr\Array_ $optionsArray) {
		foreach ($optionsArray->items as $option) {
			if ($option->key instanceof Node\Scalar\String_) {
				if ($option->key->value === 'autoincrement' &&
					 $option->value instanceof Node\Expr\ConstFetch) {
					/** @var Node\Expr\ConstFetch $const */
					$const = $option->value;

					if ($const->name instanceof Name &&
						 $const->name->parts === ['true']) {
						return true;
					}
				}
			}
		}

		return false;
	}
}
