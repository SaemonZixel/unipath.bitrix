<?php

function _uni_bxcache_on(&$tree, $lv) {
	return _uni_bx_cache_on($tree, $lv);
}

function _uni_bx_cache_on(&$tree, $lv) {
	list($args, $args_types) = __uni_parseFuncArgs($tree[$lv]['name']);

	// cache_id=
	$arg1 = explode('/', str_replace('\\', '/', $args[0]));
	if (count($arg1) > 1) {
		$cache_id = array_pop($arg1);
		$cache_path = implode('/', $arg1);
	}
	elseif (strpos($args[0], '_')) {
		$cache_id = $args[0];
		$cache_path = strtok($args[0], '_');
	}
	else {
		$cache_id = $arg1[0];
		$cache_path = false;
	}
	
	// ttl=
	if (isset($args['ttl']))
		$ttl = intval($args['ttl']);
	else
		$ttl = 3600;
	
	// cache
	$cache = new CPHPCache();
// var_dump($args, $args_types, $cache_path,$cache_id, $ttl);
	$result = array('data' => null, 'metadata' => array(
		'null',
		'cache' => $cache,
		'cache_id' => $cache_id,
		'cache_path' => $cache_path,
		'ttl' => $ttl,
	));
	
	if ($ttl > 0 && $cache->InitCache($ttl, $cache_id, $cache_path)) {
		
		// пропускаем все шаги до bxcache_off()
		for ($i = $lv+1; $i < count($tree); $i++) {
			if (stripos($tree[$i]['name'], 'bxcache_off(') !== false
			|| stripos($tree[$i]['name'], 'bx_cache_off(') !== false
			|| stripos($tree[$i]['name'], 'cache_off(') !== false) {
				
				// загружаем данные в bxcache_off
				$tree[$i]['data'] = $cache->GetVars();
				$tree[$i]['metadata'][0] = gettype($tree[$i]['data']);
				
				// сообщим, что надо перепрыгнуть на него
				$result['metadata']['jump_to_lv'] = $i+1; 
				
				break;
			}
			else
				$tree[$i]['metadata'] = array('null'); // заполняем метаданные пропущенных узлов
		}
	}

	return $result;
}

function _uni_bxcache_off($tree, $lv = 0) {
	return _uni_bx_cache_off($tree, $lv);
}

function _uni_bx_cache_off($tree, $lv = 0) {

	// ищем соответствующий bxcache_on 
	for ($i = $lv; $i >= 0; $i--) {
		if (stripos($tree[$i]['name'], 'bxcache_on(') !== false
		|| stripos($tree[$i]['name'], 'bx_cache_on(') !== false
		|| stripos($tree[$i]['name'], 'cache_on(') !== false) {
			$cache = $tree[$i]['metadata']['cache'];
			$cache_id = $tree[$i]['metadata']['cache_id'];
			$cache_path = $tree[$i]['metadata']['cache_path'];
			$ttl = $tree[$i]['metadata']['ttl'];
			break;
		}
	}

	if (!isset($ttl)) {
		trigger_error('_uni_bx_cache_off: Not found start bx_cache_on step!');
	}
	
	elseif ($ttl > 0) {
		$cache->StartDataCache($ttl, $cache_id, $cache_path);
		$cache->EndDataCache($tree[$lv-1]['data']);
	}

	return $tree[$lv-1];
}

function _uni_bxcache_get($tree, $lv = 0)
{
	list($args, $args_types) = __uni_parseFuncArgs($tree[$lv]['name']);

	// (cache_id)
	$arg1 = explode('/', str_replace('\\', '/', $args[0]));
	if (count($arg1) > 1) {
		$cache_id = array_pop($arg1);
		$cache_path = implode('/', $arg1);
	}
	elseif (strpos($args[0], '_')) {
		$cache_id = $args[0];
		$cache_path = strtok($args[0], '_');
	}
	else {
		$cache_id = $arg1[0];
		$cache_path = false;
	}

	$cache = new CPHPCache();
	$result = array('data' => null, 'metadata' => array(
		'null',
		'cache' => $cache,
		'cache_id' => $cache_id,
		'cache_path' => $cache_path
	));

	if($cache->InitCache(60*60*24*365, $cache_id, $cache_path))
	{
		$result['data'] = $cache->GetVars();
		$result['metadata'][0] = gettype($result['data']);
	}

	return $result;
}

function _uni_bxsql($tree, $lv) {
	return _uni_bx_db_query($tree, $lv);
}

function _uni_bx_db_query($tree, $lv) {
	
	$result = array('data' => array(), 'metadata' => array('array'));
	
	list($args, $args_types) = __uni_parseFuncArgs($tree[$lv]['name']);
// var_dump($args, $args_types);

	global $DB;
	$strSql = $args[0];
if(!empty($GLOBALS['unipath_debug'])) var_dump("_uni_bxsql: sql=".$strSql);

	$db_res = $DB->Query($strSql);
	if(stripos($strSql, 'SELECT ') !== false)
		while($res = $db_res->Fetch()) {
			$result['data'][] = $res;
		}
	else {
		$result['data'] = $db_res->AffectedRowsCount();
		$result['metadata'][0] = gettype($result['data']);
	}
	
	return $result;
}

function _uni_bx_db($tree, $lv = 0) {
	global $DB;
	$result = array('data' => $DB->db_Conn, 'metadata' => array(gettype($DB)));
	return $result;
}

function _uni_bx($tree, $lv) {
	return array(
		'data' => null, 
		'metadata' => array('null', 'cursor()' => new UniPathExtension_BX()));
}

class UniPathExtension_BX extends \UniPathExtension {

	function evalute($tree_node) {
		
		// cache_on()
		if (stripos($tree_node['name'], 'cache_on(') !== false) {
			$result = _uni_bx_cache_on($this->tree, $this->lv);
			$result['metadata']['cursor()'] = $this;
			return $result;
		}
		
		// cache_off()
		if (stripos($tree_node['name'], 'cache_off(') !== false) {
			$result = _uni_bx_cache_off($this->tree, $this->lv);
			return $result;
		}
	
		// db
		if ($tree_node['name'] == 'db') {
			return _uni_bx_db($this->tree, $this->lv);
		}
	
		// db/query()
		/* if (stripos($tree_node['name'], 'query(') === 0) {
			return _uni_bx_db_query(array($tree_node), 0);
		} */
		
		// iblocks
		if ($tree_node['name'] == 'iblocks') {
			return array(
				'data' => null, 
				'metadata' => array('null/iblocks', 'cursor()' => $this)
			);
		}
		
		// iblocks/<code/name/ID>
		// TODO cache
		if ($this->metadata[0] == 'null/iblocks') {
			global $DB;
			$name = $DB->ForSql($tree_node['name']);
			$db_res = $DB->Query(
				"SELECT * FROM b_iblock WHERE ID = '$name' OR CODE = '$name' OR NAME = '$name' ORDER BY SORT");
			while($row = $db_res->Fetch()) {
				$iblock_info = $row;
			}
			
			$fields = array(
				array('FIELD_NAME' => 'ID', 'USER_TYPE_ID' => 'number'),
				array('FIELD_NAME' => 'TIMESTAMP_X', 'USER_TYPE_ID' => 'datetime'),
				array('FIELD_NAME' => 'IBLOCK_TYPE_ID', 'USER_TYPE_ID' => 'string'),
				array('FIELD_NAME' => 'LID', 'USER_TYPE_ID' => 'string'),
				array('FIELD_NAME' => 'CODE', 'USER_TYPE_ID' => 'string'),
				array('FIELD_NAME' => 'API_CODE', 'USER_TYPE_ID' => 'string'),
				array('FIELD_NAME' => 'NAME', 'USER_TYPE_ID' => 'string'),
				array('FIELD_NAME' => 'ACTIVE', 'USER_TYPE_ID' => 'string'),
				array('FIELD_NAME' => 'SORT', 'USER_TYPE_ID' => 'number'),
				array('FIELD_NAME' => 'LIST_PAGE_URL', 'USER_TYPE_ID' => 'string'),
				array('FIELD_NAME' => 'DETAIL_PAGE_URL', 'USER_TYPE_ID' => 'string'),
				array('FIELD_NAME' => 'SECTION_PAGE_URL', 'USER_TYPE_ID' => 'string'),
				array('FIELD_NAME' => 'CANONICAL_PAGE_URL', 'USER_TYPE_ID' => 'string'),
				array('FIELD_NAME' => 'PICTURE', 'USER_TYPE_ID' => 'file'),
				array('FIELD_NAME' => 'DESCRIPTION', 'USER_TYPE_ID' => 'string'),
				array('FIELD_NAME' => 'DESCRIPTION_TYPE', 'USER_TYPE_ID' => 'string'),
				array('FIELD_NAME' => 'RSS_TTL', 'USER_TYPE_ID' => 'number'),
				array('FIELD_NAME' => 'RSS_ACTIVE', 'USER_TYPE_ID' => 'string'),
				array('FIELD_NAME' => 'RSS_FILE_ACTIVE', 'USER_TYPE_ID' => 'string'),
				array('FIELD_NAME' => 'RSS_FILE_LIMIT', 'USER_TYPE_ID' => 'number'),
				array('FIELD_NAME' => 'RSS_FILE_DAYS', 'USER_TYPE_ID' => 'number'),
				array('FIELD_NAME' => 'RSS_YANDEX_ACTIVE', 'USER_TYPE_ID' => 'string'),
				array('FIELD_NAME' => 'XML_ID', 'USER_TYPE_ID' => 'string'),
				array('FIELD_NAME' => 'TMP_ID', 'USER_TYPE_ID' => 'string'),
				array('FIELD_NAME' => 'INDEX_ELEMENT', 'USER_TYPE_ID' => 'string'),
				array('FIELD_NAME' => 'INDEX_SECTION', 'USER_TYPE_ID' => 'string'),
				array('FIELD_NAME' => 'WORKFLOW', 'USER_TYPE_ID' => 'string'),
				array('FIELD_NAME' => 'BIZPROC', 'USER_TYPE_ID' => 'string'),
				array('FIELD_NAME' => 'SECTION_CHOOSER', 'USER_TYPE_ID' => 'string'),
				array('FIELD_NAME' => 'LIST_MODE', 'USER_TYPE_ID' => 'string'),
				array('FIELD_NAME' => 'RIGHTS_MODE', 'USER_TYPE_ID' => 'string'),
				array('FIELD_NAME' => 'SECTION_PROPERTY', 'USER_TYPE_ID' => 'string'),
				array('FIELD_NAME' => 'PROPERTY_INDEX', 'USER_TYPE_ID' => 'string'),
				array('FIELD_NAME' => 'VERSION', 'USER_TYPE_ID' => 'number'),
				array('FIELD_NAME' => 'LAST_CONV_ELEMENT', 'USER_TYPE_ID' => 'number'),
				array('FIELD_NAME' => 'SOCNET_GROUP_ID', 'USER_TYPE_ID' => 'number'),
				array('FIELD_NAME' => 'EDIT_FILE_BEFORE', 'USER_TYPE_ID' => 'string'),
				array('FIELD_NAME' => 'EDIT_FILE_AFTER', 'USER_TYPE_ID' => 'string'),
				array('FIELD_NAME' => 'SECTIONS_NAME', 'USER_TYPE_ID' => 'string'),
				array('FIELD_NAME' => 'SECTION_NAME', 'USER_TYPE_ID' => 'string'),
				array('FIELD_NAME' => 'ELEMENTS_NAME', 'USER_TYPE_ID' => 'string'),
				array('FIELD_NAME' => 'ELEMENT_NAME', 'USER_TYPE_ID' => 'string'),
			);
			
			return array('data' => $fields[0]['FIELD_NAME'], 
				'metadata' => array(
					'string/iblock', 
					'iblock_info' => $iblock_info,
					'fields' => $fields,
					'cursor()' => $this
				));
		}
		
		// iblocks/<code/name/ID>/sections[]
		if ($this->metadata[0] == 'string/iblock' and strpos($tree_node['name'], 'sections') === 0) {
			// построим фильтр
			if (!empty($tree_node['filter'])) {
				$expr = $tree_node['filter']['start_expr'];
				$filter = $tree_node['filter'];
				while($expr && isset($filter[$expr])) {
				
					// left
					if(isset($filter[$expr]['left']))
					switch($filter[$expr]['left_type']) {
						case 'name':
							$left = $filter[$expr]['left'];
							break;
						case 'expr':
							$left = $filter[$filter[$expr]['left']]['sql'];
							break;
						case 'string':
						case 'number':
						default:
							$left = $filter[$expr]['left'];
					}
					
					// right
					if(isset($filter[$expr]['right_type']))
					switch($filter[$expr]['right_type']) { 
						case 'name':
							$right = $filter[$expr]['right'];
							
							// NULL
							if ($right == 'null') {
								$filter[$expr]['op'] = 'IS';
								$right = 'NULL';
							}
							break;
						case 'expr':
							$right = $filter[$filter[$expr]['left']]['sql'];
							break;
						case 'string':
						case 'number':
						default:
							$right = $filter[$expr]['right'];
					}
						
					// op
					$filter[$expr]['sql'] = "{$left} {$filter[$expr]['op']} {$right}";
					
					if (!isset($filter[$expr]['next'])) break;
					else $expr = $filter[$expr]['next'];
				}
			}

			$fields = array(
				array('FIELD_NAME' => 'ID', 'TABLE_NAME' => 'b_iblock_section', 'USER_TYPE_ID' => 'number'),
				array('FIELD_NAME' => 'TIMESTAMP_X', 'USER_TYPE_ID' => 'datetime'),
				array('FIELD_NAME' => 'MODIFIED_BY', 'USER_TYPE_ID' => 'number'),
				array('FIELD_NAME' => 'DATE_CREATE', 'USER_TYPE_ID' => 'datetime'),
				array('FIELD_NAME' => 'CREATED_BY', 'USER_TYPE_ID' => 'number'),
				array('FIELD_NAME' => 'IBLOCK_ID', 'USER_TYPE_ID' => 'number'),
				array('FIELD_NAME' => 'IBLOCK_SECTION_ID', 'USER_TYPE_ID' => 'number'),
				array('FIELD_NAME' => 'ACTIVE', 'USER_TYPE_ID' => 'string'),
				array('FIELD_NAME' => 'GLOBAL_ACTIVE', 'USER_TYPE_ID' => 'string'),
				array('FIELD_NAME' => 'SORT', 'USER_TYPE_ID' => 'number'),
				array('FIELD_NAME' => 'NAME', 'USER_TYPE_ID' => 'string'),
				array('FIELD_NAME' => 'PICTURE', 'USER_TYPE_ID' => 'file'),
				array('FIELD_NAME' => 'LEFT_MARGIN', 'USER_TYPE_ID' => 'number'),
				array('FIELD_NAME' => 'RIGHT_MARGIN', 'USER_TYPE_ID' => 'number'),
				array('FIELD_NAME' => 'DEPTH_LEVEL', 'USER_TYPE_ID' => 'number'),
				array('FIELD_NAME' => 'DESCRIPTION', 'USER_TYPE_ID' => 'string'),
				array('FIELD_NAME' => 'DESCRIPTION_TYPE', 'USER_TYPE_ID' => 'string'),
				array('FIELD_NAME' => 'SEARCHABLE_CONTENT', 'USER_TYPE_ID' => 'string'),
				array('FIELD_NAME' => 'CODE', 'USER_TYPE_ID' => 'string'),
				array('FIELD_NAME' => 'XML_ID', 'USER_TYPE_ID' => 'string'),
				array('FIELD_NAME' => 'TMP_ID', 'USER_TYPE_ID' => 'string'),
				array('FIELD_NAME' => 'DETAIL_PICTURE', 'USER_TYPE_ID' => 'file'),
				array('FIELD_NAME' => 'SOCNET_GROUP_ID', 'USER_TYPE_ID' => 'number'),
			);
			
			$result = array(
				'data' => null, 
				'metadata' => array(
					'null/iblock-sections', 
					'iblock_info' => $iblock_info,
					'fields' => $fields,
					'sql_where' => isset($filter) ? $filter[$expr]['sql'] : null,
					'cursor()' => $this,
				));

			return $result;
		}
		
		// iblocks/<code/name/ID>/sections[]/order_by()
		if (strpos($tree_node['name'], 'order_by(') === 0 
		&& $this->metadata[0] == 'null/iblock-sections') {
			list($args, $args_types) = __uni_parseFuncArgs($tree_node['name']);
			
			$sql_order = array();
			foreach($args as $key => $field_or_order) {
				$field_name = is_numeric($key) ? $field_or_order : $key;
				if (is_numeric($key))
					$sql_order[] = $field_name;
				else
					$sql_order[] = $field_name . ' ' . $field_or_order;
			}
			
			return array(
				'data' => $this->data, 
				'metadata' => $this->metadata 
					+ array('sql_order' => implode(', ', $sql_order))
			);
		}

		// iblocks/<code/name/ID>/elements[]
		// TODO cache
		if ($this->metadata[0] == 'string/iblock' and strpos($tree_node['name'], 'elements') === 0) {

			$fields = array(
				array('FIELD_NAME' => 'ID', 'TABLE_NAME' => 'b_iblock_element', 'USER_TYPE_ID' => 'number'),
				array('FIELD_NAME' => 'TIMESTAMP_X', 'USER_TYPE_ID' => 'datetime'),
				array('FIELD_NAME' => 'MODIFIED_BY', 'USER_TYPE_ID' => 'number'),
				array('FIELD_NAME' => 'DATE_CREATE', 'USER_TYPE_ID' => 'datetime'),
				array('FIELD_NAME' => 'CREATED_BY', 'USER_TYPE_ID' => 'number'),
				array('FIELD_NAME' => 'IBLOCK_ID', 'USER_TYPE_ID' => 'number'),
				array('FIELD_NAME' => 'IBLOCK_SECTION_ID', 'USER_TYPE_ID' => 'number'),
				array('FIELD_NAME' => 'ACTIVE', 'USER_TYPE_ID' => 'string'),
				array('FIELD_NAME' => 'ACTIVE_FROM', 'USER_TYPE_ID' => 'datetime'),
				array('FIELD_NAME' => 'ACTIVE_TO', 'USER_TYPE_ID' => 'datetime'),
				array('FIELD_NAME' => 'SORT', 'USER_TYPE_ID' => 'number'),
				array('FIELD_NAME' => 'NAME', 'USER_TYPE_ID' => 'string'),
				array('FIELD_NAME' => 'PREVIEW_PICTURE', 'USER_TYPE_ID' => 'file'),
				array('FIELD_NAME' => 'PREVIEW_TEXT', 'USER_TYPE_ID' => 'string'),
				array('FIELD_NAME' => 'PREVIEW_TEXT_TYPE', 'USER_TYPE_ID' => 'string'),
				array('FIELD_NAME' => 'DETAIL_PICTURE', 'USER_TYPE_ID' => 'file'),
				array('FIELD_NAME' => 'DETAIL_TEXT', 'USER_TYPE_ID' => 'string'),
				array('FIELD_NAME' => 'DETAIL_TEXT_TYPE', 'USER_TYPE_ID' => 'string'),
				array('FIELD_NAME' => 'SEARCHABLE_CONTENT', 'USER_TYPE_ID' => 'string'),
				array('FIELD_NAME' => 'WF_STATUS_ID', 'USER_TYPE_ID' => 'number'),
				array('FIELD_NAME' => 'WF_PARENT_ELEMENT_ID', 'USER_TYPE_ID' => 'number'),
				array('FIELD_NAME' => 'WF_NEW', 'USER_TYPE_ID' => 'string'),
				array('FIELD_NAME' => 'WF_LOCKED_BY', 'USER_TYPE_ID' => 'number'),
				array('FIELD_NAME' => 'WF_DATE_LOCK', 'USER_TYPE_ID' => 'datetime'),
				array('FIELD_NAME' => 'WF_COMMENTS', 'USER_TYPE_ID' => 'string'),
				array('FIELD_NAME' => 'IN_SECTIONS', 'USER_TYPE_ID' => 'string'),
				array('FIELD_NAME' => 'XML_ID', 'USER_TYPE_ID' => 'string'),
				array('FIELD_NAME' => 'CODE', 'USER_TYPE_ID' => 'string'),
				array('FIELD_NAME' => 'TAGS', 'USER_TYPE_ID' => 'string'),
				array('FIELD_NAME' => 'TMP_ID', 'USER_TYPE_ID' => 'string'),
				array('FIELD_NAME' => 'WF_LAST_HISTORY_ID', 'USER_TYPE_ID' => 'number'),
				array('FIELD_NAME' => 'SHOW_COUNTER', 'USER_TYPE_ID' => 'number'),
				array('FIELD_NAME' => 'SHOW_COUNTER_START', 'USER_TYPE_ID' => 'datetime')
			);
			
			// возмём информацию о PROPERTY
			$iblock_info = isset($this->metadata['iblock_info']) ? $this->metadata['iblock_info'] : null;
			if (!empty($iblock_info)) {
				global $DB;
				$db_res = $DB->Query(
				"SELECT * FROM b_iblock_property WHERE IBLOCK_ID = {$iblock_info['ID']} ORDER BY SORT");
				while($row = $db_res->Fetch()) {
					isset($iblock_info['PROPERTES']) or $iblock_info['PROPERTES'] = array();
					$iblock_info['PROPERTES'][$row['CODE']] = $row + array(
						'FIELD_NAME' => $row['CODE'],
						'ALIAS' => 'PROPERTY_'.$row['ID'],
						'USER_TYPE_ID' => strtr($row['PROPERTY_TYPE'], array('S' => 'string', 'F' => 'file', 'N' => 'number', 'L' => 'link', 'E' => 'element'))
					);
					
// 					$fields[] =& $iblock_info['PROPERTES'][$row['CODE']];
				}
			}
			
			// построим фильтр
			$used_columns = array();
			if (!empty($tree_node['filter'])) {
				$expr = $tree_node['filter']['start_expr'];
				$filter = $tree_node['filter'];
				while($expr && isset($filter[$expr])) {
				
					// left
					if(isset($filter[$expr]['left']))
					switch($filter[$expr]['left_type']) {
						case 'name':
							$left = $filter[$expr]['left'];
							$used_columns[] = $left;
							break;
						case 'expr':
							$left = $filter[$filter[$expr]['left']]['sql'];
							break;
						case 'string':
						case 'number':
						default:
							$left = $filter[$expr]['left'];
					}
					
					// right
					if(isset($filter[$expr]['right_type']))
					switch($filter[$expr]['right_type']) { 
						case 'name':
							$right = $filter[$expr]['right'];
							
							// NULL
							if ($right == 'null') {
								$filter[$expr]['op'] = 'IS';
								$right = 'NULL';
							}
							else
								$used_columns[] = $right;
								
							break;
						case 'expr':
							$right = $filter[$filter[$expr]['left']]['sql'];
							break;
						case 'string':
						case 'number':
						default:
							$right = $filter[$expr]['right'];
					}
						
					// op
					$filter[$expr]['sql'] = "{$left} {$filter[$expr]['op']} {$right}";
					
					if (!isset($filter[$expr]['next'])) break;
					else $expr = $filter[$expr]['next'];
				}
			}
			
			// добавим LEFT JOIN если нужно
			$left_joins = array();
			$select = array('b_iblock_element.*');
			foreach($used_columns as $name)
			foreach($fields as $field) {
				if (!isset($field['VERSION'])) continue; // поле b_iblock_element таблицы
				if ($field['FIELD_NAME'] != $name and !isset($field['ALIAS']) or $field['ALIAS'] != $name) continue;
				
				if ($field['VERSION'] == 2 and $field['MULTIPLE'] == 'N') {
					$left_joins['b_iblock_element_prop_s'.$iblock_info['ID']] = "LEFT JOIN b_iblock_element_prop_s{$iblock_info['ID']} ON IBLOCK_ELEMENT_ID = b_iblock_element.ID";
					$select[] = "b_iblock_element_prop_s{$iblock_info['ID']}.{$field['ALIAS']} AS {$field['CODE']}";
				}
				else {
					$left_joins["b_iblock_element_property_{$field['CODE']}"][] = "LEFT JOIN b_iblock_element_property AS b_iblock_element_property_{$field['CODE']} ON IBLOCK_ELEMENT_ID = b_iblock_element.ID AND IBLOCK_PROPERTY_ID = {$field['ID']}";
					$select[] = "b_iblock_element_property_{$field['CODE']}.VALUE AS {$field['CODE']}";
				}
			}
			
			$result = array(
				'data' => null, 
				'metadata' => array(
					'null/iblock-elements', 
					'iblock_info' => $iblock_info,
					'fields' => $fields,
					'sql_where' => isset($filter) ? $filter[$expr]['sql'] : null,
					'sql_left_join' => $left_joins,
					'sql_columns' => $select,
					'cursor()' => $this,
				));

			return $result;
		}
		
		// iblocks/<code/name/ID>/elements[]/order_by()
		if (strpos($tree_node['name'], 'order_by(') === 0 
		&& $this->metadata[0] == 'null/iblock-elements') {
			list($args, $args_types) = __uni_parseFuncArgs($tree_node['name']);
			
			$sql_order = array();
			foreach($args as $key => $field_or_order) {
				$field_name = is_numeric($key) ? $field_or_order : $key;
				if (is_numeric($key))
					$sql_order[] = $field_name;
				else
					$sql_order[] = $field_name . ' ' . $field_or_order;
			}
			
			return array(
				'data' => $this->data, 
				'metadata' => $this->metadata 
					+ array('sql_order' => implode(', ', $sql_order))
			);
		}
		
		// hlblocks
		if ($tree_node['name'] == 'hlblocks') {
			return array(
				'data' => null, 
				'metadata' => array('null/hlblocks', 'cursor()' => $this)
			);
		}
	
		// hlblocks/<code/table_name/ID>
		if ($this->metadata[0] == 'null/hlblocks') {
			// $entity = \Bitrix\Highloadblock\HighloadBlockTable::compileEntity($tree_node['name']);
			// $entityDataClass = $entity->getDataClass();

			global $DB;
			$name = $DB->ForSql($tree_node['name']);
			$sql = "SELECT * FROM b_hlblock_entity LEFT OUTER JOIN b_user_field ON ENTITY_ID = CONCAT('HLBLOCK_', b_hlblock_entity.ID) WHERE b_hlblock_entity.ID = '$name' OR NAME = '$name' OR TABLE_NAME = '$name' ORDER BY SORT";
			if (!empty($GLOBALS['unipath_debug_sql'])) var_dump(__METHOD__.": $sql");
			
			$fields = array();
			$db_res = $DB->Query($sql);
			while($row = $db_res->Fetch()) {
				$fields[] = $row;
			}
			
			$fields[] = array('FIELD_NAME' => 'ID', 'USER_TYPE_ID' => 'number');
			
			return array('data' => $fields[0]['NAME'], 'metadata' => array(
				'string/hlblock', 
				'fields' => $fields,
				'cursor()' => $this));
		}
	
		// hlblocks/<code>/elements[]
		// hlblocks/<code>/fields
		if ($this->metadata[0] == 'string/hlblock') {
			if ($tree_node['name'] == 'fields') {
				return array(
					'data' => $this->metadata['fields'], 
					'metadata' => array('array'));
			}
			
			if (strpos($tree_node['name'], 'elements') === 0) {
				
				// построим фильтр
				if (!empty($tree_node['filter'])) {
					$expr = $tree_node['filter']['start_expr'];
					$filter = $tree_node['filter'];
					while($expr && isset($filter[$expr])) {
					
						// left
						if(isset($filter[$expr]['left']))
						switch($filter[$expr]['left_type']) {
							case 'name':
								$left = $filter[$expr]['left'];
								
								// добавляем приставку UF_ если надо
								if (strpos($left, 'UF_') === false)
								foreach($this->metadata['fields'] as $field)
									if (strpos($field['FIELD_NAME'], $left) == 3) {
										$left = $field['FIELD_NAME'];
										break;
									}
								break;
							case 'expr':
								$left = $filter[$filter[$expr]['left']]['sql'];
								break;
							case 'string':
							case 'number':
							default:
								$left = $filter[$expr]['left'];
						}
						
						// right
						if(isset($filter[$expr]['right_type']))
						switch($filter[$expr]['right_type']) { 
							case 'name':
								$right = $filter[$expr]['right'];
								
								// NULL
								if ($right == 'null') {
									$filter[$expr]['op'] = 'IS';
									$right = 'NULL';
								}
								
								// добавляем приставку UF_ если надо
								elseif (strpos($right, 'UF_') === false)
								foreach($this->metadata['fields'] as $field)
									if (strpos($field['FIELD_NAME'], $right) == 3) {
										$right = $field['FIELD_NAME'];
										break;
									}
								break;
							case 'expr':
								$right = $filter[$filter[$expr]['left']]['sql'];
								break;
							case 'string':
							case 'number':
							default:
								$right = $filter[$expr]['right'];
						}
							
						// op
						$filter[$expr]['sql'] = "{$left} {$filter[$expr]['op']} {$right}";
							
						if (!isset($filter[$expr]['next'])) break;
						else $expr = $filter[$expr]['next'];
					}
				}
				
				$result = array(
					'data' => null, 
					'metadata' => array(
						'null/hlblock-elements', 
						'fields' => $this->metadata['fields'],
						'sql_where' => isset($filter) ? $filter[$expr]['sql'] : null,
						'cursor()' => $this,
					));

				return $result;
			}
		}
		
		// hlblocks/<code>/elements[]/order_by()
		if (strpos($tree_node['name'], 'order_by(') === 0
		&& in_array($this->metadata[0], array('null/hlblock-elements', 'null/hlblock-sections'))) {
			$fields = $this->metadata['fields'];
			list($args, $args_types) = __uni_parseFuncArgs($tree_node['name']);

			$sql_order = array();
			foreach($args as $key => $field_or_order) {
				$field_name = is_numeric($key) ? $field_or_order : $key;

				// добавляем приставку UF_ если надо
				if (strpos($field_name, 'UF_') === false)
				foreach($this->metadata['fields'] as $field)
					if (strpos($field['FIELD_NAME'], $field_name) == 3) {
						$field_name = $field['FIELD_NAME'];
						break;
					}
					
				if (is_numeric($key))
					$sql_order[] = $field_name;
				else
					$sql_order[] = $field_name . ' ' . $field_or_order;
			}
			
			return array(
				'data' => isset($this->data) ? $this->data : null, 
				'metadata' => array_merge($this->metadata, array('sql_order' => implode(', ', $sql_order)))
			);
		}
	}

	function rewind() {
		$fields = $this->metadata['fields'];
		$table_name = $fields[0]['TABLE_NAME'];
		$sql_where = $this->metadata['sql_where'];
		$sql_order = isset($this->metadata['sql_order']) ? $this->metadata['sql_order'] : '';

		$sql_select = isset($this->metadata['sql_select']) ? $this->metadata['sql_select'] : array(); 
		$sql_join = isset($this->metadata['sql_left_join']) ? $this->metadata['sql_left_join'] : array(); 
		foreach($fields as $field) {
			$sql_select[] = $table_name.'.'.$field['FIELD_NAME'];
			
			if ($field['USER_TYPE_ID'] == 'file') {
				$sfx = count($sql_join);
				$sql_join[] = "LEFT JOIN b_file AS b_file_$sfx ON {$field['FIELD_NAME']} = b_file_$sfx.ID";
				$sql_select[] = "b_file_$sfx.TIMESTAMP_X AS {$field['FIELD_NAME']}___TIMESTAMP_X, b_file_$sfx.HEIGHT AS {$field['FIELD_NAME']}___HEIGHT, b_file_$sfx.WIDTH AS {$field['FIELD_NAME']}___WIDTH, b_file_$sfx.FILE_SIZE AS {$field['FIELD_NAME']}___FILE_SIZE, b_file_$sfx.CONTENT_TYPE AS {$field['FIELD_NAME']}___CONTENT_TYPE, b_file_$sfx.SUBDIR AS {$field['FIELD_NAME']}___SUBDIR, b_file_$sfx.FILE_NAME AS {$field['FIELD_NAME']}___FILE_NAME, b_file_$sfx.ORIGINAL_NAME AS {$field['FIELD_NAME']}___ORIGINAL_NAME, b_file_$sfx.DESCRIPTION AS {$field['FIELD_NAME']}___DESCRIPTION, b_file_$sfx.EXTERNAL_ID AS {$field['FIELD_NAME']}___EXTERNAL_ID";
			}
		}
		
		$sql = "SELECT "
			.implode(", ", $sql_select)
			." FROM $table_name "
			.(implode(' ', $sql_join))." WHERE "
			.(trim($sql_where) == '' ? "1" : $sql_where)
			.(empty($sql_order) ? "" : " ORDER BY $sql_order");
		if (!empty($GLOBALS['unipath_debug_sql'])) var_dump(__METHOD__.": $sql");

		global $DB;
		$this->data = $DB->Query($sql);
		$this->metadata['record_num'] = 0;
		return true;
	}

	function next($count) {
		$rsPropEnums = $this->data;
		while ($arEnum = $rsPropEnums->fetch()) {
			
			// уберём приставку UF_ у названий колонок для удобства
			$rec = array();
			foreach($arEnum as $k => $v) 
				$rec[str_replace('___', '.', strncmp($k, 'UF_', 3) == 0 ? substr($k, 3) : $k)] = $v;
				
			return array(
				'data' => array(
					$this->metadata['record_num']++ => $rec
				), 
				'metadata' => array(gettype($arEnum)));
		}
		
		return null;
	}

	function set($value, $metadata = null, $is_unset = false) {
		return false;
	}
}

// для удобства
global $bx_db;
if(isset($GLOBALS['DB'])) {
	$GLOBALS['DB']->DoConnect();
	$bx_db = new Uni('/DB/db_Conn');
}