<?
namespace vettich\devform;

use CAdminSorting;
use CAdminList;
use CAdminResult;
use CAdminFilter;
use vettich\devform\types\_type;
use vettich\devform\data\_data;
use vettich\devform\data\orm;

/**
* show elements list on admin page
*
* @author Oleg Lenshin (Vettich)
* @var string $pageTitle
* @var string $sTableID
* @var string $navLabel
* @var CAdminSorting $sort
* @var CAdminList $list
* @var _data $datas
* @var array $params types
* @var array $hiddenParams
* @var array $dontEdit
* @var array $onHandlers
*/
class AdminList extends Object
{
	protected $pageTitle = '';
	protected $sTableID = '';
	protected $navLabel = '';
	protected $sort = null;
	protected $list = null;
	protected $datas = null;
	protected $params = array();
	protected $hiddenParams = array();
	protected $dontEdit = array('ID');
	protected $onHandlers = array();

	/**
	 * @param string $pageTitle
	 * @param string $sTableID
	 * @param boolean|array[] $arSort
	 * @param string $navLabel
	 */
	function __construct($pageTitle, $sTableID, $args)
	{
		$this->pageTitle = self::mess($pageTitle);
		$this->sTableID = $sTableID;
		$this->params = _type::createTypes($args['params']);

		if(!isset($args['buttons']['add'])) {
			$args['buttons'] = array_merge(array(
				'add' => 'buttons\newLink:#VDF_ADD#:'.str_replace(array('=', '[', ']'), array('\=', '\[', '\]'), self::getLinkEdit()),
			), (array)$args['buttons']);
		}
		$this->buttons = _type::createTypes($args['buttons']);

		$this->setSort($args);

		if(isset($args['data'])) {
			$this->datas = _data::createDatas($args['data']);
		} elseif(isset($args['dbClass'])) {
			$this->datas = _data::createDatas(new orm(array('dbClass' => $args['dbClass'])));
		}

		if(isset($args['navLabel'])) $this->navLabel = $args['navLabel'];
		if(isset($args['hiddenParams'])) $this->hiddenParams = $args['hiddenParams'];
		if(isset($args['dontEdit'])) $this->dontEdit = $args['dontEdit'];

		$this->onHandlers = self::getOnHandler($args);
		$this->list = new CAdminList($this->sTableID, $this->sort);

		if(!isset($args['isFilter']) or $args['isFilter']) {
			$filters = array(
				'find',
				'find_type',
			);
			$this->list->InitFilter($filters);
		}

		$this->doGroupActions();
		$this->doEditAction();
	}

	public static function getLinkEdit($params=array())
	{
		$p = $_GET;
		unset($p['mode']);
		$p = http_build_query($p);
		$params['back_url'] = $_SERVER['SCRIPT_NAME'].(empty($p) ? '' : '?'.$p);
		return str_replace('.php', '_edit.php', $_SERVER['SCRIPT_NAME'])
			.'?'.http_build_query($params);
	}

	function isHiddenParam($id)
	{
		return in_array($id, $this->hiddenParams);
	}

	function doGroupActions()
	{
		if(($arID = $this->list->GroupAction()))
		{
			if($_REQUEST['action_target']=='selected')
			{
				if(method_exists($post['option'], 'GetIDs'))
					$arID = $post['option']::GetIDs();
			}

			foreach($arID as $ID)
			{
				$ID = IntVal($ID);
				if($ID <= 0)
					continue;
				switch($_REQUEST['action'])
				{
					case 'delete':
						if(false !== self::onHandler($this->onHandlers, 'beforeGroupDelete', $ID, $this)) {
							$this->datas->delete('ID', $ID);
							self::onHandler($this->onHandlers, 'afterGroupDelete', $ID, $this);
						}
						break;
				}
			}
			self::onHandler($this->onHandlers, 'doGroupActions', $arID, $_REQUEST['action'], $this);
		}
	}

	function doEditAction()
	{
		if($this->list->EditAction()) {
			foreach($_REQUEST['FIELDS'] as $id => $arField) {
				$arField['ID'] = $id;
				$this->datas->saveValues($arField);
			}
		}
	}

	function setSort($args)
	{
		if(isset($args['isSort']) && !$args['isSort']) {
			$this->sort = false;
		} else {
			if(isset($args['sortDefault'])) {
				$sBy = key($this->sortDefault);
				$sOrder = current($this->sortDefault);
				if($sBy == 0) {
					$sBy = $sOrder;
					$sOrder = 'ASC';
				}
			} else {
				$sBy = key($this->params);
				$sOrder = 'ASC';
			}
			$this->sort = new CAdminSorting($this->sTableID, $sBy, $sOrder);
		}
	}

	function getHeaders()
	{
		$arHeaders = array();
		foreach ($this->params as $id => $param)
		{
			$arHeaders[] = array(
				'id' => $param->id,
				'content' => $param->title,
				// 'sort' => $param->info['sort'],
				// 'align' => $param->info['align'],
				'default' => !$this->isHiddenParam($param->id),
			);
		}
		return $arHeaders;
	}

	function getSelectedFields()
	{
		$arSelectedFields = $this->list->GetVisibleHeaderColumns();
		if (!is_array($arSelectedFields) || empty($arSelectedFields))
		{
			$arSelectedFields = array();
			foreach ($this->params as $id => $param)
			{
				if ($this->isHiddenParam($id))
					$arSelectedFields[] = $id;
			}
		}
		return $arSelectedFields;
	}

	function getDataSource($arOrder=array(), $arFilter=array(), $arSelect=array())
	{
		$params = array();
		if(!empty($arOrder)) $params['order'] = $arOrder;
		if(!empty($arFilter)) $params['filter'] = $arFilter;
		if(!empty($arSelect)) $params['select'] = $arSelect;
		if(!empty($this->datas->datas)) {
			foreach($this->datas->datas as $data) {
				if(method_exists($data, 'getList')) {
					return $data->getList($params);
				}
			}
		}
		return null;
	}

	function getOrder()
	{
		global $by, $order;
		return array($by => $order);
	}

	function getFilter()
	{
		global $find, $find_type;

		$arFilter = array();
		foreach ($this->params as $param) {
			$find_name = 'find_'.$param->id;
			if (!empty($find) && $find_type == $find_name) {
				$arFilter[$param->getFilterId()] = $find;
			} elseif (isset($GLOBALS[$find_name])) {
				$arFilter[$param->getFilterId()] = $GLOBALS[$find_name];
			}
		}

		foreach ($arFilter as $key => $value) {
			if ($value == "")
				unset($arFilter[$key]);
		}
		return $arFilter;
	}

	function getActions($row)
	{
		$arActions = array(
			'edit' => array(
				'ICON' => 'edit',
				'DEFAULT' => true,
				'TEXT' => GetMessage('VDF_LIST_EDIT'),
				'ACTION' => $this->list->ActionRedirect(self::getLinkEdit(array('ID' => $row->arRes['ID']))),
			),
			'delete' => array(
				'ICON' => 'delete',
				'DEFAULT' => true,
				'TEXT' => GetMessage('VDF_LIST_DELETE'),
				'ACTION' => 'if(confirm("'
					.GetMessage('VDF_LIST_DELETE_CONFIRM', array('#NAME#' => $row->arRes['NAME'])).'")) '
					.$this->list->ActionDoGroup($row->arRes['ID'], 'delete'),
			),
		);
		$arActions = array_merge($arActions, (array)self::onHandler($this->onHandlers, 'actionsBuild', $arActions));
		return $arActions;
	}

	function getFooter()
	{
		return array();
	}

	function getContextMenu()
	{
		$arResult = array();
		foreach($this->buttons as $button) {
			$arResult[] = array(
				'HTML' => $button->render(),
			);
		}
		return $arResult;
	}

	function displayFilter()
	{
		global $APPLICATION, $find, $find_type;

		$findFilter = array(
			'reference' => array(),
			'reference_id' => array(),
		);
		$listFilter = array();
		$filterRows = array();
		foreach ($this->params as $param)
		{
			$listFilter[$param->id] = $param->title;
			$findFilter['reference'][] = $param->title;
			$findFilter['reference_id'][] = 'find_'.$param->id;
		}

		if (!empty($listFilter))
		{
			$filter = new CAdminFilter($this->sTableID.'_filter', $listFilter);
			?>
			<form name="find_form" method="get" action="<? echo $APPLICATION->GetCurPage(); ?>">
				<? $filter->Begin(); ?>
				<? if (!empty($findFilter['reference'])): ?>
					<tr>
						<td><b><?=GetMessage('PERFMON_HIT_FIND')?>:</b></td>
						<td><input
							type="text" size="25" name="find"
							value="<? echo htmlspecialcharsbx($find) ?>"><? echo SelectBoxFromArray('find_type', $findFilter, $find_type, '', ''); ?>
						</td>
					</tr>
				<? endif; ?>
				<?
				foreach ($this->params as $param)
				{
					?><tr>
						<td><? echo $param->title ?></td>
						<td><? echo $param->renderTemplate('{content}', array('{name}' => 'find_'.$param->id)) ?></td>
					</tr><?
				}
				$filter->Buttons(array(
					'table_id' => $this->sTableID,
					'url' => $APPLICATION->GetCurPage(),
					'form' => 'find_form',
				));
				$filter->End();
				?>
			</form>
		<?
		}
	}

	/**
	* show page on display
	* @global $APPLICATION
	*/
	function render()
	{
		\CJSCore::Init(array('ajax'));
		\CJSCore::Init(array('jquery'));
		$GLOBALS['APPLICATION']->AddHeadScript('/bitrix/js/vettich.devform/script.js');
		$GLOBALS['APPLICATION']->SetAdditionalCSS('/bitrix/css/vettich.devform/style.css');

		$this->list->addHeaders($this->getHeaders());
		$select = $this->getSelectedFields();

		$dataSource = $this->getDataSource(array(), $this->getFilter(), $select);
		$data = new CAdminResult($dataSource, $this->sTableID);
		$data->NavStart();
		$this->list->NavText($data->GetNavPrint($this->navLabel));
		while ($arRes = $data->NavNext(false)) {
			$row = $this->list->AddRow($arRes['ID'], $arRes);
			foreach ($select as $fieldId) {
				$param = $this->params[$fieldId];
				if ($param) {
					$view = $param->renderView($arRes[$param->id]);
					$row->AddViewField($param->id, $view);

					if(!in_array($param->id, $this->dontEdit)) {
						$edit = $param->renderTemplate('{content}', array(
							'{id}' => "FIELDS-{$arRes[ID]}-{$param->id}",
							'{value}' => $arRes[$param->id],
							'{name}' => "FIELDS[{$arRes[ID]}][{$param->id}]",
						));
						$row->AddEditField($param->id, $edit);
					}
				}
			}
			$arActions = $this->getActions($row);
			$row->AddActions($arActions);
			self::onHandler($this->onHandlers, 'afterRow', $row);
		}

		$this->list->AddFooter($this->getFooter());
		$this->list->AddAdminContextMenu($this->getContextMenu());
		$this->list->AddGroupActionTable(array('delete'=>true));
		$this->list->CheckListMode();
		if(!!$this->pageTitle) {
			$GLOBALS['APPLICATION']->SetTitle($this->pageTitle);
		}
		global $adminPage, $adminMenu, $adminChain, $USER, $APPLICATION;
		require($_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/main/include/prolog_admin_after.php');
		$this->displayFilter();
		$this->list->DisplayList();
	}
}
