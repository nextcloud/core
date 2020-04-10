<?php
/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 *
 * @author Daniel Kesselberg <mail@danielkesselberg.de>
 * @author Joas Schilling <coding@schilljs.com>
 * @author Morris Jobke <hey@morrisjobke.de>
 * @author Thomas Müller <thomas.mueller@tmit.eu>
 *
 * @license AGPL-3.0
 *
 * This code is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License, version 3,
 * as published by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License, version 3,
 * along with this program. If not, see <http://www.gnu.org/licenses/>
 *
 */

namespace OC\App\CodeChecker;

use PhpParser\Node;
use PhpParser\Node\Name;
use PhpParser\NodeVisitorAbstract;

class NodeVisitor extends NodeVisitorAbstract {
	/** @var ICheck */
	protected $list;

	/** @var string */
	protected $blackListDescription;
	/** @var string[] */
	protected $blackListedClassNames;
	/** @var string[] */
	protected $blackListedConstants;
	/** @var string[] */
	protected $blackListedFunctions;
	/** @var string[] */
	protected $blackListedMethods;
	/** @var bool */
	protected $checkEqualOperatorUsage;
	/** @var string[] */
	protected $errorMessages;

	/**
	 * @param ICheck $list
	 */
	public function __construct(ICheck $list) {
		$this->list = $list;

		$this->blackListedClassNames = [];
		foreach ($list->getClasses() as $class => $blackListInfo) {
			if (is_numeric($class) && is_string($blackListInfo)) {
				$class = $blackListInfo;
				$blackListInfo = null;
			}

			$class = strtolower($class);
			$this->blackListedClassNames[$class] = $class;
		}

		$this->blackListedConstants = [];
		foreach ($list->getConstants() as $constantName => $blackListInfo) {
			$constantName = strtolower($constantName);
			$this->blackListedConstants[$constantName] = $constantName;
		}

		$this->blackListedFunctions = [];
		foreach ($list->getFunctions() as $functionName => $blackListInfo) {
			$functionName = strtolower($functionName);
			$this->blackListedFunctions[$functionName] = $functionName;
		}

		$this->blackListedMethods = [];
		foreach ($list->getMethods() as $functionName => $blackListInfo) {
			$functionName = strtolower($functionName);
			$this->blackListedMethods[$functionName] = $functionName;
		}

		$this->checkEqualOperatorUsage = $list->checkStrongComparisons();

		$this->errorMessages = [
			CodeChecker::CLASS_EXTENDS_NOT_ALLOWED => "%s class must not be extended",
			CodeChecker::CLASS_IMPLEMENTS_NOT_ALLOWED => "%s interface must not be implemented",
			CodeChecker::STATIC_CALL_NOT_ALLOWED => "Static method of %s class must not be called",
			CodeChecker::CLASS_CONST_FETCH_NOT_ALLOWED => "Constant of %s class must not not be fetched",
			CodeChecker::CLASS_NEW_NOT_ALLOWED => "%s class must not be instantiated",
			CodeChecker::CLASS_USE_NOT_ALLOWED => "%s class must not be imported with a use statement",
			CodeChecker::CLASS_METHOD_CALL_NOT_ALLOWED => "Method of %s class must not be called",

			CodeChecker::OP_OPERATOR_USAGE_DISCOURAGED => "is discouraged",
		];
	}

	/** @var array */
	public $errors = [];

	public function enterNode(Node $node) {
		if ($this->checkEqualOperatorUsage && $node instanceof Node\Expr\BinaryOp\Equal) {
			$this->errors[]= [
				'disallowedToken' => '==',
				'errorCode' => CodeChecker::OP_OPERATOR_USAGE_DISCOURAGED,
				'line' => $node->getLine(),
				'reason' => $this->buildReason('==', CodeChecker::OP_OPERATOR_USAGE_DISCOURAGED)
			];
		}
		if ($this->checkEqualOperatorUsage && $node instanceof Node\Expr\BinaryOp\NotEqual) {
			$this->errors[]= [
				'disallowedToken' => '!=',
				'errorCode' => CodeChecker::OP_OPERATOR_USAGE_DISCOURAGED,
				'line' => $node->getLine(),
				'reason' => $this->buildReason('!=', CodeChecker::OP_OPERATOR_USAGE_DISCOURAGED)
			];
		}
		if ($node instanceof Node\Stmt\Class_) {
			if (!is_null($node->extends)) {
				$this->checkBlackList($node->extends->toString(), CodeChecker::CLASS_EXTENDS_NOT_ALLOWED, $node);
			}
			foreach ($node->implements as $implements) {
				$this->checkBlackList($implements->toString(), CodeChecker::CLASS_IMPLEMENTS_NOT_ALLOWED, $node);
			}
		}
		if ($node instanceof Node\Expr\StaticCall) {
			if (!is_null($node->class)) {
				if ($node->class instanceof Name) {
					$this->checkBlackList($node->class->toString(), CodeChecker::STATIC_CALL_NOT_ALLOWED, $node);

					$this->checkBlackListFunction($node->class->toString(), $node->name, $node);
					$this->checkBlackListMethod($node->class->toString(), $node->name, $node);
				}

				if ($node->class instanceof Node\Expr\Variable) {
					/**
					 * TODO: find a way to detect something like this:
					 *       $c = "OC_API";
					 *       $n = $c::call();
					 */
					// $this->checkBlackListMethod($node->class->..., $node->name, $node);
				}
			}
		}
		if ($node instanceof Node\Expr\MethodCall) {
			if (!is_null($node->var)) {
				if ($node->var instanceof Node\Expr\Variable) {
					/**
					 * TODO: find a way to detect something like this:
					 *       $c = new OC_API();
					 *       $n = $c::call();
					 *       $n = $c->call();
					 */
					// $this->checkBlackListMethod($node->var->..., $node->name, $node);
				}
			}
		}
		if ($node instanceof Node\Expr\ClassConstFetch) {
			if (!is_null($node->class)) {
				if ($node->class instanceof Name) {
					$this->checkBlackList($node->class->toString(), CodeChecker::CLASS_CONST_FETCH_NOT_ALLOWED, $node);
				}
				if ($node->class instanceof Node\Expr\Variable || $node->class instanceof Node\Expr\PropertyFetch) {
					/**
					 * TODO: find a way to detect something like this:
					 *       $c = "OC_API";
					 *       $n = $i::ADMIN_AUTH;
					 */
				} else {
					$this->checkBlackListConstant($node->class->toString(), $node->name, $node);
				}
			}
		}
		if ($node instanceof Node\Expr\New_) {
			if (!is_null($node->class)) {
				if ($node->class instanceof Name) {
					$this->checkBlackList($node->class->toString(), CodeChecker::CLASS_NEW_NOT_ALLOWED, $node);
				}
				if ($node->class instanceof Node\Expr\Variable) {
					/**
					 * TODO: find a way to detect something like this:
					 *       $c = "OC_API";
					 *       $n = new $i;
					 */
				}
			}
		}
		if ($node instanceof Node\Stmt\UseUse) {
			$this->checkBlackList($node->name->toString(), CodeChecker::CLASS_USE_NOT_ALLOWED, $node);
			if ($node->alias) {
				$this->addUseNameToBlackList($node->name->toString(), $node->alias);
			} else {
				$this->addUseNameToBlackList($node->name->toString(), $node->name->getLast());
			}
		}
	}

	/**
	 * Check whether an alias was introduced for a namespace of a blacklisted class
	 *
	 * Example:
	 * - Blacklist entry:      OCP\AppFramework\IApi
	 * - Name:                 OCP\AppFramework
	 * - Alias:                OAF
	 * =>  new blacklist entry:  OAF\IApi
	 *
	 * @param string $name
	 * @param string $alias
	 */
	private function addUseNameToBlackList($name, $alias) {
		$name = strtolower($name);
		$alias = strtolower($alias);

		foreach ($this->blackListedClassNames as $blackListedAlias => $blackListedClassName) {
			if (strpos($blackListedClassName, $name . '\\') === 0) {
				$aliasedClassName = str_replace($name, $alias, $blackListedClassName);
				$this->blackListedClassNames[$aliasedClassName] = $blackListedClassName;
			}
		}

		foreach ($this->blackListedConstants as $blackListedAlias => $blackListedConstant) {
			if (strpos($blackListedConstant, $name . '\\') === 0 || strpos($blackListedConstant, $name . '::') === 0) {
				$aliasedConstantName = str_replace($name, $alias, $blackListedConstant);
				$this->blackListedConstants[$aliasedConstantName] = $blackListedConstant;
			}
		}

		foreach ($this->blackListedFunctions as $blackListedAlias => $blackListedFunction) {
			if (strpos($blackListedFunction, $name . '\\') === 0 || strpos($blackListedFunction, $name . '::') === 0) {
				$aliasedFunctionName = str_replace($name, $alias, $blackListedFunction);
				$this->blackListedFunctions[$aliasedFunctionName] = $blackListedFunction;
			}
		}

		foreach ($this->blackListedMethods as $blackListedAlias => $blackListedMethod) {
			if (strpos($blackListedMethod, $name . '\\') === 0 || strpos($blackListedMethod, $name . '::') === 0) {
				$aliasedMethodName = str_replace($name, $alias, $blackListedMethod);
				$this->blackListedMethods[$aliasedMethodName] = $blackListedMethod;
			}
		}
	}

	private function checkBlackList($name, $errorCode, Node $node) {
		$lowerName = strtolower($name);

		if (isset($this->blackListedClassNames[$lowerName])) {
			$this->errors[]= [
				'disallowedToken' => $name,
				'errorCode' => $errorCode,
				'line' => $node->getLine(),
				'reason' => $this->buildReason($this->blackListedClassNames[$lowerName], $errorCode)
			];
		}
	}

	private function checkBlackListConstant($class, $constantName, Node $node) {
		$name = $class . '::' . $constantName;
		$lowerName = strtolower($name);

		if (isset($this->blackListedConstants[$lowerName])) {
			$this->errors[]= [
				'disallowedToken' => $name,
				'errorCode' => CodeChecker::CLASS_CONST_FETCH_NOT_ALLOWED,
				'line' => $node->getLine(),
				'reason' => $this->buildReason($this->blackListedConstants[$lowerName], CodeChecker::CLASS_CONST_FETCH_NOT_ALLOWED)
			];
		}
	}

	private function checkBlackListFunction($class, $functionName, Node $node) {
		$name = $class . '::' . $functionName;
		$lowerName = strtolower($name);

		if (isset($this->blackListedFunctions[$lowerName])) {
			$this->errors[]= [
				'disallowedToken' => $name,
				'errorCode' => CodeChecker::STATIC_CALL_NOT_ALLOWED,
				'line' => $node->getLine(),
				'reason' => $this->buildReason($this->blackListedFunctions[$lowerName], CodeChecker::STATIC_CALL_NOT_ALLOWED)
			];
		}
	}

	private function checkBlackListMethod($class, $functionName, Node $node) {
		$name = $class . '::' . $functionName;
		$lowerName = strtolower($name);

		if (isset($this->blackListedMethods[$lowerName])) {
			$this->errors[]= [
				'disallowedToken' => $name,
				'errorCode' => CodeChecker::CLASS_METHOD_CALL_NOT_ALLOWED,
				'line' => $node->getLine(),
				'reason' => $this->buildReason($this->blackListedMethods[$lowerName], CodeChecker::CLASS_METHOD_CALL_NOT_ALLOWED)
			];
		}
	}

	private function buildReason($name, $errorCode) {
		if (isset($this->errorMessages[$errorCode])) {
			$desc = $this->list->getDescription($errorCode, $name);
			return sprintf($this->errorMessages[$errorCode], $desc);
		}

		return "$name usage not allowed - error: $errorCode";
	}
}
