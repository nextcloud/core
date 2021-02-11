<?php

declare(strict_types=1);

/**
 * @copyright Copyright (c) 2020, Roeland Jago Douma <roeland@famdouma.nl>
 *
 * @author Christoph Wurst <christoph@winzerhof-wurst.at>
 * @author Joas Schilling <coding@schilljs.com>
 * @author Roeland Jago Douma <roeland@famdouma.nl>
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

namespace OC\Authentication\WebAuthn;

use Cose\Algorithm\Signature\ECDSA\ES256;
use Cose\Algorithm\Signature\RSA\RS256;
use Cose\Algorithms;
use GuzzleHttp\Psr7\ServerRequest;
use OC\Authentication\WebAuthn\Db\PublicKeyCredentialEntity;
use OC\Authentication\WebAuthn\Db\PublicKeyCredentialMapper;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\IConfig;
use OCP\IUser;
use Psr\Log\LoggerInterface;
use Webauthn\AttestationStatement\AttestationObjectLoader;
use Webauthn\AttestationStatement\AttestationStatementSupportManager;
use Webauthn\AttestationStatement\NoneAttestationStatementSupport;
use Webauthn\AuthenticationExtensions\ExtensionOutputCheckerHandler;
use Webauthn\AuthenticatorAssertionResponse;
use Webauthn\AuthenticatorAssertionResponseValidator;
use Webauthn\AuthenticatorAttestationResponse;
use Webauthn\AuthenticatorAttestationResponseValidator;
use Webauthn\AuthenticatorSelectionCriteria;
use Webauthn\PublicKeyCredentialCreationOptions;
use Webauthn\PublicKeyCredentialDescriptor;
use Webauthn\PublicKeyCredentialLoader;
use Webauthn\PublicKeyCredentialParameters;
use Webauthn\PublicKeyCredentialRequestOptions;
use Webauthn\PublicKeyCredentialRpEntity;
use Webauthn\PublicKeyCredentialUserEntity;
use Webauthn\TokenBinding\TokenBindingNotSupportedHandler;

class Manager {

	/** @var CredentialRepository */
	private $repository;

	/** @var PublicKeyCredentialMapper */
	private $credentialMapper;

	/** @var LoggerInterface */
	private $logger;

	/** @var IConfig */
	private $config;

	public function __construct(
		CredentialRepository $repository,
		PublicKeyCredentialMapper $credentialMapper,
		LoggerInterface $logger,
		IConfig $config
	) {
		$this->repository = $repository;
		$this->credentialMapper = $credentialMapper;
		$this->logger = $logger;
		$this->config = $config;
	}

	public function startRegistration(IUser $user, string $serverHost): PublicKeyCredentialCreationOptions {
		$rpEntity = new PublicKeyCredentialRpEntity(
			'Nextcloud', //Name
			$this->stripPort($serverHost),        //ID
			null                            //Icon
		);

		$userEntity = new PublicKeyCredentialUserEntity(
			$user->getUID(),                              //Name
			$user->getUID(),                              //ID
			$user->getDisplayName()                      //Display name
//            'https://foo.example.co/avatar/123e4567-e89b-12d3-a456-426655440000' //Icon
		);

		$challenge = random_bytes(32);

		$publicKeyCredentialParametersList = [
			new PublicKeyCredentialParameters('public-key', Algorithms::COSE_ALGORITHM_ES256),
			new PublicKeyCredentialParameters('public-key', Algorithms::COSE_ALGORITHM_RS256),
		];

		$timeout = 60000;

		$excludedPublicKeyDescriptors = [
		];

		$authenticatorSelectionCriteria = new AuthenticatorSelectionCriteria(
			null,
			false,
			AuthenticatorSelectionCriteria::USER_VERIFICATION_REQUIREMENT_DISCOURAGED
		);

		return new PublicKeyCredentialCreationOptions(
			$rpEntity,
			$userEntity,
			$challenge,
			$publicKeyCredentialParametersList,
			$timeout,
			$excludedPublicKeyDescriptors,
			$authenticatorSelectionCriteria,
			PublicKeyCredentialCreationOptions::ATTESTATION_CONVEYANCE_PREFERENCE_NONE,
			null
		);
	}

	public function finishRegister(PublicKeyCredentialCreationOptions $publicKeyCredentialCreationOptions, string $name, string $data): PublicKeyCredentialEntity {
		$tokenBindingHandler = new TokenBindingNotSupportedHandler();

		$attestationStatementSupportManager = new AttestationStatementSupportManager();
		$attestationStatementSupportManager->add(new NoneAttestationStatementSupport());

		$attestationObjectLoader = new AttestationObjectLoader($attestationStatementSupportManager);
		$publicKeyCredentialLoader = new PublicKeyCredentialLoader($attestationObjectLoader);

		// Extension Output Checker Handler
		$extensionOutputCheckerHandler = new ExtensionOutputCheckerHandler();

		// Authenticator Attestation Response Validator
		$authenticatorAttestationResponseValidator = new AuthenticatorAttestationResponseValidator(
			$attestationStatementSupportManager,
			$this->repository,
			$tokenBindingHandler,
			$extensionOutputCheckerHandler
		);

		try {
			// Load the data
			$publicKeyCredential = $publicKeyCredentialLoader->load($data);
			$response = $publicKeyCredential->getResponse();

			// Check if the response is an Authenticator Attestation Response
			if (!$response instanceof AuthenticatorAttestationResponse) {
				throw new \RuntimeException('Not an authenticator attestation response');
			}

			// Check the response against the request
			$request = ServerRequest::fromGlobals();

			$publicKeyCredentialSource = $authenticatorAttestationResponseValidator->check(
				$response,
				$publicKeyCredentialCreationOptions,
				$request);
		} catch (\Throwable $exception) {
			throw $exception;
		}

		// Persist the data
		return $this->repository->saveAndReturnCredentialSource($publicKeyCredentialSource, $name);
	}

	private function stripPort(string $serverHost): string {
		return preg_replace('/(:\d+$)/', '', $serverHost);
	}

	public function startAuthentication(string $uid, string $serverHost): PublicKeyCredentialRequestOptions {
		// List of registered PublicKeyCredentialDescriptor classes associated to the user
		$registeredPublicKeyCredentialDescriptors = array_map(function (PublicKeyCredentialEntity $entity) {
			$credential = $entity->toPublicKeyCredentialSource();
			return new PublicKeyCredentialDescriptor(
				$credential->getType(),
				$credential->getPublicKeyCredentialId()
			);
		}, $this->credentialMapper->findAllForUid($uid));

		// Public Key Credential Request Options
		return new PublicKeyCredentialRequestOptions(
			random_bytes(32),                                                    // Challenge
			60000,                                                              // Timeout
			$this->stripPort($serverHost),                                                                  // Relying Party ID
			$registeredPublicKeyCredentialDescriptors,                                  // Registered PublicKeyCredentialDescriptor classes
			AuthenticatorSelectionCriteria::USER_VERIFICATION_REQUIREMENT_DISCOURAGED
		);
	}

	public function finishAuthentication(PublicKeyCredentialRequestOptions $publicKeyCredentialRequestOptions, string $data, string $uid) {
		$attestationStatementSupportManager = new AttestationStatementSupportManager();
		$attestationStatementSupportManager->add(new NoneAttestationStatementSupport());

		$attestationObjectLoader = new AttestationObjectLoader($attestationStatementSupportManager);
		$publicKeyCredentialLoader = new PublicKeyCredentialLoader($attestationObjectLoader);

		$tokenBindingHandler = new TokenBindingNotSupportedHandler();
		$extensionOutputCheckerHandler = new ExtensionOutputCheckerHandler();
		$algorithmManager = new \Cose\Algorithm\Manager();
		$algorithmManager->add(new ES256());
		$algorithmManager->add(new RS256());

		$authenticatorAssertionResponseValidator = new AuthenticatorAssertionResponseValidator(
			$this->repository,
			$tokenBindingHandler,
			$extensionOutputCheckerHandler,
			$algorithmManager
		);

		try {
			$this->logger->debug('Loading publickey credentials from: ' . $data);

			// Load the data
			$publicKeyCredential = $publicKeyCredentialLoader->load($data);
			$response = $publicKeyCredential->getResponse();

			// Check if the response is an Authenticator Attestation Response
			if (!$response instanceof AuthenticatorAssertionResponse) {
				throw new \RuntimeException('Not an authenticator attestation response');
			}

			// Check the response against the request
			$request = ServerRequest::fromGlobals();

			$publicKeyCredentialSource = $authenticatorAssertionResponseValidator->check(
				$publicKeyCredential->getRawId(),
				$response,
				$publicKeyCredentialRequestOptions,
				$request,
				$uid
			);
		} catch (\Throwable $e) {
			throw $e;
		}



		return true;
	}

	public function deleteRegistration(IUser $user, int $id): void {
		try {
			$entry = $this->credentialMapper->findById($user->getUID(), $id);
		} catch (DoesNotExistException $e) {
			$this->logger->warning("WebAuthn device $id does not exist, can't delete it");
			return;
		}

		$this->credentialMapper->delete($entry);
	}

	public function isWebAuthnAvailable(): bool {
		if (!extension_loaded('bcmath')) {
			return false;
		}

		if (!extension_loaded('gmp')) {
			return false;
		}

		if (!$this->config->getSystemValueBool('auth.webauthn.enabled', true)) {
			return false;
		}

		return true;
	}
}
