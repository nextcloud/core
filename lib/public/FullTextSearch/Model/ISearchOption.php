<?php

declare(strict_types=1);

/**
 * @copyright 2018
 *
 * @author Christoph Wurst <christoph@winzerhof-wurst.at>
 * @author Maxence Lange <maxence@artificial-owl.com>
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

namespace OCP\FullTextSearch\Model;

/**
 * @since 16.0.0
 *
 * Interface ISearchOption
 *
 */
interface ISearchOption {


	/**
	 * @since 16.0.0
	 */
	public const CHECKBOX = 'checkbox';

	/**
	 * @since 16.0.0
	 */
	public const INPUT = 'input';

	/**
	 * @since 16.0.0
	 */
	public const INPUT_SMALL = 'small';


	/**
	 * Set the name/key of the option.
	 * The string should only contains alphanumerical chars and underscore.
	 * The key can be retrieve when using ISearchRequest::getOption
	 *
	 * @see ISearchRequest::getOption
	 *
	 * @since 16.0.0
	 *
	 * @param string $name
	 *
	 * @return ISearchOption
	 */
	public function setName(string $name): ISearchOption;

	/**
	 * Get the name/key of the option.
	 *
	 * @since 16.0.0
	 *
	 * @return string
	 */
	public function getName(): string;


	/**
	 * Set the title/display name of the option.
	 *
	 * @since 16.0.0
	 *
	 * @param string $title
	 *
	 * @return ISearchOption
	 */
	public function setTitle(string $title): ISearchOption;

	/**
	 * Get the title of the option.
	 *
	 * @since 16.0.0
	 *
	 * @return string
	 */
	public function getTitle(): string;


	/**
	 * Set the type of the option.
	 * $type can be ISearchOption::CHECKBOX or ISearchOption::INPUT
	 *
	 * @since 16.0.0
	 *
	 * @param string $type
	 *
	 * @return ISearchOption
	 */
	public function setType(string $type): ISearchOption;

	/**
	 * Get the type of the option.
	 *
	 * @since 16.0.0
	 *
	 * @return string
	 */
	public function getType(): string;


	/**
	 * In case of Type is INPUT, set the size of the input field.
	 * Value can be ISearchOption::INPUT_SMALL or not defined.
	 *
	 * @since 16.0.0
	 *
	 * @param string $size
	 *
	 * @return ISearchOption
	 */
	public function setSize(string $size): ISearchOption;

	/**
	 * Get the size of the INPUT.
	 *
	 * @since 16.0.0
	 *
	 * @return string
	 */
	public function getSize(): string;


	/**
	 * In case of Type is , set the placeholder to be displayed in the input
	 * field.
	 *
	 * @since 16.0.0
	 *
	 * @param string $placeholder
	 *
	 * @return ISearchOption
	 */
	public function setPlaceholder(string $placeholder): ISearchOption;

	/**
	 * Get the placeholder.
	 *
	 * @since 16.0.0
	 *
	 * @return string
	 */
	public function getPlaceholder(): string;
}
