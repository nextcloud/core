<?php
/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 *
 * @author Christoph Wurst <christoph@winzerhof-wurst.at>
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

use OC\Hooks\BasicEmitter;
use PhpParser\NodeTraverser;
use PhpParser\Parser;
use PhpParser\ParserFactory;
use RecursiveCallbackFilterIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RegexIterator;
use SplFileInfo;

class CodeChecker extends BasicEmitter {
	public const CLASS_EXTENDS_NOT_ALLOWED = 1000;
	public const CLASS_IMPLEMENTS_NOT_ALLOWED = 1001;
	public const STATIC_CALL_NOT_ALLOWED = 1002;
	public const CLASS_CONST_FETCH_NOT_ALLOWED = 1003;
	public const CLASS_NEW_NOT_ALLOWED = 1004;
	public const OP_OPERATOR_USAGE_DISCOURAGED = 1005;
	public const CLASS_USE_NOT_ALLOWED = 1006;
	public const CLASS_METHOD_CALL_NOT_ALLOWED = 1007;

	/** @var Parser */
	private $parser;

	/** @var ICheck */
	protected $checkList;

	/** @var bool */
	protected $checkMigrationSchema;

	public function __construct(ICheck $checkList, $checkMigrationSchema) {
		$this->checkList = $checkList;
		$this->checkMigrationSchema = $checkMigrationSchema;
		$this->parser = (new ParserFactory)->create(ParserFactory::ONLY_PHP7);
	}

	/**
	 * @param string $appId
	 * @return array
	 * @throws \RuntimeException if app with $appId is unknown
	 */
	public function analyse(string $appId): array {
		$appPath = \OC_App::getAppPath($appId);
		if ($appPath === false) {
			throw new \RuntimeException("No app with given id <$appId> known.");
		}

		return $this->analyseFolder($appId, $appPath);
	}

	/**
	 * @param string $appId
	 * @param string $folder
	 * @return array
	 */
	public function analyseFolder(string $appId, string $folder): array {
		$errors = [];

		$excludedDirectories = ['vendor', '3rdparty', '.git', 'l10n', 'tests', 'test', 'build'];
		if ($appId === 'password_policy') {
			$excludedDirectories[] = 'lists';
		}

		$excludes = array_map(function ($item) use ($folder) {
			return $folder . '/' . $item;
		}, $excludedDirectories);

		$iterator = new RecursiveDirectoryIterator($folder, RecursiveDirectoryIterator::SKIP_DOTS);
		$iterator = new RecursiveCallbackFilterIterator($iterator, function ($item) use ($excludes) {
			/** @var SplFileInfo $item */
			foreach ($excludes as $exclude) {
				if (substr($item->getPath(), 0, strlen($exclude)) === $exclude) {
					return false;
				}
			}
			return true;
		});
		$iterator = new RecursiveIteratorIterator($iterator);
		$iterator = new RegexIterator($iterator, '/^.+\.php$/i');

		foreach ($iterator as $file) {
			/** @var SplFileInfo $file */
			$this->emit('CodeChecker', 'analyseFileBegin', [$file->getPathname()]);
			$fileErrors = $this->analyseFile($file->__toString());
			$this->emit('CodeChecker', 'analyseFileFinished', [$file->getPathname(), $fileErrors]);
			$errors = array_merge($fileErrors, $errors);
		}

		return $errors;
	}


	/**
	 * @param string $file
	 * @return array
	 */
	public function analyseFile(string $file): array {
		$code = file_get_contents($file);
		$statements = $this->parser->parse($code);

		$visitor = new NodeVisitor($this->checkList);
		$migrationVisitor = new MigrationSchemaChecker();
		$traverser = new NodeTraverser;
		$traverser->addVisitor($visitor);

		if ($this->checkMigrationSchema && preg_match('#^.+\\/Migration\\/Version[^\\/]{1,255}\\.php$#i', $file)) {
			$traverser->addVisitor($migrationVisitor);
		}

		$traverser->traverse($statements);

		return array_merge($visitor->errors, $migrationVisitor->errors);
	}
}
