<?php


	require_once(TOOLKIT . '/class.field.php');

	/**
	 * The Identity class is extended by fields that act as the identity
	 * fields for the members system. These fields provide unique information
	 * to identify a user uniquely in the system. At present, there are two
	 * Identity fields, member: Username and member: Email. As time goes on
	 * it is anticipated that this will extend to include other types, such
	 * as Facebook or Twitter.
	 *
	 * This class provides methods for the system to return an Entry of a
	 * member given a particular search term via the `fetchmemberBy` function.
	 */
	Abstract Class Identity extends Field {

		protected static $driver = null;

	/*-------------------------------------------------------------------------
		Definition:
	-------------------------------------------------------------------------*/

		public function __construct(){
			parent::__construct();
			$this->_required = true;
			$this->set('required', 'yes');
		}

		public function mustBeUnique() {
			return true;
		}

		public function canFilter(){
			return true;
		}

		public function allowDatasourceParamOutput(){
			return true;
		}

		public function canPrePopulate(){
			return true;
		}

	/*-------------------------------------------------------------------------
		Utilities:
	-------------------------------------------------------------------------*/

		/**
		 * Given a member ID, return member
		 *
		 * @param integer $member_id
		 * @return Entry
		 */
		public function fetchmemberFromID($member_id){
			if(!(Identity::$driver instanceof Extension)){
				Identity::$driver = Symphony::ExtensionManager()->create('members');
			}

			return Identity::$driver->getmemberDriver()->initialisememberObject($member_id);
		}

		abstract public function fetchmemberIDBy($needle);

	/*-------------------------------------------------------------------------
		Publish:
	-------------------------------------------------------------------------*/

		public function displayPublishPanel(XMLElement &$wrapper, $data = null, $error = null, $prefix = null, $postfix = null, $entry_id = null) {
			$field_id = $this->get('id');
			$handle = $this->get('element_name');

			// Identity
			$label = Widget::Label($this->get('label'));
			if(!($this->get('required') == 'yes')) $label->appendChild(new XMLElement('i', __('Optional')));

			$label->appendChild(Widget::Input(
				"fields{$prefix}[{$handle}]{$postfix}", $data['value']
			));

			// Error?
			if(!is_null($error)) {
				$wrapper->appendChild(Widget::wrapFormElementWithError($label, $error));
			}
			else {
				$wrapper->appendChild($label);
			}
		}

	/*-------------------------------------------------------------------------
		Input:
	-------------------------------------------------------------------------*/

		public function processRawFieldData($data, &$status, $simulate=false, $entry_id = null){
			$status = self::__OK__;

			if(empty($data)) return array();

			return array(
				'value' => trim($data),
				'handle' => Lang::createHandle(trim($data))
			);
		}

	/*-------------------------------------------------------------------------
		Output:
	-------------------------------------------------------------------------*/

		public function prepareTableValue($data, XMLElement $link=NULL){
			if(empty($data)) return __('None');

			return parent::prepareTableValue(array('value' => General::sanitize($data['value'])), $link);
		}

		public function getParameterPoolValue($data, $entry_id = null) {
			return $data['value'];
		}

	/*-------------------------------------------------------------------------
		Filtering:
	-------------------------------------------------------------------------*/

		public function buildDSRetrievalSQL($data, &$joins, &$where, $andOperation=false){

			$field_id = $this->get('id');

			// Filter is an regexp.
			if(self::isFilterRegex($data[0])) {
				$pattern = str_replace('regexp:', '', $data[0]);
				$joins .= " LEFT JOIN `tbl_entries_data_$field_id` AS `t$field_id` ON (`e`.`id` = `t$field_id`.entry_id) ";
				$where .= " AND (
								`t$field_id`.value REGEXP '$pattern'
								OR `t$field_id`.handle REGEXP '$pattern'
								OR `t$field_id`.entry_id REGEXP '$pattern'
							) ";
			}

			// Filter has + in it.
			else if($andOperation) {
				foreach($data as $key => $bit){
					$joins .= " LEFT JOIN `tbl_entries_data_$field_id` AS `t$field_id$key` ON (`e`.`id` = `t$field_id$key`.entry_id) ";
					$where .= " AND (
									`t$field_id$key`.value = '$bit'
									OR `t$field_id`.handle = '$bit'
									OR `t$field_id`.entry_id = '$bit'
								) ";
				}
			}

			// Normal
			else {
				if(!is_array($data)) {
					$data = array($data);
				}

				$joins .= " LEFT JOIN `tbl_entries_data_$field_id` AS `t$field_id` ON (`e`.`id` = `t$field_id`.entry_id) ";
				$where .= " AND (
								`t$field_id`.value IN ('".implode("', '", $data)."')
								OR `t$field_id`.handle IN ('".implode("', '", $data)."')
								OR `t$field_id`.entry_id IN ('".implode("', '", $data)."')
							) ";
			}

			return true;
		}

	/*-------------------------------------------------------------------------
		Events:
	-------------------------------------------------------------------------*/

		public function getExampleFormMarkup(){

			$label = Widget::Label($this->get('label'));
			$label->appendChild(Widget::Input('fields['.$this->get('element_name').']'));

			return $label;
		}

	}
