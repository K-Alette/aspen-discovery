<?php /** @noinspection PhpMissingFieldTypeInspection */

class MaterialsRequestUsage extends DataObject {
	public $__table = 'materials_request_usage';
	public $id;
	public $libraryId;
	public $year;
	public $month;
	public $statusId;
	public $numUsed;

	public function getUniquenessFields(): array {
		return [
			'locationId',
			'year',
			'month',
			'statusId',
		];
	}

	static function incrementStat($status, $homeLocation) : void {
		try {
			require_once ROOT_DIR . '/sys/MaterialsRequests/MaterialsRequestUsage.php';
			$materialsRequestUsage = new MaterialsRequestUsage();
			$materialsRequestUsage->year = date('Y');
			$materialsRequestUsage->month = date('n');
			$materialsRequestUsage->libraryId = $homeLocation;
			$materialsRequestUsage->statusId = $status;
			if ($materialsRequestUsage->find(true)) {
				$materialsRequestUsage->numUsed++;
				$materialsRequestUsage->update();
			} else {
				$materialsRequestUsage->numUsed = 1;
				$materialsRequestUsage->insert();
			}
		} /** @noinspection PhpUnusedLocalVariableInspection */ catch (PDOException $e) {
			//This happens if the table has not been created, ignore it
		}
	}
}