<?php

/**
 * @file
 * Alter views query before its execution and results before rendering.
 */

use Drupal\views\Views;
use Drupal\views\ViewExecutable;
use Drupal\views\Plugin\views\query\QueryPluginBase;

use Drupal\dcl_sar\SearchAndReplaceTrait;

/**
 * Implements hook_views_query_alter().
 * 
 * Adds all text fields of given bundles to search query. 
 */
function dcl_sar_views_query_alter(ViewExecutable $view, QueryPluginBase $query) {
	// Find the view to modify 
	if ($view->id() == 'content_bulk_edit' && $view->getDisplay()->display['id'] == 'vbo_sar') {
      	$base = $view->storage->get('base_table');	//node_field_data    
		if (isset($view->exposed_data) && $view->exposed_data['sar_search']) {
			// Update where clause to include all text fields associated with bundles.
			$textFields = [];
			foreach ($query->where as &$condition_group) {
				$field = $condition_group['conditions'][0]['field'];
				$value = $condition_group['conditions'][0]['value'];

			    if ($field == 'node_field_data.type') {
			    	$textFields = _getAllTextFields($value); 
			    }

			    if (strpos($field, 'CONCAT_WS') !== false) {
			    	$expression = _buildExpression($textFields);
			    	if (strpos($field, 'LIKE') !== false) {
			    		$start = strpos($field, 'LIKE');
			    		$like = substr($field, $start, strlen($field) - $start);
			    	}

			    	$condition_group['conditions'][0]['field'] = $expression . ' ' . $like;
			    }
		    }
		    // Update joins
			// https://api.drupal.org/api/drupal/core%21modules%21views%21src%21Plugin%21views%21join%21JoinPluginBase.php/group/views_join_handlers/8.7.x
		    // For each field except title, there is an associated left join:
		    // LEFT JOIN {node__field_where} node__field_where ON node_field_data.nid = node__field_where.entity_id AND node__field_where.deleted = '0'
		    foreach ($textFields as $field => $val) {
		    	if ($field == 'title'){
		    		// Do nothing as title does not require additional process
		    	}
		    	elseif ($field == 'body') {
		    		$field = 'node__' . $field;
	    			if(!isset($query->getTableInfo($field)['join'])) {
	    				// Add this to the join
	    			};
		    	}
		    	else {
		    		$field = 'node__' . $field;
					$config = array(
						'type' => 'LEFT', // default
					  	'table' => $field,
					  	'field' => 'entity_id',
					  	'left_table' => $base,
					  	'left_field' => 'nid',
					  	'operator' => '=',
					  	'extra' => array(
					  		0 => array(
						  		'field' => 'deleted',
						  		'value' => '0',
						  	), 	
					  	),
					);
					$join = Views::pluginManager('join')->createInstance('standard', $config);	
					$query->addRelationship($field, $join, $base); 
		    	}
	    	}		

			// Update the search to be case sensitive
			// @see -	https://api.drupal.org/api/drupal/core%21lib%21Drupal%21Core%21Database%21Query%21Condition.php/function/Condition%3A%3Acondition/8.7.x
			$likeCondition = $query->where[1]['conditions'][1];
			$likeField = $likeCondition['field'];
			$likeValue = $likeCondition['value'];
			$likeOp = $likeCondition['operator'];

		 	$likeField = str_replace('LIKE', 'LIKE BINARY', $likeField);
			$query->where[1]['conditions'][1] = [
				'field' => $likeField,
				'value' => $likeValue,
				'operator' => $likeOp,
			];
		}
	}	
}

// helper function: get all text fields of a bundle. 
function _getAllTextFields(array $bundles) {
    $entity_type = 'node';
    $target_elements = ['title', 'body'];
    // User defined and created content fields.
    $target_field_prefix = 'field_';
    // Field type identifier, covering textarea, text_format, etc.
    $text_field_prefix = 'text';
	
	$allTextFieldsArray = [];

    foreach ($bundles as $id => $bundle) {
    	$textFieldsArray = [];

	    // Load all fields of the bundle.
	    $entityManager = \Drupal::service('entity_field.manager');
	    $fields = $entityManager->getFieldDefinitions($entity_type, $bundle);
	
	    foreach ($fields as $key => $value) {
	      if ($key === 'title'
	        || $key === 'body') {
	        if(!isset($textFieldsArray[$key])) {
	        	$textFieldsArray[$key] = 0;
	        }
	      }
	      elseif (strpos($value->getName(), $target_field_prefix) === 0) {
	        if (strpos($value->getType(), $text_field_prefix) === 0) {
		        if(!isset($textFieldsArray[$key])) {
		        	$textFieldsArray[$key] = 0;
		        }	       
		    }
	      }
	    }

	    $allTextFieldsArray = array_merge($allTextFieldsArray, $textFieldsArray);  
	}

	return $allTextFieldsArray;
}

// Helper function: create expression for query, where clause.
function _buildExpression(array $fields) {
  	if ($fields) {
	  $count = count($fields);
	  $counter = 0;
	  $separated_fields = [];
	  foreach ($fields as $key => $field) {
	  	if ($key === 'title') {
	  		$key = 'node_field_data.title';
	  	}
	  	elseif($key === 'body') {
	  		$key = 'node__body.body_value';
	  	}
	  	else {
	  		$key = 'node__' . $key . '.' . $key . '_value';
	  	}
	    $separated_fields[] = $key;
	    $counter++;
	    if ($counter < $count - 1) {
	      $separated_fields[] = "' '";
	    }
	  }
	  $expression = implode(', ', $separated_fields);
	  $expression = "CONCAT_WS(' ', $expression)";
	}
	return $expression;
}
