<?php
/**
 * @author Joas Schilling <coding@schilljs.com>
 * @author Lukas Reschke <lukas@statuscode.ch>
 * @author Phil Davis <phil.davis@inf.org>
 * @author Roeland Jago Douma <rullzer@owncloud.com>
 *
 * @copyright Copyright (c) 2018, ownCloud GmbH
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
 * along with this program.  If not, see <http://www.gnu.org/licenses/>
 *
 */

namespace OC\Session;

use OC\Http\CookieHelper;
use OCP\IConfig;
use OCP\IRequest;
use OCP\ISession;
use OCP\Security\ICrypto;
use OCP\Security\ISecureRandom;

/**
 * Class CryptoWrapper provides some rough basic level of additional security by
 * storing the session data in an encrypted form.
 *
 * The content of the session is encrypted using another cookie sent by the browser.
 * One should note that an adversary with access to the source code or the system
 * memory is still able to read the original session ID from the users' request.
 * This thus can not be considered a strong security measure one should consider
 * it as an additional small security obfuscation layer to comply with compliance
 * guidelines.
 *
 * TODO: Remove this in a future release with an approach such as
 * https://github.com/owncloud/core/pull/17866
 *
 * @package OC\Session
 */
class CryptoWrapper {
	const COOKIE_NAME = 'oc_sessionPassphrase';

	/** @var ISession */
	protected $session;

	/** @var \OCP\Security\ICrypto */
	protected $crypto;

	/** @var ISecureRandom */
	protected $random;

	/** @var IConfig */
	private $config;

	/** @var string */
	private $passphrase;

	/**
	 * @param IConfig $config
	 * @param ICrypto $crypto
	 * @param ISecureRandom $random
	 * @param IRequest $request
	 */
	public function __construct(IConfig $config,
								ICrypto $crypto,
								ISecureRandom $random,
								IRequest $request) {
		$this->crypto = $crypto;
		$this->config = $config;
		$this->random = $random;

		if ($request->getCookie(self::COOKIE_NAME) !== null) {
			$this->passphrase = $request->getCookie(self::COOKIE_NAME);
		} else {
			$this->passphrase = $this->random->generate(128);

			// FIXME: Required for CI
			if (!\defined('PHPUNIT_RUN')) {
				CookieHelper::setCookie(
					self::COOKIE_NAME,
					$this->passphrase, [
					CookieHelper::PATH => \OC::$WEBROOT === '' ? '/' : \OC::$WEBROOT,
					CookieHelper::SECURE => $request->getServerProtocol() === 'https',
					CookieHelper::HTTPONLY => true,
					CookieHelper::SAMESITE => CookieHelper::SAMESITE_NONE
				], CookieHelper::POLYFILL_FALLBACK);
			}
		}
	}

	/**
	 * @param ISession $session
	 * @return ISession
	 */
	public function wrapSession(ISession $session) {
		if (!($session instanceof CryptoSessionData)) {
			return new CryptoSessionData($session, $this->crypto, $this->passphrase);
		}

		return $session;
	}
}
