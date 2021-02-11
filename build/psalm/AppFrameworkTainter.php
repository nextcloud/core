<?php

/**
 * Copyright (c) 2020 Lukas Reschke <lukas@statuscode.ch>
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 *
 *
 * The above copyright notice and this permission notice shall be included in all
 * copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
 * SOFTWARE.
 */
use Psalm\CodeLocation;
use Psalm\Plugin\Hook\AfterFunctionLikeAnalysisInterface;
use Psalm\Type\TaintKindGroup;

class AppFrameworkTainter implements AfterFunctionLikeAnalysisInterface {
	public static function afterStatementAnalysis(
		PhpParser\Node\FunctionLike $stmt,
		Psalm\Storage\FunctionLikeStorage $classlike_storage,
		Psalm\StatementsSource $statements_source,
		Psalm\Codebase $codebase,
		array &$file_replacements = []
	): ?bool {
		if ($statements_source->getFQCLN() !== null) {
			if ($codebase->classExtendsOrImplements($statements_source->getFQCLN(), \OCP\AppFramework\Controller::class)) {
				if ($stmt instanceof PhpParser\Node\Stmt\ClassMethod) {
					if ($stmt->isPublic() && !$stmt->isMagic()) {
						foreach ($stmt->params as $i => $param) {
							$expr_type = new Psalm\Type\Union([new Psalm\Type\Atomic\TString()]);
							$expr_identifier = (strtolower($statements_source->getFQCLN()) . '::' . strtolower($classlike_storage->cased_name) . '#' . ($i+1));

							if ($expr_type) {
								$codebase->addTaintSource(
									$expr_type,
									$expr_identifier,
									TaintKindGroup::ALL_INPUT,
									new CodeLocation($statements_source, $param)
								);
							}
						}
					}
				}
			}
		}
		return null;
	}
}
