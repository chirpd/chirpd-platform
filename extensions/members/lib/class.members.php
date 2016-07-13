<?php

	function __autoload($name) {
		if(preg_match('/member$/', $name)) {
			require_once EXTENSIONS . '/members/lib/member.' . strtolower(preg_replace('/member$/', '', $name)) . '.php';
		}
	}

	Interface member {
		// Authentication
		public function login(array $credentials);
		public function logout();
		public function isLoggedIn();

		// Finding
		public static function setIdentityField(array $credentials, $simplified = true);
		public function findmemberIDFromCredentials(array $credentials);
		public function fetchmemberFromID($member_id = null);

		// Output
		public function addmemberDetailsToPageParams(array $context = null);
		public function appendLoginStatusToEventXML(array $context = null);
	}

	Abstract Class members implements member {

		protected static $member_id = 0;
		protected static $isLoggedIn = false;

		public $member = null;
		public $cookie = null;

	/*-------------------------------------------------------------------------
		Utilities:
	-------------------------------------------------------------------------*/

		public function getmemberID() {
			return self::$member_id;
		}

		public function getmember() {
			return $this->member;
		}

	/*-------------------------------------------------------------------------
		Initalise:
	-------------------------------------------------------------------------*/

		public function initialiseCookie() {
			if(is_null($this->cookie)) {
				$this->cookie = new Cookie(
					Symphony::Configuration()->get('cookie-prefix', 'members'), TWO_WEEKS, __SYM_COOKIE_PATH__, null, true
				);
			}
		}

		public function initialisememberObject($member_id = null) {
			if(is_null($this->member)) {
				$this->member = $this->fetchmemberFromID($member_id);
			}

			return $this->member;
		}

	/*-------------------------------------------------------------------------
		Finding:
	-------------------------------------------------------------------------*/

		public function fetchmemberFromID($member_id = null) {
			if(!is_null($member_id)) {
				$member = EntryManager::fetch($member_id, NULL, NULL, NULL, NULL, NULL, false, true);
				return $member[0];
			}

			else if(self::$member_id !== 0) {
				$member = EntryManager::fetch(self::$member_id, NULL, NULL, NULL, NULL, NULL, false, true);
				return $member[0];
			}

			return null;
		}

		/**
		 * Given `$needle` this function will call the active Identity field
		 * to return the entry ID, aka. member ID, of that entry matching the
		 * `$needle`.
		 *
		 * @param string $needle
		 * @return integer|null
		 */
		public function findmemberIDFromIdentity($needle = null){
			if(is_null($needle)) return null;

			$identity = extension_members::getField('identity');

			return $identity->fetchmemberIDBy($needle);
		}

	/*-------------------------------------------------------------------------
		Authentication:
	-------------------------------------------------------------------------*/

		public function logout(){
			if(is_null($this->cookie)) $this->initialiseCookie();

			$this->cookie->expire();
		}

	/*-------------------------------------------------------------------------
		Output:
	-------------------------------------------------------------------------*/

		public function addmemberDetailsToPageParams(array $context = null) {
			if(!$this->isLoggedIn()) return;

			$this->initialisememberObject();

			$context['params']['member-id'] = $this->getmemberID();

			if(!is_null(extension_members::getFieldHandle('role'))) {
				$role_data = $this->getmember()->getData(extension_members::getField('role')->get('id'));
				$role = RoleManager::fetch($role_data['role_id']);
				if($role instanceof Role) {
					$context['params']['member-role'] = $role->get('name');
				}
			}

			if(!is_null(extension_members::getFieldHandle('activation'))) {
				if($this->getmember()->getData(extension_members::getField('activation')->get('id'), true)->activated != "yes") {
					$context['params']['member-activated'] = 'no';
				}
			}
		}

		public function appendLoginStatusToEventXML(array $context = null){
			$result = new XMLElement('member-login-info');

			if($this->isLoggedIn()) {
				$result->setAttributearray(array(
					'logged-in' => 'yes',
					'id' => $this->getmemberID(),
					'result' => 'success'
				));
			}
			else {
				$result->setAttribute('logged-in','no');

				// Append error messages
				if(is_array(extension_members::$_errors) && !empty(extension_members::$_errors)) {
					foreach(extension_members::$_errors as $type => $error) {
						$result->appendChild(
							new XMLElement($type, null, array(
								'type' => $error['type'],
								'message' => $error['message'],
								'label' => General::sanitize($error['label'])
							))
						);
					}
				}

				// Append post values to simulate a real Symphony event
				if(extension_members::$_failed_login_attempt) {
					$result->setAttribute('result', 'error');

					$post_values = new XMLElement('post-values');
					$post = General::getPostData();

					// Create the post data cookie element
					if (is_array($post['fields']) && !empty($post['fields'])) {
						General::array_to_xml($post_values, $post['fields'], true);
						$result->appendChild($post_values);
					}
				}
			}

			$context['wrapper']->appendChild($result);
		}

	/*-------------------------------------------------------------------------
		Filters:
	-------------------------------------------------------------------------*/

		public function filter_LockRole(array &$context) {
			return true;
		}

		public function filter_LockActivation(array &$context) {
			return true;
		}

		public function filter_UpdatePassword(array &$context) {
			return true;
		}

		public function filter_UpdatePasswordLogin(array $context) {
			return true;
		}
	}
