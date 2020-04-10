<?php
/**
 * @copyright Copyright (c) 2017 Robin Appelman <robin@icewind.nl>
 *
 * @author Robin Appelman <robin@icewind.nl>
 * @author Roeland Jago Douma <roeland@famdouma.nl>
 * @author Tobias Kaminsky <tobias@kaminsky.me>
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

namespace OC\Files\Cache;

use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\Files\IMimeTypeLoader;
use OCP\Files\Search\ISearchBinaryOperator;
use OCP\Files\Search\ISearchComparison;
use OCP\Files\Search\ISearchOperator;
use OCP\Files\Search\ISearchOrder;

/**
 * Tools for transforming search queries into database queries
 */
class QuerySearchHelper {
	static protected $searchOperatorMap = [
		ISearchComparison::COMPARE_LIKE => 'iLike',
		ISearchComparison::COMPARE_EQUAL => 'eq',
		ISearchComparison::COMPARE_GREATER_THAN => 'gt',
		ISearchComparison::COMPARE_GREATER_THAN_EQUAL => 'gte',
		ISearchComparison::COMPARE_LESS_THAN => 'lt',
		ISearchComparison::COMPARE_LESS_THAN_EQUAL => 'lte'
	];

	static protected $searchOperatorNegativeMap = [
		ISearchComparison::COMPARE_LIKE => 'notLike',
		ISearchComparison::COMPARE_EQUAL => 'neq',
		ISearchComparison::COMPARE_GREATER_THAN => 'lte',
		ISearchComparison::COMPARE_GREATER_THAN_EQUAL => 'lt',
		ISearchComparison::COMPARE_LESS_THAN => 'gte',
		ISearchComparison::COMPARE_LESS_THAN_EQUAL => 'lt'
	];

	const TAG_FAVORITE = '_$!<Favorite>!$_';

	/** @var IMimeTypeLoader */
	private $mimetypeLoader;

	/**
	 * QuerySearchUtil constructor.
	 *
	 * @param IMimeTypeLoader $mimetypeLoader
	 */
	public function __construct(IMimeTypeLoader $mimetypeLoader) {
		$this->mimetypeLoader = $mimetypeLoader;
	}

	/**
	 * Whether or not the tag tables should be joined to complete the search
	 *
	 * @param ISearchOperator $operator
	 * @return boolean
	 */
	public function shouldJoinTags(ISearchOperator $operator) {
		if ($operator instanceof ISearchBinaryOperator) {
			return array_reduce($operator->getArguments(), function ($shouldJoin, ISearchOperator $operator) {
				return $shouldJoin || $this->shouldJoinTags($operator);
			}, false);
		} else if ($operator instanceof ISearchComparison) {
			return $operator->getField() === 'tagname' || $operator->getField() === 'favorite';
		}
		return false;
	}

	/**
	 * @param IQueryBuilder $builder
	 * @param ISearchOperator $operator
	 */
	public function searchOperatorArrayToDBExprArray(IQueryBuilder $builder, array $operators) {
		return array_filter(array_map(function ($operator) use ($builder) {
			return $this->searchOperatorToDBExpr($builder, $operator);
		}, $operators));
	}

	public function searchOperatorToDBExpr(IQueryBuilder $builder, ISearchOperator $operator) {
		$expr = $builder->expr();
		if ($operator instanceof ISearchBinaryOperator) {
			if (count($operator->getArguments()) === 0) {
				return null;
			}

			switch ($operator->getType()) {
				case ISearchBinaryOperator::OPERATOR_NOT:
					$negativeOperator = $operator->getArguments()[0];
					if ($negativeOperator instanceof ISearchComparison) {
						return $this->searchComparisonToDBExpr($builder, $negativeOperator, self::$searchOperatorNegativeMap);
					} else {
						throw new \InvalidArgumentException('Binary operators inside "not" is not supported');
					}
				case ISearchBinaryOperator::OPERATOR_AND:
					return call_user_func_array([$expr, 'andX'], $this->searchOperatorArrayToDBExprArray($builder, $operator->getArguments()));
				case ISearchBinaryOperator::OPERATOR_OR:
					return call_user_func_array([$expr, 'orX'], $this->searchOperatorArrayToDBExprArray($builder, $operator->getArguments()));
				default:
					throw new \InvalidArgumentException('Invalid operator type: ' . $operator->getType());
			}
		} else if ($operator instanceof ISearchComparison) {
			return $this->searchComparisonToDBExpr($builder, $operator, self::$searchOperatorMap);
		} else {
			throw new \InvalidArgumentException('Invalid operator type: ' . get_class($operator));
		}
	}

	private function searchComparisonToDBExpr(IQueryBuilder $builder, ISearchComparison $comparison, array $operatorMap) {
		$this->validateComparison($comparison);

		list($field, $value, $type) = $this->getOperatorFieldAndValue($comparison);
		if (isset($operatorMap[$type])) {
			$queryOperator = $operatorMap[$type];
			return $builder->expr()->$queryOperator($field, $this->getParameterForValue($builder, $value));
		} else {
			throw new \InvalidArgumentException('Invalid operator type: ' . $comparison->getType());
		}
	}

	private function getOperatorFieldAndValue(ISearchComparison $operator) {
		$field = $operator->getField();
		$value = $operator->getValue();
		$type = $operator->getType();
		if ($field === 'mimetype') {
			if ($operator->getType() === ISearchComparison::COMPARE_EQUAL) {
				$value = (int)$this->mimetypeLoader->getId($value);
			} else if ($operator->getType() === ISearchComparison::COMPARE_LIKE) {
				// transform "mimetype='foo/%'" to "mimepart='foo'"
				if (preg_match('|(.+)/%|', $value, $matches)) {
					$field = 'mimepart';
					$value = (int)$this->mimetypeLoader->getId($matches[1]);
					$type = ISearchComparison::COMPARE_EQUAL;
				} else if (strpos($value, '%') !== false) {
					throw new \InvalidArgumentException('Unsupported query value for mimetype: ' . $value . ', only values in the format "mime/type" or "mime/%" are supported');
				} else {
					$field = 'mimetype';
					$value = (int)$this->mimetypeLoader->getId($value);
					$type = ISearchComparison::COMPARE_EQUAL;
				}
			}
		} else if ($field === 'favorite') {
			$field = 'tag.category';
			$value = self::TAG_FAVORITE;
		} else if ($field === 'tagname') {
			$field = 'tag.category';
		} else if ($field === 'fileid') {
			$field = 'file.fileid';
		}
		return [$field, $value, $type];
	}

	private function validateComparison(ISearchComparison $operator) {
		$types = [
			'mimetype' => 'string',
			'mtime' => 'integer',
			'name' => 'string',
			'size' => 'integer',
			'tagname' => 'string',
			'favorite' => 'boolean',
			'fileid' => 'integer'
		];
		$comparisons = [
			'mimetype' => ['eq', 'like'],
			'mtime' => ['eq', 'gt', 'lt', 'gte', 'lte'],
			'name' => ['eq', 'like'],
			'size' => ['eq', 'gt', 'lt', 'gte', 'lte'],
			'tagname' => ['eq', 'like'],
			'favorite' => ['eq'],
			'fileid' => ['eq']
		];

		if (!isset($types[$operator->getField()])) {
			throw new \InvalidArgumentException('Unsupported comparison field ' . $operator->getField());
		}
		$type = $types[$operator->getField()];
		if (gettype($operator->getValue()) !== $type) {
			throw new \InvalidArgumentException('Invalid type for field ' . $operator->getField());
		}
		if (!in_array($operator->getType(), $comparisons[$operator->getField()])) {
			throw new \InvalidArgumentException('Unsupported comparison for field  ' . $operator->getField() . ': ' . $operator->getType());
		}
	}

	private function getParameterForValue(IQueryBuilder $builder, $value) {
		if ($value instanceof \DateTime) {
			$value = $value->getTimestamp();
		}
		if (is_numeric($value)) {
			$type = IQueryBuilder::PARAM_INT;
		} else {
			$type = IQueryBuilder::PARAM_STR;
		}
		return $builder->createNamedParameter($value, $type);
	}

	/**
	 * @param IQueryBuilder $query
	 * @param ISearchOrder[] $orders
	 */
	public function addSearchOrdersToQuery(IQueryBuilder $query, array $orders) {
		foreach ($orders as $order) {
			$query->addOrderBy($order->getField(), $order->getDirection());
		}
	}
}
