<?php

/**
 *
 * @copyright Copyright (c) 2017, Daniel Calviño Sánchez (danxuliu@gmail.com)
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
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 */

use Behat\Behat\Hook\Scope\BeforeStepScope;
use Behat\MinkExtension\Context\RawMinkContext;

/**
 * Behat context to set the actor used in sibling contexts.
 *
 * This helper context provides a step definition ("I act as XXX") to change the
 * current actor of the scenario, which makes possible to use different browser
 * sessions in the same scenario.
 *
 * Sibling contexts that want to have access to the current actor of the
 * scenario must implement the ActorAwareInterface; this can be done just by
 * using the ActorAware trait.
 *
 * Besides updating the current actor in sibling contexts the ActorContext also
 * propagates its inherited "base_url" Mink parameter to the Actors as needed.
 *
 * By default no multiplier for the find timeout is set in the Actors. However,
 * it can be customized using the "actorTimeoutMultiplier" parameter of the
 * ActorContext in "behat.yml". This parameter also affects the overall timeout
 * to start a session for an Actor before giving up.
 *
 * Every actor used in the scenarios must have a corresponding Mink session
 * declared in "behat.yml" with the same name as the actor. All used sessions
 * are stopped after each scenario is run.
 */
class ActorContext extends RawMinkContext {

	/**
	 * @var array
	 */
	private $actors;

	/**
	 * @var array
	 */
	private $sharedNotebook;

	/**
	 * @var Actor
	 */
	private $currentActor;

	/**
	 * @var float
	 */
	private $actorTimeoutMultiplier;

	/**
	 * Creates a new ActorContext.
	 *
	 * @param float $actorTimeoutMultiplier the timeout multiplier for Actor
	 *        related timeouts.
	 */
	public function __construct($actorTimeoutMultiplier = 1) {
		$this->actorTimeoutMultiplier = $actorTimeoutMultiplier;
	}

	/**
	 * Sets a Mink parameter.
	 *
	 * When the "base_url" parameter is set its value is propagated to all the
	 * Actors.
	 *
	 * @param string $name the name of the parameter.
	 * @param string $value the value of the parameter.
	 */
	public function setMinkParameter($name, $value) {
		parent::setMinkParameter($name, $value);

		if ($name === "base_url") {
			foreach ($this->actors as $actor) {
				$actor->setBaseUrl($value);
			}
		}
	}

	/**
	 * Returns the session with the given name.
	 *
	 * If the session is not started it is started before returning it; if the
	 * session fails to start (typically due to a timeout connecting with the
	 * web browser) it will be tried again up to $actorTimeoutMultiplier times
	 * in total (rounded up to the next integer) before giving up.
	 *
	 * @param string|null $sname the name of the session to get, or null for the
	 *        default session.
	 * @return \Behat\Mink\Session the session.
	 */
	public function getSession($name = null) {
		for ($i = 0; $i < ($this->actorTimeoutMultiplier - 1); $i++) {
			try {
				return parent::getSession($name);
			} catch (\Behat\Mink\Exception\DriverException $exception) {
				echo "Exception when getting " . ($name == null? "default session": "session '$name'") . ": " . $exception->getMessage() . "\n";
				echo "Trying again\n";
			}
		}

		return parent::getSession($name);
	}

	/**
	 * @BeforeScenario
	 *
	 * Initializes the Actors for the new Scenario with the default Actor.
	 *
	 * Other Actors are added (and their Mink Sessions started) only when they
	 * are used in an "I act as XXX" step.
	 */
	public function initializeActors() {
		$this->actors = [];
		$this->sharedNotebook = [];

		$this->actors["default"] = new Actor("default", $this->getSession(), $this->getMinkParameter("base_url"), $this->sharedNotebook);
		$this->actors["default"]->setFindTimeoutMultiplier($this->actorTimeoutMultiplier);

		$this->currentActor = $this->actors["default"];
	}

	/**
	 * @BeforeStep
	 */
	public function setCurrentActorInSiblingActorAwareContexts(BeforeStepScope $scope) {
		$environment = $scope->getEnvironment();

		foreach ($environment->getContexts() as $context) {
			if ($context instanceof ActorAwareInterface) {
				$context->setCurrentActor($this->currentActor);
			}
		}
	}

	/**
	 * @Given I act as :actorName
	 */
	public function iActAs($actorName) {
		if (!array_key_exists($actorName, $this->actors)) {
			$this->actors[$actorName] = new Actor($actorName, $this->getSession($actorName), $this->getMinkParameter("base_url"), $this->sharedNotebook);
			$this->actors[$actorName]->setFindTimeoutMultiplier($this->actorTimeoutMultiplier);
		}

		$this->currentActor = $this->actors[$actorName];

		// Ensure that the browser window of the actor is the one in the
		// foreground; this works around a bug in the Firefox driver of Selenium
		// and/or maybe in Firefox itself when interacting with a window in the
		// background, but also reflects better how the user would interact with
		// the browser in real life.
		$session = $this->actors[$actorName]->getSession();
		$session->switchToWindow($session->getWindowName());
	}

	/**
	 * @AfterScenario
	 *
	 * Stops all the Mink Sessions used in the last Scenario.
	 */
	public function cleanUpSessions() {
		foreach ($this->actors as $actor) {
			$actor->getSession()->stop();
		}
	}

}
