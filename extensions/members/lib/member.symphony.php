<?php

	Class Symphonymember extends members {

	/*-------------------------------------------------------------------------
		Utilities:
	-------------------------------------------------------------------------*/

		/**
		 * This function determines what field instance to use based on the current
		 * $_POST data.
		 *
		 * @param array $credentials
		 * @param boolean $simplified
		 *  If true, this function assumes that the $credentials array contains a
		 *  username and email key, otherwise, it will attempt to map a value using
		 *  the field handles to a normalised username/email.
		 * @return Field
		 */
		public static function setIdentityField(array $credentials, $simplified = true) {
			if($simplified) {
				extract($credentials);
			}
			else {
				// Map POST data to simple terms
				if(isset($credentials[extension_members::getFieldHandle('identity')])) {
					$username = $credentials[extension_members::getFieldHandle('identity')];
				}

				if(isset($credentials[extension_members::getFieldHandle('email')])) {
					$email = $credentials[extension_members::getFieldHandle('email')];
				}
			}

			// Check to see if neither can be found, just return null
			if(is_null($username) && is_null($email)) {
				return null;
			}

			// If email is supplied, use the Email field
			if(!is_null($email) && (isset($email) && !empty($email))) {
				$identity_field = extension_members::getField('email');
			}
			// If username is supplied, use the Username field
			else if (!is_null($username) && (isset($username) && !empty($username))) {
				$identity_field = extension_members::getField('identity');
			}

			return $identity_field;
		}

	/*-------------------------------------------------------------------------
		Finding:
	-------------------------------------------------------------------------*/

		/**
		 * Returns an Entry object given an array of credentials
		 *
		 * @param array $credentials
		 * @return integer
		 */
		public function findmemberIDFromCredentials(array $credentials) {
			if((is_null($credentials['username']) && is_null($credentials['email']))) return null;

			$identity = Symphonymember::setIdentityField($credentials);

			if(!$identity instanceof Field) return null;

			// member from Identity
			$member_id = $identity->fetchmemberIDBy($credentials);

			// Validate against Password
			// It's expected that $password is sha1'd and salted.
			$auth = extension_members::getField('authentication');
			if(!is_null($auth)) {
				$member_id = $auth->fetchmemberIDBy($credentials, $member_id);
			}

			// No member found, can't even begin to check Activation
			// Return null
			if(is_null($member_id)) return null;

			// Check that if there's activiation, that this member is activated.
			if(!is_null(extension_members::getFieldHandle('activation'))) {
				$entry = EntryManager::fetch($member_id);

				$isActivated = $entry[0]->getData(extension_members::getField('activation')->get('id'), true)->activated == "yes";

				// If we are denying login for non activated members, lets do so now
				if(extension_members::getField('activation')->get('deny_login') == 'yes' && !$isActivated) {
					extension_members::$_errors[extension_members::getFieldHandle('activation')] = array(
						'message' => __('member is not activated.'),
						'type' => 'invalid',
						'label' => extension_members::getField('activation')->get('label')
					);

					return null;
				}

				// If the member isn't activated and a Role field doesn't exist
				// just return false.
				if(!$isActivated) {
					if(is_null(extension_members::getFieldHandle('role'))) {
						extension_members::$_errors[extension_members::getFieldHandle('activation')] = array(
							'message' => __('member is not activated.'),
							'type' => 'invalid',
							'label' => extension_members::getField('activation')->get('label')
						);
						return false;
					}
				}
			}

			return $member_id;
		}

		public function fetchmemberFromID($member_id = null) {
			$member = parent::fetchmemberFromID($member_id);

			if(is_null($member)) return null;

			// If the member isn't activated and a Role field exists, we need to override
			// the current Role with the Activation Role. This may allow members to view certain
			// things until they active their account.
			if(!is_null(extension_members::getFieldHandle('activation'))) {
				if($member->getData(extension_members::getField('activation')->get('id'), true)->activated != "yes") {
					if(!is_null(extension_members::getFieldHandle('role'))) {
						$member->setData(
							extension_members::getField('role')->get('id'),
							extension_members::getField('activation')->get('activation_role_id')
						);
					}
				}
			}

			return $member;
		}

	/*-------------------------------------------------------------------------
		Authentication:
	-------------------------------------------------------------------------*/

		/**
		 * Login function takes an associative array of fields that contain
		 * an Identity field (Email/Username) and a Password field. They keys
		 * should be the Field's `element_name`.
		 * An optional parameter, `$isHashed` refers to if the password provided
		 * is hashed already, or requires hashing prior to logging in.
		 *
		 * @param array $credentials
		 * @param boolean $isHashed
		 *  Defaults to false, which will encode the password value before attempting
		 *  to log the user in
		 *
		 * @throws Exception
		 * @return boolean
		 */
		public function login(array $credentials, $isHashed = false) {
			$username = $email = $password = null;
			$data = extension_members::$_errors = array();

			// Map POST data to simple terms
			if(isset($credentials[extension_members::getFieldHandle('identity')])) {
				$username = $credentials[extension_members::getFieldHandle('identity')];
			}

			if(isset($credentials[extension_members::getFieldHandle('email')])) {
				$email = $credentials[extension_members::getFieldHandle('email')];
			}

			// Allow login via username OR email. This normalises the $data array from the custom
			// field names to simple names for ease of use.
			if(isset($username)) {
				$data['username'] = Symphony::Database()->cleanValue($username);
			}
			else if(isset($email) && !is_null(extension_members::getFieldHandle('email'))) {
				$data['email'] = Symphony::Database()->cleanValue($email);
			}

			// Map POST data for password to `$password`
			if(isset($credentials[extension_members::getFieldHandle('authentication')])) {
				$password = $credentials[extension_members::getFieldHandle('authentication')];

				// Use normalised handles for the fields
				if(!empty($password)) {
					$data['password'] = $isHashed ? $password : extension_members::getField('authentication')->encodePassword($password);
				}
				else {
					$data['password'] = '';
				}
			}

			// Check to ensure that we actually have some data to try and log a user in with.
			if(empty($data['password']) && isset($credentials[extension_members::getFieldHandle('authentication')])) {
				extension_members::$_errors[extension_members::getFieldHandle('authentication')] = array(
					'message' => __('%s is a required field.', array(extension_members::getField('authentication')->get('label'))),
					'type' => 'missing',
					'label' => extension_members::getField('authentication')->get('label')
				);
			}

			if(isset($data['username']) && empty($data['username'])) {
				extension_members::$_errors[extension_members::getFieldHandle('identity')] = array(
					'message' => __('%s is a required field.', array(extension_members::getField('identity')->get('label'))),
					'type' => 'missing',
					'label' => extension_members::getField('identity')->get('label')
				);
			}

			if(isset($data['email']) && empty($data['email'])) {
				extension_members::$_errors[extension_members::getFieldHandle('email')] = array(
					'message' => __('%s is a required field.', array(extension_members::getField('email')->get('label'))),
					'type' => 'missing',
					'label' => extension_members::getField('email')->get('label')
				);
			}

			// If there is errors already, no point continuing, return false
			if(!empty(extension_members::$_errors)) {
				return false;
			}

			if($id = $this->findmemberIDFromCredentials($data)) {
				try{
					self::$member_id = $id;
					$this->initialiseCookie();
					$this->initialisememberObject();

					$this->cookie->set('id', $id);

					if(isset($username)) {
						$this->cookie->set('username', $data['username']);
					}
					else {
						$this->cookie->set('email', $data['email']);
					}

					$this->cookie->set('password', $data['password']);

					self::$isLoggedIn = true;

				} catch(Exception $ex){
					// Or do something else?
					throw new Exception($ex);
				}

				return true;
			}

			$this->logout();

			return false;
		}

		public function isLoggedIn() {
			if(self::$isLoggedIn) return true;

			$this->initialiseCookie();

			$data = array(
				'password' => $this->cookie->get('password')
			);

			if(!is_null($this->cookie->get('username'))) {
				$data['username'] = $this->cookie->get('username');
			}
			else {
				$data['email'] = $this->cookie->get('email');
			}

			if($id = $this->findmemberIDFromCredentials($data)) {
				self::$member_id = $id;
				self::$isLoggedIn = true;
				return true;
			}

			$this->logout();

			return false;
		}

	/*-------------------------------------------------------------------------
		Filters:
	-------------------------------------------------------------------------*/

		public function filter_LockRole(array &$context) {
			// If there is a Role field, this will force it to be the Default Role.
			if(!is_null(extension_members::getFieldHandle('role'))) {
				// Can't use `$context` as `$fields` only contains $_POST['fields']
				if(isset($_POST['id'])) {
					$member = parent::fetchmemberFromID(
						Symphony::Database()->cleanValue($_POST['id'])
					);

					if(!$member instanceof Entry) return;

					// If there is a Role set to this member, lock the `$fields` role to the same value
					$role_id = $member->getData(extension_members::getField('role')->get('id'), true)->role_id;
					$context['fields'][extension_members::getFieldHandle('role')] = $role_id;
				}
				// New member, so use the default Role
				else {
					$context['fields'][extension_members::getFieldHandle('role')] = extension_members::getField('role')->get('default_role');
				}
			}
		}

		public function filter_LockActivation(array &$context) {
			// If there is an Activation field, this will force it to be no.
			if(!is_null(extension_members::getFieldHandle('activation'))) {
				// Can't use `$context` as `$fields` only contains $_POST['fields']
				if(isset($_POST['id'])) {
					$member = parent::fetchmemberFromID(
						Symphony::Database()->cleanValue($_POST['id'])
					);

					if(!$member instanceof Entry) return;

					// Lock the `$fields` activation to the same value as what is set to the member
					$activated = $member->getData(extension_members::getField('activation')->get('id'), true)->activated;
					$context['fields'][extension_members::getFieldHandle('activation')] = $activated;
				}
				// New member, so set activation to 'no'
				else {
					$context['fields'][extension_members::getFieldHandle('activation')] = 'no';
				}
			}
		}

		/**
		 * Part 1 - Update Password
		 * If there is an Authentication field, we need to inject the 'optional'
		 * key so that it won't flag a user's password as invalid if they fail to
		 * enter it. The use of the 'optional' key will only trigger validation should
		 * they enter a value in the password field, in which it assumes the user is
		 * trying to update their password.
		 *
		 * @param array $context
		 */
		public function filter_UpdatePassword(array &$context) {
			if(!is_null(extension_members::getFieldHandle('authentication'))) {
				$context['fields'][extension_members::getFieldHandle('authentication')]['optional'] = 'yes';
			}
		}

		/**
		 * Part 2 - Update Password, logs the user in
		 * If the user changed their password, we need to login them back into the
		 * system with their new password.
		 *
		 * @param array $context
		 *
		 * @return bool
		 */
		public function filter_UpdatePasswordLogin(array $context) {
			// If the user didn't update their password, or no Identity field exists return
			if(empty($context['fields'][extension_members::getFieldHandle('authentication')]['password'])) return;

			// Handle which is the Identity field, either the member: Username or member: Email field
			$identity = is_null(extension_members::getFieldHandle('identity')) ? 'email' : 'identity';

			$this->login(array(
				extension_members::getFieldHandle($identity) => $context['entry']->getData(extension_members::getField($identity)->get('id'), true)->value,
				extension_members::getFieldHandle('authentication') => $context['fields'][extension_members::getFieldHandle('authentication')]['password']
			), false);

			if(isset($_REQUEST['redirect'])) {
				redirect($_REQUEST['redirect']);
			}
		}

	}
