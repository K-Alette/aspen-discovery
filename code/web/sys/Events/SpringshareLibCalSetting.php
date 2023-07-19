<?php
require_once ROOT_DIR . '/sys/Events/LibraryEventsSetting.php';
require_once ROOT_DIR . '/sys/Events/EventsBranchMapping.php';


/**
 * Settings for Springshare - LibCal integration
 */
class SpringshareLibCalSetting extends DataObject {
	public $__table = 'springshare_libcal_settings';
	public $id;
	public $name;
	public $baseUrl;
	public $calId;
	public /** @noinspection PhpUnused */
		$clientId;
	public /** @noinspection PhpUnused */
		$clientSecret;
	public $eventsInLists;
	public $bypassAspenEventPages;
	public $username;
	public $password;

	private $_libraries;
	private $_locationMap;

	public static function getObjectStructure($context = ''): array {
		$libraryList = Library::getLibraryList(!UserAccount::userHasPermission('Administer Springshare LibCal Settings'));

		$branchMapStructure = EventsBranchMapping::getObjectStructure($context);

		$structure = [
			'id' => [
				'property' => 'id',
				'type' => 'label',
				'label' => 'Id',
				'description' => 'The unique id',
			],
			'name' => [
				'property' => 'name',
				'type' => 'text',
				'label' => 'Name',
				'description' => 'A name for the settings',
			],
			'baseUrl' => [
				'property' => 'baseUrl',
				'type' => 'url',
				'label' => 'Base URL (i.e. https://yoursite.libcal.com)',
				'description' => 'The URL for the site',
			],
			'calId' => [
				'property' => 'calId',
				'type' => 'text',
				'label' => 'Calendar IDs',
				'description' => 'Comma-delimited list of LibCal Calendar IDs. Leave blank to retrieve all public calendars.',
				'default' => '',
				'maxLength' => 255,
			],
			'clientId' => [
				'property' => 'clientId',
				'type' => 'integer',
				'label' => 'Client ID',
				'description' => 'Client ID',
			],
			'clientSecret' => [
				'property' => 'clientSecret',
				'type' => 'storedPassword',
				'label' => 'Client Secret',
				'description' => 'Client Secret',
				'maxLength' => 36,
				'hideInLists' => true,
			],
			'eventsInLists' => [
				'property' => 'eventsInLists',
				'type' => 'enum',
				'label' => 'Events in Lists',
				'description' => 'Allow/Disallow certain users to add events to lists',
				'values' => [
					'1' => 'Allow staff to add events to lists',
					'2' => 'Allow all users to add events to lists',
					'0' => 'Do not allow adding events to lists',
				],
				'default' => '1',
			],
			'bypassAspenEventPages' => [
				'property' => 'bypassAspenEventPages',
				'type' => 'checkbox',
				'label' => 'Bypass event pages in Aspen',
				'description' => 'Whether or not a user will be redirected to an Aspen event page or the page for the native event platform.',
				'default' => 0,
			],

			'libraries' => [
				'property' => 'libraries',
				'type' => 'multiSelect',
				'listStyle' => 'checkboxSimple',
				'label' => 'Libraries',
				'description' => 'Define libraries that use these settings',
				'values' => $libraryList,
				'hideInLists' => true,
			],

			'locationMappingSection' => [
				'property' => 'locationMappingSection',
				'type' => 'section',
				'label' => 'Location Mapping',
				'properties' => [
					'locationMap' => [
						'property' => 'locationMap',
						'type' => 'oneToMany',
						'label' => 'Location Map',
						'description' => 'The mapping of library location names for Aspen and events.',
						'keyThis' => 'id',
						'subObjectType' => 'EventsBranchMapping',
						'structure' => $branchMapStructure,
						'storeDb' => true,
						'sortable' => false,
						'allowEdit' => false,
						'canEdit' => false,
						'canAddNew' => false,
						'canDelete' => false,
					],
				],
			],
		];
		return $structure;
	}

	/**
	 * Override the update functionality to save related objects
	 *
	 * @see DB/DB_DataObject::update()
	 */
	public function update($context = '') {
		$ret = parent::update();
		if ($ret !== FALSE) {
			$this->saveLibraries();
			$this->saveLocationMap();
		}
		return $ret;
	}

	/**
	 * Override the insert functionality to save the related objects
	 *
	 * @see DB/DB_DataObject::insert()
	 */
	public function insert($context = '') {
		$ret = parent::insert();
		if ($ret !== FALSE) {
			$this->saveLibraries();
		}
		return $ret;
	}

	public function __get($name) {
		if ($name == "libraries") {
			return $this->getLibraries();
		}if ($name == "locationMap") {
			return $this->getLocationMap();
		}else {
			return parent::__get($name);
		}
	}

	public function __set($name, $value) {
		if ($name == "libraries") {
			$this->_libraries = $value;
		} else {
			parent::__set($name, $value);
		}
	}

	public function delete($useWhere = false) {
		$ret = parent::delete($useWhere);
		if ($ret && !empty($this->id)) {
			$this->clearLibraries();
		}
		return $ret;
	}

	public function getLibraries() {
		if (!isset($this->_libraries) && $this->id) {
			$this->_libraries = [];
			$library = new LibraryEventsSetting();
			$library->settingSource = 'springshare';
			$library->settingId = $this->id;
			$library->find();
			while ($library->fetch()) {
				$this->_libraries[$library->libraryId] = $library->libraryId;
			}
		}
		return $this->_libraries;
	}
	public function getLocationMap() {
		if (!isset($this->_locationmap)) {
			//Get the list of translation maps
			$this->_locationmap = [];
			$locationMap = new EventsBranchMapping();
			$locationMap->orderBy('id');
			$locationMap->find();
			while ($locationMap->fetch()) {
				$this->_locationMap[$locationMap->id] = clone($locationMap);
			}
		}
		return $this->_locationMap;
	}

	public function saveLibraries() {
		if (isset($this->_libraries) && is_array($this->_libraries)) {
			$this->clearLibraries();

			foreach ($this->_libraries as $libraryId) {
				$libraryEventSetting = new LibraryEventsSetting();

				$libraryEventSetting->settingSource = 'springshare';
				$libraryEventSetting->settingId = $this->id;
				$libraryEventSetting->libraryId = $libraryId;
				$libraryEventSetting->insert();
			}
			unset($this->_libraries);
		}
	}

	public function saveLocationMap() {
		if (isset($this->_locationMap)) {
			foreach ($this->_locationMap as $location) {
				$locationMap = new EventsBranchMapping();
				$locationMap->locationId = $location->locationId;
				if ($locationMap->find(true)){
					$locationMap->eventsLocation = $location->eventsLocation;
					$locationMap->update();
				}
			}
			unset($this->_locationMap);
		}
	}

	private function clearLibraries() {
		//Delete links to the libraries
		$libraryEventSetting = new LibraryEventsSetting();
		$libraryEventSetting->settingSource = 'springshare';
		$libraryEventSetting->settingId = $this->id;
		return $libraryEventSetting->delete(true);
	}
}