<?php
require_once ROOT_DIR . '/sys/WebBuilder/LibraryGrapesPage.php';
require_once ROOT_DIR . '/sys/DB/LibraryLinkedObject.php';
require_once ROOT_DIR . '/sys/WebBuilder/GrapesTemplate.php';

class GrapesPage extends DB_LibraryLinkedObject {
	public $__table = 'grapes_web_builder';
	public $id;
	public $title;
	public $urlAlias;
	public $teaser;
	public $templatesSelect;
	public $grapesGenId;
	public $templateContent;
	public $htmlData;
	public $cssData;
	private $_libraries;

	/** @var GrapesTemplate[] */
	private $_templates;

	public function getUniquenessFields(): array {
		return [
			'id',
		];
	}

	static function getObjectStructure($context = ''): array {
		$libraryList = Library::getLibraryList(!UserAccount::userHasPermission('Administer All Grapes Pages'));
		$templateList = GrapesTemplate::getTemplateList();
		require_once ROOT_DIR . '/services/WebBuilder/Templates.php';

		$structure = [
			'id' => [
				'property' => 'id',
				'type' => 'label',
				'label' => 'Id',
				'description' => 'The unique id within the database',
			],
			'title' => [
				'property' => 'title',
				'type' => 'text',
				'label' => 'Title',
				'description' => 'The title of the page',
				'size' => '40',
				'maxLength' => 100,
			],
			'urlAlias' => [
				'property' => 'urlAlias',
				'type' => 'text',
				'label' => 'URL Alias (no domain, should start with /)',
				'description' => 'The url of the page (no domain name)',
				'size' => '40',
				'maxLength' => 100,
			],
			'teaser' => [
				'property' => 'teaser',
				'type' => 'textarea',
				'label' => 'Teaser',
				'description' => 'Teaser for page content',
				'maxLength' => 512,
				'hideInLists' => true,
			],
			'templatesSelect' => [
				'property' => 'templatesSelect',
				'type' => 'enum',
				'label' => 'Templates',
				'required' => true,
				'values' => $templateList,
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
			'htmlData' => [
				'property' => 'htmlData',
				'type' => 'hidden',
				'label' => 'htmlData',
				'description' => 'html data',
				'hideInLists' => true,
			],
			'cssData' => [
				'property' => 'cssData',
				'type' => 'hidden',
				'label' => 'cssData',
				'description' => 'css data',
				'hideInLists' => true,
			],
			'grapesGenId' => [
				'property' => 'grapesGenId',
				'type' => 'hidden',
				'label' => 'grapesGenId',
				'description' => 'The page ID generated by Grapes JS',
				'hideInLists' => true,
			],
			'templateContent' => [
				'property' => 'templateContent',
				'type' => 'hidden',
				'label' => 'templateContent',
				'description' => 'The content of the template selected for the page',
				'hideInLists' => true,
			],
		];
		if ($context == 'addNew') {
			unset($structure['templateSelect']);
		}

		if ($context != 'addNew') {
			unset($structure['templateContent']);
		}

		return $structure;
	}

	public function getFormattedContents() {
		require_once ROOT_DIR . '/sys/Parsedown/AspenParsedown.php';
		$parsedown = AspenParsedown::instance();
		$parsedown->setBreaksEnabled(true);

		$tplFilePath = $this->getTplFilePath();
		if (file_exists($tplFilePath)) {
			$tplContent = file_get_contents($tplFilePath);
			return $parsedown->parse($tplContent);
		} else {
			return 'Content file not found';
		}
	}

	public function findById($id) {
		$this->id = $id;
		return $this->find(true);
	}

	public function updatePage($templateContent, $htmlData, $cssData) {
		$this->templateContent = $templateContent;
		$this->htmlData = $htmlData;
		$this->cssData = $cssData;
		return $this->update();
	}

	public function getTplFilePath() {
		$relativePath = 'code/web/interface/themes/responsive/WebBuilder/grapesjs.tpl';
		return $relativePath;
	}

	public function insert($context = '') {
		$this->lastUpdate = time();
		$ret = parent::insert();
		if ($ret !== FALSE) {
			$this->saveLibraries();
		}
		return $ret;
	}

	public function update($context = '') {
		$this->lastUpdate = time();
		$ret = parent::update();
		if ($ret !== FALSE) {
			$this->saveLibraries();
		}
		return $ret;
	}

	public function __get($name) {
		if ($name == "libraries") {
			return $this->getLibraries();
		} elseif ($name == "templateContent") {
			return $this->getTemplates();
		} else {
			return parent::__get($name);
		}
	}

	public function __set($name, $value) {
		if ($name == "libraries") {
			$this->_libraries = $value;
		} elseif ($name == "templateContent") {
			$this->_templateContent = $value;
		} else {
			parent::__set($name, $value);
		}
	}

	public function delete($useWhere = false): int {
		$ret = parent::delete($useWhere);
		if ($ret && !empty($this->id)) {
			$this->clearLibraries();
		}
		return $ret;
	}

	public function getLibraries(): ?array {
		if (!isset($this->_libraries) && $this->id) {
			$this->_libraries = [];
			$libraryLink = new LibraryGrapesPage();
			$libraryLink->grapesPageId = $this->id;
			$libraryLink->find();
			while ($libraryLink->fetch()) {
				$this->_libraries[$libraryLink->libraryId] = $libraryLink->libraryId;
			}
		}
		return $this->_libraries;
	}

	public function getTemplates() {
		if (is_null($this->_templates)) {
			$this->_templates = [];
			require_once ROOT_DIR . '/sys/WebBuilder/GrapesTemplate.php';

			/** @noinspection SqlResolve */
			$this->query("SELECT htmlData, cssData, templatesSelect FROM grapes_web_builder WHERE id=" . (int)$this->id);

			while ($this->fetch()) {
				$htmlData = $this->htmlData;
				$cssData = $this->cssData;
				$templatesSelect = $this->templatesSelect;

				// If htmlData and cssData are empty, fetch from templates table
				if (empty($htmlData) && empty($cssData) && !empty($templatesSelect)) {
					$template = new GrapesTemplate();
					$template->id = $templatesSelect;

					if ($template->find(true)) {
						$htmlData = $template->htmlData;
						$cssData = $template->cssData;
					}
				}

				$templateContent = "<style>" . $cssData . "</style>" . $htmlData;

				// Ensure _templates[$this->id] is correctly instantiated as GrapesTemplate
				$this->_templates[$this->id] = new GrapesTemplate();
				$this->_templates[$this->id]->templateContent = $templateContent;
			}
		}
		return $this->_templates;
	}


	public function saveLibraries() {
		if (isset($this->_libraries) && is_array($this->_libraries)) {
			$this->clearLibraries();

			foreach ($this->_libraries as $libraryId) {
				$libraryLink = new LibraryGrapesPage();

				$libraryLink->grapesPageId = $this->id;
				$libraryLink->libraryId = $libraryId;
				$libraryLink->insert();
			}
			unset($this->_libraries);
		}
	}

	private function clearLibraries() {
		//Delete links to the libraries
		$libraryLink = new LibraryGrapesPage();
		$libraryLink->grapesPageId = $this->id;
		return $libraryLink->delete(true);
	}


	public function canView(): bool {
		return true;
	}

	public function canDelete(): bool {
		return true;
	}

	public function canEdit(): bool {
		return false;
	}

	function getAdditionalObjectActions($existingObject): array {
		$objectActions = [];

		if ($existingObject instanceof GrapesPage) {
			$objectActions[] = [
				'text' => 'Open in Editor',
				'url' => '/services/WebBuilder/GrapesJSEditor?objectAction=edit&id=' . $existingObject->id . '&templateId=' . $existingObject->templatesSelect,
			];
		}
		if ($existingObject instanceof GrapesPage) {
			$objectActions[] = [
				'text' => 'View As Page',
				// 'url' => '/services/WebBuilder/GrapesJSEditor?objectAction=edit&id=' . $this->id . '&templateId=' . $this->templatesSelect,
				'url' => $existingObject->urlAlias,
			];
		}
		return $objectActions;
	}

	function getAdditionalListActions(): array {
		require_once ROOT_DIR . '/services/WebBuilder/GrapesPages.php';
		$objectActions = [];
		$objectActions[] = [
			'text' => 'Open in Editor',
			'url' => '/services/WebBuilder/GrapesJSEditor?objectAction=edit&id=' . $this->id . '&templateId=' . $this->templatesSelect,
		];
		$objectActions[] = [
			'text' => 'View As Page',
			'url' => $this->urlAlias,
		];


		return $objectActions;
	}
}