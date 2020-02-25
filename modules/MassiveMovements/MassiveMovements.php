<?php
/*+**********************************************************************************
 * The contents of this file are subject to the vtiger CRM Public License Version 1.0
 * ("License"); You may not use this file except in compliance with the License
 * The Original Code is:  vtiger CRM Open Source
 * The Initial Developer of the Original Code is vtiger.
 * Portions created by vtiger are Copyright (C) vtiger.
 * All Rights Reserved.
 ************************************************************************************/
require_once 'data/CRMEntity.php';
require_once 'data/Tracker.php';
require_once 'modules/InventoryDetails/InventoryDetails.php';

class MassiveMovements extends CRMEntity {
	public $db;
	public $log;

	public $table_name = 'vtiger_massivemovements';
	public $table_index= 'massivemovementsid';
	public $column_fields = array();

	/** Indicator if this is a custom module or standard module */
	public $IsCustomModule = true;
	public $HasDirectImageField = false;
	public $moduleIcon = array('library' => 'utility', 'containerClass' => 'slds-icon_container slds-icon-standard-account', 'class' => 'slds-icon', 'icon'=>'move');

	/**
	 * Mandatory table for supporting custom fields.
	 */
	public $customFieldTable = array('vtiger_massivemovementscf', 'massivemovementsid');

	public $object_name = 'MassiveMovements';

	public $update_product_array = array();

	/**
	 * Mandatory for Saving, Include tables related to this module.
	 */
	public $tab_name = array('vtiger_crmentity', 'vtiger_massivemovements', 'vtiger_massivemovementscf');

	/**
	 * Mandatory for Saving, Include tablename and tablekey columnname here.
	 */
	public $tab_name_index = array(
		'vtiger_crmentity' => 'crmid',
		'vtiger_massivemovements' => 'massivemovementsid',
		'vtiger_massivemovementscf'=> 'massivemovementsid',
	);

	/**
	 * Mandatory for Listing (Related listview)
	 */
	public $list_fields = array(
		'MassiveMovements No'=> array('project' => 'massivemovements_no'),
		'subject' => array('massivemovements' => 'subject'),
		'srcwhid' => array('massivemovements' => 'srcwhid'),
		'dstwhid' => array('massivemovements' => 'dstwhid'),
		'Created Time' => array('crmentity' => 'createdtime'),
		'Assigned To' => array('crmentity' => 'smownerid')
	);
	public $list_fields_name = array(
		'MassiveMovements No'=> 'massivemovements_no',
		'subject' => 'subject',
		'srcwhid' => 'srcwhid',
		'dstwhid' => 'dstwhid',
		'Created Time' => 'createdtime',
		'Total'=> 'hdnGrandTotal',
		'Assigned To' => 'assigned_user_id'
	);

	// Make the field link to detail view from list view (Fieldname)
	public $list_link_field = 'massivemovements_no';

	// For Popup listview and UI type support
	public $search_fields = array(
		'MassiveMovements No'=> array('massivemovements' => 'massivemovements_no'),
		'ctoid' => array('massivemovements' => 'ctoid'),
		'srcwhid' => array('massivemovements' => 'srcwhid'),
		'dstwhid' => array('massivemovements' => 'dstwhid'),
		'Created Time' => array('crmentity' => 'createdtime'),
	);
	public $search_fields_name = array(
		'MassiveMovements No'=> 'massivemovements_no',
		'srcwhid' => 'srcwhid',
		'dstwhid' => 'dstwhid',
		'ctoid' => 'ctoid',
		'Created Time' => 'createdtime',
	);

	// For Popup window record selection
	public $popup_fields = array('massivemovements_no');

	// Placeholder for sort fields - All the fields will be initialized for Sorting through initSortFields
	public $sortby_fields = array();

	// For Alphabetical search
	public $def_basicsearch_col = 'massivemovements_no';

	// Column value to use on detail view record text display
	public $def_detailview_recname = 'massivemovements_no';

	// Required Information for enabling Import feature
	public $required_fields = array('massivemovements_no'=>1);

	// Callback function list during Importing
	public $special_functions = array('set_import_assigned_user');

	public $default_order_by = 'createdtime';
	public $default_sort_order='DESC';
	// Used when enabling/disabling the mandatory fields for the module.
	// Refers to vtiger_field.fieldname values.
	public $mandatory_fields = array('createdtime', 'modifiedtime', 'massivemovements_no');

	public function save_module($module) {
		if ($this->HasDirectImageField) {
			$this->insertIntoAttachment($this->id, $module);
		}
		//in ajax save we should not call this function, because this will delete all the existing product values
		if (inventoryCanSaveProductLines($_REQUEST, 'MassiveMovements')) {
			//Based on the total Number of rows we will save the product relationship with this entity
			saveInventoryProductDetails($this, 'MassiveMovements');
		}

		// Update the currency id and the conversion rate for the invoice
		$update_query = 'update vtiger_massivemovements set currency_id=?, conversion_rate=? where massivemovementsid=?';
		$update_params = array($this->column_fields['currency_id'], $this->column_fields['conversion_rate'], $this->id);
		$this->db->pquery($update_query, $update_params);
	}

	public function restore($module, $id) {
		$this->db->println('> MassMovement restore');
		$this->db->startTransaction();

		$this->db->pquery('UPDATE vtiger_crmentity SET deleted=0 WHERE crmid=?', array($id));
		//Restore related entities/records
		$this->restoreRelatedRecords($module, $id);

		$this->db->completeTransaction();
		$this->db->println('< MassMovement restore');
	}

	/*Function to create records in current module.
	**This function called while importing records to this module*/
	public function createRecords($obj) {
		return createRecords($obj);
	}

	/*Function returns the record information which means whether the record is imported or not
	**This function called while importing records to this module*/
	public function importRecord($obj, $inventoryFieldData, $lineItemDetails) {
		return importRecord($obj, $inventoryFieldData, $lineItemDetails);
	}

	/*Function to return the status count of imported records in current module.
	**This function called while importing records to this module*/
	public function getImportStatusCount($obj) {
		return getImportStatusCount($obj);
	}

	public function undoLastImport($obj, $user) {
		undoLastImport($obj, $user);
	}

	/** Function to export the lead records in CSV Format
	* @param reference variable - where condition is passed when the query is executed
	* Returns Export MassiveMovements Query.
	*/
	public function create_export_query($where) {
		global $log, $current_user;
		$log->debug('> create_export_query '.$where);

		include 'include/utils/ExportUtils.php';

		//To get the Permitted fields query and the permitted fields list
		$sql = getPermittedFieldsQuery("MassiveMovements", "detail_view");
		$fields_list = getFieldsListFromQuery($sql);
		$fields_list .= getInventoryFieldsForExport($this->table_name);
		//$userNameSql = getSqlForNameInDisplayFormat(array('first_name'=>'vtiger_users.first_name', 'last_name' => 'vtiger_users.last_name'), 'Users');

		$query = "SELECT $fields_list FROM ".$this->entity_table
			.'INNER JOIN vtiger_massivemovements ON vtiger_massivemovements.massivemovementsid = vtiger_crmentity.crmid
			LEFT JOIN vtiger_massivemovementscf ON vtiger_massivemovementscf.massivemovementsid = vtiger_massivemovements.massivemovementsid
			LEFT JOIN vtiger_inventoryproductrel ON vtiger_inventoryproductrel.id = vtiger_massivemovements.massivemovementsid
			LEFT JOIN vtiger_products ON vtiger_products.productid = vtiger_inventoryproductrel.productid
			LEFT JOIN vtiger_currency_info ON vtiger_currency_info.id = vtiger_massivemovements.currency_id
			LEFT JOIN vtiger_groups ON vtiger_groups.groupid = vtiger_crmentity.smownerid
			LEFT JOIN vtiger_users ON vtiger_users.id = vtiger_crmentity.smownerid';

		$query .= $this->getNonAdminAccessControlQuery('MassiveMovements', $current_user);
		$where_auto = " vtiger_crmentity.deleted=0";

		if ($where != '') {
			$query .= " where ($where) AND ".$where_auto;
		} else {
			$query .= ' where '.$where_auto;
		}

		$log->debug('< create_export_query');
		return $query;
	}

	/**
	 * Invoked when special actions are performed on the module.
	 * @param String Module name
	 * @param String Event Type (module.postinstall, module.disabled, module.enabled, module.preuninstall)
	 */
	public function vtlib_handler($modulename, $event_type) {
		global $adb;
		require_once 'include/events/include.inc';
		include_once 'vtlib/Vtiger/Module.php';
		if ($event_type == 'module.postinstall') {
			// TODO Handle post installation actions
			$modWarehouse=Vtiger_Module::getInstance('Warehouse');
			$modMov=Vtiger_Module::getInstance('Movement');
			$modInvD=Vtiger_Module::getInstance('InventoryDetails');
			$modMMv=Vtiger_Module::getInstance('MassiveMovements');
			if ($modWarehouse) {
				$modWarehouse->setRelatedList($modMMv, 'MassiveMovements', array('ADD'), 'get_dependents_list');
			}
			if ($modInvD) {
				$field = Vtiger_Field::getInstance('related_to', $modInvD);
				$field->setRelatedModules(array('MassiveMovements'));
				$modMMv->setRelatedList($modInvD, 'InventoryDetails', array(''), 'get_dependents_list');
			}
			if ($modMov) {
				$field = Vtiger_Field::getInstance('refid', $modMov);
				$field->setRelatedModules(array('MassiveMovements'));
				$modMMv->setRelatedList($modMov, 'Movement', array(''), 'get_dependents_list');
			}

			$wfid=$adb->getUniqueID('com_vtiger_workflowtasks_entitymethod');
			$adb->query("insert into com_vtiger_workflowtasks_entitymethod
				(workflowtasks_entitymethod_id,module_name,method_name,function_path,function_name)
				values
				($wfid,'MassiveMovements','mwSrcToDstStock','modules/Movement/InventoryIncDec.php','mwSrcToDstStock')");

			$wfid=$adb->getUniqueID('com_vtiger_workflowtasks_entitymethod');
			$adb->query("insert into com_vtiger_workflowtasks_entitymethod
				(workflowtasks_entitymethod_id,module_name,method_name,function_path,function_name)
				values
				($wfid,'MassiveMovements','mwReturnStock','modules/Movement/InventoryIncDec.php','mwReturnStock')");

			$this->setModuleSeqNumber('configure', $modulename, 'MMv-', '000001');
		} elseif ($event_type == 'module.disabled') {
			// TODO Handle actions when this module is disabled.
		} elseif ($event_type == 'module.enabled') {
			// TODO Handle actions when this module is enabled.
		} elseif ($event_type == 'module.preuninstall') {
			// TODO Handle actions when this module is about to be deleted.
		} elseif ($event_type == 'module.preupdate') {
			// TODO Handle actions before this module is updated.
		} elseif ($event_type == 'module.postupdate') {
			// TODO Handle actions after this module is updated.
		}
	}

	/*
	 * Function to get the secondary query part of a report
	 * @param - $module primary module name
	 * @param - $secmodule secondary module name
	 * returns the query string formed on fetching the related data for report for secondary module
	 */
	public function generateReportsSecQuery($module, $secmodule, $queryPlanner, $type = '', $where_condition = '') {
		$query = $this->getRelationQuery($module, $secmodule, 'vtiger_massivemovements', 'massivemovementsid', $queryPlanner);
		$query .= " left join vtiger_currency_info as vtiger_currency_info$secmodule on vtiger_currency_info$secmodule.id = vtiger_massivemovements.currency_id ";
		if (($type !== 'COLUMNSTOTOTAL') || ($type == 'COLUMNSTOTOTAL' && $where_condition == 'add')) {
			$query.='left join vtiger_inventoryproductrel as vtiger_inventoryproductrelMassiveMovements on vtiger_massivemovements.massivemovementsid=vtiger_inventoryproductrelMassiveMovements.id
				left join vtiger_products as vtiger_productsMassiveMovements on vtiger_productsMassiveMovements.productid = vtiger_inventoryproductrelMassiveMovements.productid';
		}
		return $query;
	}
}
?>
