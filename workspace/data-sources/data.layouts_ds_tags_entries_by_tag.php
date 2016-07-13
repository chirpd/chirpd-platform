<?php

	require_once(TOOLKIT . '/class.datasource.php');

	Class datasourcelayouts_ds_tags_entries_by_tag extends SectionDatasource {

		public $dsParamROOTELEMENT = 'layouts-ds-tags-entries-by-tag';
		public $dsParamORDER = 'desc';
		public $dsParamPAGINATERESULTS = 'no';
		public $dsParamLIMIT = '20';
		public $dsParamSTARTPAGE = '1';
		public $dsParamREDIRECTONEMPTY = 'no';
		public $dsParamREQUIREDPARAM = '$ds-tags-entries-by-tag';
		public $dsParamPARAMOUTPUT = array(
				'name'
		);
		public $dsParamSORT = 'system:id';
		public $dsParamHTMLENCODE = 'yes';
		public $dsParamASSOCIATEDENTRYCOUNTS = 'no';
		

		public $dsParamFILTERS = array(
				'id' => '{$ds-tags-entries-by-tag}',
				'222' => 'no',
		);
		

		public $dsParamINCLUDEDELEMENTS = array(
				'name: unformatted',
				'column-full-width: label: unformatted',
				'column-center: label: unformatted',
				'column-right: label: unformatted'
		);
		

		public function __construct($env=NULL, $process_params=true) {
			parent::__construct($env, $process_params);
			$this->_dependencies = array('$ds-tags-entries-by-tag');
		}

		public function about() {
			return array(
				'name' => 'Layouts: DS tags entries by tag',
				'author' => array(
					'name' => 'Jonathan Simcoe',
					'website' => 'http://atheycreek.dev',
					'email' => 'jdsimcoe@gmail.com'),
				'version' => 'Symphony 2.3.2',
				'release-date' => '2013-08-13T21:41:05+00:00'
			);
		}

		public function getSource() {
			return '11';
		}

		public function allowEditorToParse() {
			return true;
		}

		public function execute(array &$param_pool = null) {
			$result = new XMLElement($this->dsParamROOTELEMENT);

			try{
				$result = parent::execute($param_pool);
			}
			catch(FrontendPageNotFoundException $e){
				// Work around. This ensures the 404 page is displayed and
				// is not picked up by the default catch() statement below
				FrontendPageNotFoundExceptionHandler::render($e);
			}
			catch(Exception $e){
				$result->appendChild(new XMLElement('error', $e->getMessage()));
				return $result;
			}

			if($this->_force_empty_result) $result = $this->emptyXMLSet();

			return $result;
		}

	}
