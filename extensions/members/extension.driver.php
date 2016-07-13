<?php

	include_once(TOOLKIT . '/class.entrymanager.php');
	include_once(TOOLKIT . '/class.sectionmanager.php');

	include_once(EXTENSIONS . '/members/lib/class.role.php');
	include_once(EXTENSIONS . '/members/lib/class.members.php');
	include_once(EXTENSIONS . '/members/lib/member.symphony.php');

	Class extension_members extends Extension {

		/**
		 * @var integer $members_section
		 */
		private static $initialised = null;

		/**
		 * @var integer $members_section
		 */
		private static $members_section = null;

		/**
		 * @var array $member_fields
		 */
		private static $member_fields = array(
			'memberusername', 'memberemail'
		);

		/**
		 * @var array $member_events
		 */
		private static $member_events = array(
			'members_regenerate_activation_code',
			'members_activate_account',
			'members_generate_recovery_code',
			'members_reset_password'
		);

		/**
		 * Holds the current member class that is in place, for this release
		 * this will always the Symphonymember, which extends member and implements
		 * the member interface.
		 *
		 * @see getmemberDriver()
		 * @var member $member
		 */
		protected $member = null;

		/**
		 * Accessible via `getField()`
		 *
		 * @see getField()
		 * @var array $fields
		 */
		protected static $fields = array();

		/**
		 * Accessible via `getFieldHandle()`
		 *
		 * @see getFieldHandle()
		 * @var array $handles
		 */
		protected static $handles = array();

		/**
		 * Returns an associative array of errors that have occurred while
		 * logging in or preforming a members custom event. The key is the
		 * field's `element_name` and the value is the error message.
		 *
		 * @var array $_errors
		 */
		public static $_errors = array();

		/**
		 * By default this is set to `false`. If a member attempts to login
		 * but is unsuccessful, this will be `true`. Useful in the
		 * `appendLoginStatusToEventXML` function to determine whether to display
		 * `extension_members::$_errors` or not.
		 *
		 * @var boolean $failed_login_attempt
		 */
		public static $_failed_login_attempt = false;

		/**
		 * Only create a member object on the Frontend of the site.
		 * There is no need to create this in the Administration context
		 * as authenticated users are Authors and are handled by Symphony,
		 * not this extension.
		 */
		public function __construct() {
			if(!extension_members::$initialised) {
				if(class_exists('Symphony') && Symphony::Engine() instanceof Frontend) {
					Symphony::ExtensionManager()->notifymembers('Initialisemember', '/frontend/', array(
						'member' => &$this->member
					));

					if(is_null($this->member)) {
						$this->member = new Symphonymember;
					}
				}

				extension_members::$initialised = true;
			}
		}

		public function fetchNavigation(){
			return array(
				array(
					'location' 	=> __('System'),
					'name' 		=> __('member Roles'),
					'link' 		=> '/roles/'
				)
			);
		}

		public function getSubscribedDelegates(){
			return array(
				/*
					FRONTEND
				*/
				array(
					'page' => '/frontend/',
					'delegate' => 'FrontendPageResolved',
					'callback' => 'checkFrontendPagePermissions'
				),
				array(
					'page' => '/frontend/',
					'delegate' => 'FrontendParamsResolve',
					'callback' => 'addmemberDetailsToPageParams'
				),
				array(
					'page' => '/frontend/',
					'delegate' => 'FrontendProcessEvents',
					'callback' => 'appendLoginStatusToEventXML'
				),
				array(
					'page' => '/frontend/',
					'delegate' => 'EventPreSaveFilter',
					'callback' => 'checkEventPermissions'
				),
				array(
					'page' => '/frontend/',
					'delegate' => 'EventPostSaveFilter',
					'callback' => 'processPostSaveFilter'
				),
				/*
					BACKEND
				*/
				array(
					'page' => '/backend/',
					'delegate' => 'AdminPagePreGenerate',
					'callback' => 'appendAssets'
				),
				array(
					'page'		=> '/system/preferences/',
					'delegate'	=> 'AddCustomPreferenceFieldsets',
					'callback'	=> 'appendPreferences'
				),
				array(
					'page'		=> '/system/preferences/',
					'delegate'	=> 'Save',
					'callback'	=> 'savePreferences'
				),
				array(
					'page'		=> '/blueprints/events/new/',
					'delegate'	=> 'AppendEventFilter',
					'callback'	=> 'appendFilter'
				),
				array(
					'page'		=> '/blueprints/events/edit/',
					'delegate'	=> 'AppendEventFilter',
					'callback'	=> 'appendFilter'
				),
			);
		}

	/*-------------------------------------------------------------------------
		Versioning:
	-------------------------------------------------------------------------*/

		/**
		 * Sets the `cookie-prefix` of `sym-members` in the Configuration
		 * and creates all of the field's tables in the database
		 *
		 * @return boolean
		 */
		public function install(){

			Symphony::Configuration()->set('cookie-prefix', 'sym-members', 'members');
			Symphony::Configuration()->write();

			return Symphony::Database()->import("
				DROP TABLE IF EXISTS `tbl_members_roles`;
				CREATE TABLE `tbl_members_roles` (
				  `id` int(11) unsigned NOT NULL auto_increment,
				  `name` varchar(255) NOT NULL,
				  `handle` varchar(255) NOT NULL,
				  PRIMARY KEY  (`id`),
				  UNIQUE KEY `handle` (`handle`)
				) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

				INSERT INTO `tbl_members_roles` VALUES(1, 'Public', 'public');

				DROP TABLE IF EXISTS `tbl_members_roles_event_permissions`;
				CREATE TABLE `tbl_members_roles_event_permissions` (
				  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
				  `role_id` int(11) unsigned NOT NULL,
				  `event` varchar(50) NOT NULL,
				  `action` varchar(60) NOT NULL,
				  `level` smallint(1) unsigned NOT NULL DEFAULT '0',
				  PRIMARY KEY (`id`),
				  KEY `role_id` (`role_id`,`event`,`action`)
				) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

				DROP TABLE IF EXISTS `tbl_members_roles_forbidden_pages`;
				CREATE TABLE `tbl_members_roles_forbidden_pages` (
				  `id` int(11) unsigned NOT NULL auto_increment,
				  `role_id` int(11) unsigned NOT NULL,
				  `page_id` int(11) unsigned NOT NULL,
				  PRIMARY KEY  (`id`),
				  KEY `role_id` (`role_id`,`page_id`)
				) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
			");
		}

		/**
		 * Remove's all `members` Configuration values and then drops all the
		 * database tables created by the members extension
		 *
		 * @return boolean
		 */
		public function uninstall(){
			Symphony::Configuration()->remove('members');
			Symphony::Configuration()->write();

			return Symphony::Database()->query("
				DROP TABLE IF EXISTS
					`tbl_fields_memberusername`,
					`tbl_fields_memberpassword`,
					`tbl_fields_memberemail`,
					`tbl_fields_memberactivation`,
					`tbl_fields_memberrole`,
					`tbl_fields_membertimezone`,
					`tbl_members_roles`,
					`tbl_members_roles_event_permissions`,
					`tbl_members_roles_forbidden_pages`
			");
		}

		public function update($previousVersion) {
			if(version_compare($previousVersion, '1.0 Beta 3', '<')) {
				$activation_table = Symphony::Database()->fetchRow(0, "SHOW TABLES LIKE 'tbl_fields_memberactivation';");
				if(!empty($activation_table)) {
					Symphony::Database()->import("
						ALTER TABLE `tbl_fields_memberactivation` ADD `auto_login` ENUM('yes','no') NULL DEFAULT 'yes';
						ALTER TABLE `tbl_fields_memberactivation` ADD `deny_login` ENUM('yes','no') NULL DEFAULT 'yes';
					");
				}

				$password_table = Symphony::Database()->fetchRow(0, "SHOW TABLES LIKE 'tbl_fields_memberpassword';");
				if(!empty($password_table)) {
					Symphony::Database()->query("
						ALTER TABLE `tbl_fields_memberpassword` ADD `code_expiry` VARCHAR(50) NOT NULL;
					");
				}
			}

			if(version_compare($previousVersion, '1.0RC1', '<')) {
				// Move the auto_login setting from the Activation Field to the config
				$field = extension_members::getField('activation');
				if($field instanceof Field) {
					Symphony::Configuration()->set('activate-account-auto-login', $field->get('auto_login'));

					$activation_table = Symphony::Database()->fetchRow(0, "SHOW TABLES LIKE 'tbl_fields_memberactivation';");
					if(!empty($activation_table)) {
						Symphony::Database()->query("
							ALTER TABLE `tbl_fields_memberpassword` DROP `auto_login`;
						");
					}
				}

				// These are now loaded dynamically
				Symphony::Configuration()->remove('timezone', 'members');
				Symphony::Configuration()->remove('role', 'members');
				Symphony::Configuration()->remove('activation', 'members');
				Symphony::Configuration()->remove('identity', 'members');
				Symphony::Configuration()->remove('email', 'members');
				Symphony::Configuration()->remove('authentication', 'members');
				Symphony::Configuration()->write();
			}

			if(version_compare($previousVersion, '1.1 Beta 1', '<') || version_compare($previousVersion, '1.1.1RC1', '<')) {
				$tables = array();

				// For any member: Username or member: Email fields, add a handle column
				// and adjust the indexes to reflect that. Uniqueness is on the handle,
				// not the value.
				$field = extension_members::getField('identity');
				if($field instanceof fieldmemberUsername) {
					$identity_tables = Symphony::Database()->fetchCol("field_id", "SELECT `field_id` FROM `tbl_fields_memberusername`");

					if(is_array($identity_tables) && !empty($identity_tables)) {
						$tables = array_merge($tables, $identity_tables);
					}
				}

				if(is_array($tables) && !empty($tables)) foreach($tables as $field) {
					if(!Symphony::Database()->tableContainsField('tbl_entries_data_' . $field, 'handle')) {
						// Add handle field
						Symphony::Database()->query(sprintf(
							"ALTER TABLE `tbl_entries_data_%d` ADD `handle` VARCHAR(255) DEFAULT NULL",
							$field
						));

						// Populate handle field
						$rows = Symphony::Database()->fetch(sprintf(
							"SELECT `id`, `value` FROM `tbl_entries_data_%d`",
							$field
						));

						foreach($rows as $row) {
							Symphony::Database()->query(sprintf("
									UPDATE `tbl_entries_data_%d`
									SET handle = '%s'
									WHERE id = %d
								", $field, Lang::createHandle($row['value']), $row['id']
							));
						}
					}

					// Try to drop the old `username` INDEX
					try {
						Symphony::Database()->query(sprintf(
							'ALTER TABLE `tbl_entries_data_%d` DROP INDEX `username`, DROP INDEX `value`', $field
						));
					}
					catch(Exception $ex) {}

					// Create the new UNIQUE INDEX `username` on `handle`
					try {
						Symphony::Database()->query(sprintf(
							'CREATE UNIQUE INDEX `username` ON `tbl_entries_data_%d` (`handle`)', $field
						));
					}
					catch(Exception $ex) {}

					// Create an index on the `value` column
					try {
						Symphony::Database()->query(sprintf(
							'CREATE INDEX `value` ON `tbl_entries_data_%d` (`value`)', $field
						));
					}
					catch(Exception $ex) {}
				}
			}

			// So `handle` for Email fields is useless as me@example.com will become
			// the same as meex@ample.com. Reverting previous change by dropping
			// `handle` column from member: Email tables and restoring the UNIQUE KEY
			// index to the `value` column.
			if(version_compare($previousVersion, '1.1 Beta 2', '<')) {
				$tables = array();

				$field = extension_members::getField('email');
				if($field instanceof fieldmemberEmail) {
					$email_tables = Symphony::Database()->fetchCol("field_id", "SELECT `field_id` FROM `tbl_fields_memberemail`");

					if(is_array($email_tables) && !empty($email_tables)) {
						$tables = array_merge($tables, $email_tables);
					}
				}

				if(is_array($tables) && !empty($tables)) foreach($tables as $field) {
					if(Symphony::Database()->tableContainsField('tbl_entries_data_' . $field, 'handle')) {
						try {
							// Drop handle field
							Symphony::Database()->query(sprintf(
								"ALTER TABLE `tbl_entries_data_%d` DROP `handle`",
								$field
							));

							// Drop `value` index
							Symphony::Database()->query(sprintf(
								'ALTER TABLE `tbl_entries_data_%d` DROP INDEX `value`', $field
							));

							// Readd UNIQUE `value` index
							Symphony::Database()->query(sprintf(
								'CREATE UNIQUE INDEX `value` ON `tbl_entries_data_%d` (`value`)', $field
							));
						}
						catch(Exception $ex) {
							// Ignore, this may be because a user is updating directly from 1.0 and
							// never had the INDEX's created during the 1.1* betas
						}
					}
				}
			}
		}

	/*-------------------------------------------------------------------------
		Utilities:
	-------------------------------------------------------------------------*/

		public static function baseURL(){
			return SYMPHONY_URL . '/extension/members/';
		}

		/**
		 * Returns an instance of the currently logged in member, which is a `Entry` object.
		 * If there is no logged in member, this function will return `null`
		 *
		 * @return member
		 */
		public function getmemberDriver() {
			return $this->member;
		}

		/**
		 * Given a `$handle`, this function will return a value from the members
		 * configuration. Typically this is an shortcut accessor to
		 * `Symphony::Configuration()->get($handle, 'members')`. If no `$handle`
		 * is given this function will return all the configuration values for
		 * the members extension as an array.
		 *
		 * @param string $handle
		 * @return mixed
		 */
		public static function getSetting($handle = null) {
			if(is_null($handle)) return Symphony::Configuration()->get('members');

			$value = Symphony::Configuration()->get($handle, 'members');
			return ((is_numeric($value) && $value == 0) || is_null($value) || empty($value)) ? null : $value;
		}

		/**
		 * Shortcut accessor for the active members Section. This function
		 * caches the result of the `getSetting('section')`.
		 *
		 * @return integer
		 */
		public static function getmembersSection() {
			if(is_null(extension_members::$members_section)) {
				extension_members::$members_section = extension_members::getSetting('section');
			}

			return extension_members::$members_section;
		}

		/**
		 * Given a string representing a field type, return the actual field type
		 * that will be saved in Symphony. This is mainly for legacy reasons, where
		 * the members extension uses slightly different types to what is actually
		 * saved. For example, `role` is `memberrole`, `authentication` is `memberpassword`
		 * etc.
		 *
		 * @param string $type
		 *
		 * @return string
		 */
		public static function getFieldType($type) {
			switch($type) {
				case 'authentication':
					return 'memberpassword';
				case 'identity':
					return 'memberusername';
				default:
					return 'member' . $type;
			}
		}

		/**
		 * Where `$name` is one of the following values, `role`, `timezone`,
		 * `email`, `activation`, `authentication` and `identity`, this function
		 * will return a Field instance. Typically this allows extensions to access
		 * the Fields that are currently being used in the active members section.
		 *
		 * @param string $type
		 * @param integer $section_id
		 *  The Section ID to find the given `$type` in. If this isn't provided this
		 *  extension will just look for any Fields with the given `$type`
		 * @return Field|null
		 *  If `$type` is not given, or no Field was found, null will be returned.
		 */
		public static function getField($type = null, $section_id = null) {
			if(is_null($type)) return null;

			$type = self::getFieldType($type);

			// Check to see if this name has been stored in our 'static' cache
			// If it hasn't, lets go find it (for better or worse)
			if(!isset(extension_members::$fields[$type])) {
				self::initialiseField($type, $section_id);
			}

			// No field, return null
			if(!isset(extension_members::$fields[$type])) return null;

			// If it has, return it.
			return extension_members::$fields[$type];
		}

		/**
		 * Where `$name` is one of the following values, `role`, `timezone`,
		 * `email`, `activation`, `authentication` and `identity`, this function
		 * will return the Field's `element_name`. `element_name` is a handle
		 * of the Field's label, used most commonly by events in `$_POST` data.
		 * If no `$name` is given, an array of all member field handles will
		 * be returned.
		 *
		 * @param string $type
		 * @param integer $section_id
		 *  The Section ID to find the given `$type` in. If this isn't provided this
		 *  extension will just look for any Fields with the given `$type`
		 * @return string
		 */
		public static function getFieldHandle($type = null, $section_id = null) {
			$type = self::getFieldType($type);

			// Check to see if this name has been stored in our 'static' cache
			// If it hasn't, lets go find it (for better or worse)
			if(!isset(extension_members::$handles[$type])) {
				self::initialiseField($type, $section_id);
			}

			// No field, return null
			if(!isset(extension_members::$handles[$type])) return null;

			// Return the handle
			return extension_members::$handles[$type];
		}

		/**
		 * Given a `$type` and potentially `$section_id`, fetch the Field
		 * instance and populate the static `$fields` and `$handles` arrays
		 *
		 * @param string $type
		 * @param integer $section_id
		 */
		public static function initialiseField($type, $section_id = null) {
			$field = FieldManager::fetch(null, $section_id, 'ASC', 'sortorder', $type);

			if(!empty($field)) {
				$field = current($field);
				extension_members::$fields[$type] = $field;
				extension_members::$handles[$type] = $field->get('element_name');
			}
		}

		/**
		 * This function will adjust the locale for the currently logged in
		 * user if the active member section has a member: Timezone field.
		 *
		 * @param integer $member_id
		 * @return void
		 */
		public function updateSystemTimezoneOffset($member_id) {
			$timezone = extension_members::getField('timezone');

			if(!$timezone instanceof fieldmemberTimezone) return;

			$tz = $timezone->getmemberTimezone($member_id);

			if(is_null($tz)) return;

			try {
				DateTimeObj::setDefaultTimezone($tz);
			}
			catch(Exception $ex) {
				Symphony::Log()->pushToLog(__('members Timezone') . ': ' . $ex->getMessage(), $ex->getCode(), true);
			}
		}

		/**
		 * Given an array of grouped options ready for use in `Widget::Select`
		 * loop over all the options and compare the value to configuration value
		 * (as specified by `$handle`) and if it matches, set that option to true
		 *
		 * @param array $options
		 * @param string $handle
		 * @return array
		 */
		public static function setActiveTemplate(array $options, $handle) {
			$templates = explode(',', extension_members::getSetting($handle));

			foreach($options as $index => $ext) {
				foreach($ext['options'] as $key => $opt) {
					if(in_array($opt[0], $templates)) {
						$options[$index]['options'][$key][1] = true;
					}
				}
			}

			array_unshift($options, array(null,false,null));

			return $options;
		}

		/**
		 * The members extension provides a number of filters for users to add their
		 * events to do various functionality. This negates the need for custom events
		 *
		 * @uses AppendEventFilter
		 *
		 * @param $context
		 */
		public function appendFilter($context) {
			$selected = !is_array($context['selected']) ? array() : $context['selected'];

			// Add member: Lock Role filter
			$context['options'][] = array(
				'member-lock-role',
				in_array('member-lock-role', $selected),
				__('members: Lock Role')
			);

			if(!is_null(extension_members::getFieldHandle('activation')) && !is_null(extension_members::getFieldHandle('email'))) {
				// Add member: Lock Activation filter
				$context['options'][] = array(
					'member-lock-activation',
					in_array('member-lock-activation', $selected),
					__('members: Lock Activation')
				);
			}

			if(!is_null(extension_members::getFieldHandle('authentication'))) {
				// Add member: Update Password filter
				$context['options'][] = array(
					'member-update-password',
					in_array('member-update-password', $selected),
					__('members: Update Password')
				);
			}
		}

		public static function findCodeExpiry($table) {
			$default = array('1 hour' => '1 hour', '24 hours' => '24 hours');

			try {
				$used = Symphony::Database()->fetchCol('code_expiry', sprintf("
					SELECT DISTINCT(code_expiry) FROM `%s`
				", $table));

				if(is_array($used) && !empty($used)) {
					$default = array_merge($default, array_combine($used, $used));
				}
			}
			catch (DatabaseException $ex) {
				// Table doesn't exist yet, it's ok we have defaults.
			}

			return $default;
		}

		public static function fetchEmailTemplates() {
			$options = array();
			// Email Template Filter
			// @link http://symphony-cms.com/download/extensions/view/20743/
			try {
				$driver = Symphony::ExtensionManager()->getInstance('emailtemplatefilter');
				if($driver instanceof Extension) {
					$templates = $driver->getTemplates();

					$g = array('label' => __('Email Template Filter'));
					$group_options = array();

					foreach($templates as $template) {
						$group_options[] = array('etf-'.$template['id'], false, $template['name']);
					}
					$g['options'] = $group_options;

					if(!empty($g['options'])) {
						$options[] = $g;
					}
				}
			}
			catch(Exception $ex) {}

			// Email Template Manager
			// @link http://symphony-cms.com/download/extensions/view/64322/
			try {
				$handles = Symphony::ExtensionManager()->listInstalledHandles();
				if(in_array('email_template_manager', $handles)){
					if(file_exists(EXTENSIONS . '/email_template_manager/lib/class.emailtemplatemanager.php') && !class_exists("EmailTemplateManager")) {
						include_once(EXTENSIONS . '/email_template_manager/lib/class.emailtemplatemanager.php');
					}

					if(class_exists("EmailTemplateManager")){
						$templates = EmailTemplateManager::listAll();

						$g = array('label' => __('Email Template Manager'));
						$group_options = array();

						foreach($templates as $template) {
							$group_options[] = array('etm-'.$template->getHandle(), false, $template->getName());
						}
						$g['options'] = $group_options;

						if(!empty($g['options'])) {
							$options[] = $g;
						}
					}
				}
			}
			catch(Exception $ex) {}

			return $options;
		}

	/*-------------------------------------------------------------------------
		Preferences:
	-------------------------------------------------------------------------*/

		/**
		 * Allows a user to select which section they would like to use as their
		 * active members section. This allows developers to build multiple sections
		 * for migration during development.
		 *
		 * @uses AddCustomPreferenceFieldsets
		 * @todo Look at how this could be expanded so users can log into multiple sections. This is not in scope for 1.0
		 *
		 * @param array $context
		 */
		public function appendPreferences($context) {
			$fieldset = new XMLElement('fieldset');
			$fieldset->setAttribute('class', 'settings');
			$fieldset->appendChild(new XMLElement('legend', __('members')));

			$div = new XMLElement('div');
			$label = new XMLElement('label', __('Active members Section'));

			// Get the Sections that contain a member field.
			$sections = SectionManager::fetch();
			$member_sections = array();
			if(is_array($sections) && !empty($sections)) {
				foreach($sections as $section) {
					$schema = $section->fetchFieldsSchema();

					foreach($schema as $field) {
						if(!in_array($field['type'], extension_members::$member_fields)) continue;

						if(array_key_exists($section->get('id'), $member_sections)) continue;

						$member_sections[$section->get('id')] = $section->get();
					}
				}
			}

			// Build the options
			$options = array(
				array(null, false, null)
			);
			foreach($member_sections as $section_id => $section) {
				$options[] = array($section_id, ($section_id == extension_members::getmembersSection()), $section['name']);
			}

			$label->appendChild(Widget::Select('settings[members][section]', $options));
			$div->appendChild($label);

			if(count($options) == 1) {
				$div->appendChild(
					new XMLElement('p', __('A members section will at minimum contain either a member: Email or a member: Username field'), array('class' => 'help'))
				);
			}

			$fieldset->appendChild($div);

			$context['wrapper']->appendChild($fieldset);
		}

		/**
		 * Saves the member Section to the configuration
		 *
		 * @uses savePreferences
		 *
		 * @param array $context
		 * @return boolean
		 */
		public function savePreferences(array &$context){
			$settings = $context['settings'];

			// Active Section
			Symphony::Configuration()->set('section', $settings['members']['section'], 'members');

			return Symphony::Configuration()->write();
		}

	/*-------------------------------------------------------------------------
		Role Manager:
	-------------------------------------------------------------------------*/

		public function checkFrontendPagePermissions($context) {
			$isLoggedIn = false;
			$errors = array();

			// Checks $_REQUEST to see if a member Action has been requested,
			// member-action['login'] and member-action['logout']/?member-action=logout
			// are the only two supported at this stage.
			if(is_array($_REQUEST['member-action'])){
				list($action) = array_keys($_REQUEST['member-action']);
			} else {
				$action = $_REQUEST['member-action'];
			}

			// Check to see a member is already logged in.
			$isLoggedIn = $this->getmemberDriver()->isLoggedIn($errors);

			// Logout
			if(trim($action) == 'logout') {
				/**
				 * Fired just before a member is logged out (and page redirection),
				 * this delegate provides the current member ID
				 *
				 * @delegate membersPreLogout
				 * @param string $context
				 *  '/frontend/'
				 * @param integer $member_id
				 *  The member ID of the member who is about to logged out
				 */
				Symphony::ExtensionManager()->notifymembers('membersPreLogout', '/frontend/', array(
					'member_id' => $this->getmemberDriver()->getmemberID()
				));

				$this->getmemberDriver()->logout();

				// If a redirect is provided, redirect to that, otherwise return the user
				// to the index of the site. Issue #51 & #121
				if(isset($_REQUEST['redirect'])) redirect($_REQUEST['redirect']);

				redirect(URL);
			}

			// Login
			else if(trim($action) == 'login' && !is_null($_POST['fields'])) {
				// If a member is already logged in and another Login attempt is requested
				// log the member out first before trying to login with new details.
				if($isLoggedIn) {
					$this->getmemberDriver()->logout();
				}
				if($this->getmemberDriver()->login($_POST['fields'])) {
					/**
					 * Fired just after a member has successfully logged in, this delegate
					 * provides the current member ID. This delegate is fired just before
					 * the page redirection (if it is provided)
					 *
					 * @delegate membersPostLogin
					 * @param string $context
					 *  '/frontend/'
					 * @param integer $member_id
					 *  The member ID of the member who just logged in.
					 * @param Entry $member
					 *  The Entry object of the logged in member.
					 */
					Symphony::ExtensionManager()->notifymembers('membersPostLogin', '/frontend/', array(
						'member_id' => $this->getmemberDriver()->getmemberID(),
						'member' => $this->getmemberDriver()->getmember()
					));

					if(isset($_POST['redirect'])) redirect($_POST['redirect']);
				}
				else {
					self::$_failed_login_attempt = true;
				}
			}

			$this->member->initialisememberObject();

			if($isLoggedIn && $this->getmemberDriver()->getmember() instanceOf Entry) {
				$this->updateSystemTimezoneOffset($this->getmemberDriver()->getmemberID());

				if(!is_null(extension_members::getFieldHandle('role'))) {
					$role_data = $this->getmemberDriver()->getmember()->getData(extension_members::getField('role')->get('id'));
				}
			}

			// If there is no role field, or a Developer is logged in, return, as Developers
			// should be able to access every page.
			if(
				is_null(extension_members::getFieldHandle('role'))
				|| (Frontend::instance()->Author instanceof Author && Frontend::instance()->Author->isDeveloper())
			) return;

			$role_id = ($isLoggedIn) ? $role_data['role_id'] : Role::PUBLIC_ROLE;
			$role = RoleManager::fetch($role_id);

			if($role instanceof Role && !$role->canAccessPage((int)$context['page_data']['id'])) {
				// User has no access to this page, so look for a custom 403 page
				if($row = PageManager::fetchPageByType('403')) {
					$row['type'] = PageManager::fetchPageTypes($row['id']);
					$row['filelocation'] = PageManager::resolvePageFileLocation($row['path'], $row['handle']);

					$context['page_data'] = $row;
					return;
				}
				else {
					// No custom 403, just throw default 403
					GenericExceptionHandler::$enabled = true;
					throw new SymphonyErrorPage(
						__('The page you have requested has restricted access permissions.'),
						__('Forbidden'),
						'error',
						array('header' => 'HTTP/1.0 403 Forbidden')
					);
				}
			}
		}

	/*-------------------------------------------------------------------------
		Events:
	-------------------------------------------------------------------------*/

		/**
		 * Adds Javascript to the custom members events when they are viewed in the
		 * backend to enable developers to set the appropriate Email Templates for
		 * each event
		 *
		 * @uses AdminPagePreGenerate
		 *
		 * @param $context
		 */
		public function appendAssets(&$context) {
			if(class_exists('Administration')
				&& Administration::instance() instanceof Administration
				&& Administration::instance()->Page instanceof HTMLPage
			) {
				$callback = Administration::instance()->getPageCallback();

				// Event Info
				if(
					$context['oPage'] instanceof contentBlueprintsEvents &&
					$callback['context'][0] == "info" &&
					in_array($callback['context'][1], extension_members::$member_events)
				) {
					Administration::instance()->Page->addScriptToHead(URL . '/extensions/members/assets/members.events.js', 10001, false);
				}
				// Temporary fix
				else if($context['oPage'] instanceof contentPublish) {
					Administration::instance()->Page->addStylesheetToHead(URL . '/extensions/members/assets/members.publish.css', 'screen', 45);
				}
			}
		}

		/**
		 * This function will ensure that the user who has submitted the form (and
		 * hence is requesting that an event be triggered) is actually allowed to
		 * do this request.
		 * There are 2 action types, creation and editing. Creation is a simple yes/no
		 * affair, whereas editing has three levels of permission, None, Own Entries
		 * or All Entries:
		 * - None: This user can't do process this event
		 * - Own Entries: If the entry the user is trying to update is their own
		 *   determined by if the `entry_id` or, in the case of a SBL or
		 *   similar field, the `entry_id` of the linked entry matches the logged in
		 *   user's id, process the event.
		 * - All Entries: The user can update any entry in Symphony.
		 * If there are no Roles in this system, or the event is set to ignore permissions
		 * (by including a function, `ignoreRolePermissions` that returns `true`, it will
		 * immediately proceed to processing any of the Filters attached to the event
		 * before returning.
		 *
		 * @uses EventPreSaveFilter
		 *
		 * @param array $context
		 * @return null
		 */
		public function checkEventPermissions(array &$context){
			// If this system has no Roles, or the event is set to ignore role permissions
			// continue straight to processing the Filters
			if(
				is_null(extension_members::getFieldHandle('role')) ||
				(method_exists($context['event'], 'ignoreRolePermissions') && $context['event']->ignoreRolePermissions() == true)
			) {
				$this->__processEventFilters($context);
				return null;
			}

			// Prior to Symphony 2.2.2, the EventPreSaveFilter delegate doesn't
			// pass the `$entry_id`. This can cause an issue when an Event has the
			// `allow_multiple` filter set as we can't determine the correct `$entry_id`
			// This will check to see if the `$entry_id` is set, otherwise fallback
			// to the previous logic. This will mean that using `allow_multiple` will
			// not be compatible without Symphony 2.2.2 and members 1.1
			// @see https://github.com/symphonycms/members/issues/167
			if(isset($context['entry_id']) && is_numeric($context['entry_id'])) {
				$entry_id = (int)$context['entry_id'];
				$action = 'edit';
			}
			else if(isset($_POST['id']) && !empty($_POST['id'])) {
				$entry_id = (int)$_POST['id'];
				$action = 'edit';
			}
			else {
				$action = 'create';
				$entry_id = 0;
			}

			$required_level = $action == 'create' ? EventPermissions::CREATE : EventPermissions::ALL_ENTRIES;
			$role_id = Role::PUBLIC_ROLE;

			$isLoggedIn = $this->getmemberDriver()->isLoggedIn();

			if($isLoggedIn && $this->getmemberDriver()->initialisememberObject()) {
				if($this->getmemberDriver()->getmember() instanceOf Entry) {
					$required_level = EventPermissions::OWN_ENTRIES;
					$role_data = $this->getmemberDriver()->getmember()->getData(extension_members::getField('role')->get('id'));
					$role_id = $role_data['role_id'];

					if($action == 'edit' && method_exists($context['event'], 'getSource')) {
						$section_id = $context['event']->getSource();
						$isOwner = false;

						// If the event is the same section as the members section, then for `$isOwner`
						// to be true, the `$entry_id` must match the currently logged in user.
						if($section_id == extension_members::getmembersSection()) {
							// Check the logged in member is the same as the `entry_id` that is about to
							// be updated. If so the user is the Owner and can modify EventPermissions::OWN_ENTRIES
							$isOwner = ($this->getmemberDriver()->getmemberID() == $entry_id);
						}

						// If the $section_id !== members_section, check the section associations table
						// for any links to either of the member fields that my be used for linking,
						// that is the Username or Email field.
						else {
							$field_ids = array();

							// Get the ID's of the fields that may be used for Linking (Username/Email)
							if(!is_null(extension_members::getFieldHandle('identity'))) {
								$field_ids[] = extension_members::getField('identity')->get('id');
							}

							if(!is_null(extension_members::getFieldHandle('email'))) {
								$field_ids[] = extension_members::getField('email')->get('id');
							}

							// Query for the `field_id` of any linking fields that link to the members
							// section AND to one of the linking fields (Username/Email)
							$fields = Symphony::Database()->fetchCol('child_section_field_id', sprintf("
									SELECT `child_section_field_id`
									FROM `tbl_sections_association`
									WHERE `parent_section_id` = %d
									AND `child_section_id` = %d
									AND `parent_section_field_id` IN ('%s')
								",
								extension_members::getmembersSection(),
								$section_id,
								implode("','", $field_ids)
							));

							// If there was a link found, get the `relation_id`, which is the `member_id` of
							// an entry in the active members section.
							if(!empty($fields)) {
								foreach($fields as $field_id) {
									if($isOwner === true) break;
									$field = FieldManager::fetch($field_id);
									if($field instanceof Field) {
										// So we are trying to find all entries that have selected the member entry
										// to determine ownership. This check will use the `fetchAssociatedEntryIDs`
										// function, which typically works backwards, by accepting the `entry_id` (in
										// this case, our logged in member ID). This will return an array of all the
										// linked entries, so we then just check that the current entry that is going to
										// be updated is in that array
										$member_id = $field->fetchAssociatedEntryIDs($this->getmemberDriver()->getmemberID());
										$isOwner = in_array($entry_id, $member_id);
									}
								}
							}
						}

						// User is not the owner, so they can edit EventPermissions::ALL_ENTRIES
						if($isOwner === false) $required_level = EventPermissions::ALL_ENTRIES;
					}
				}
			}

			$role = RoleManager::fetch($role_id);
			$event_handle = strtolower(preg_replace('/^event/i', NULL, get_class($context['event'])));
			$success = $role->canProcessEvent($event_handle, $action, $required_level) ? true : false;

			$context['messages'][] = array(
				'permission',
				$success,
				($success === false) ? __('You are not authorised to perform this action.') : null
			);

			// Process the Filters for this event.
			$this->__processEventFilters($context);
		}

		/**
		 * We can safely assume at this stage of the process that whatever user has
		 * requested this event has permission to actually do so.
		 *
		 * @param array $context
		 */
		private function __processEventFilters(array &$context) {
			// Process the member Lock Role
			if (in_array('member-lock-role', $context['event']->eParamFILTERS)) {
				$this->getmemberDriver()->filter_LockRole($context);
			}

			// Process the member Lock Activation
			if (in_array('member-lock-activation', $context['event']->eParamFILTERS)) {
				$this->getmemberDriver()->filter_LockActivation($context);
			}

			// Process updating a member's Password
			if (in_array('member-update-password', $context['event']->eParamFILTERS)) {
				$this->getmemberDriver()->filter_UpdatePassword($context);
			}
		}

		/**
		 * Any post save behaviour
		 *
		 * @uses EventPostSaveFilter
		 *
		 * @param array $context
		 */
		public function processPostSaveFilter(array &$context) {
			// Process updating a member's Password
			if (in_array('member-update-password', $context['event']->eParamFILTERS)) {
				$this->getmemberDriver()->filter_UpdatePasswordLogin($context);
			}
		}

	/*-------------------------------------------------------------------------
		Output:
	-------------------------------------------------------------------------*/

		public function addmemberDetailsToPageParams(array $context = null) {
			$this->getmemberDriver()->addmemberDetailsToPageParams($context);
		}

		public function appendLoginStatusToEventXML(array $context = null){
			$this->getmemberDriver()->appendLoginStatusToEventXML($context);
		}

	}
