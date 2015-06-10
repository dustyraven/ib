<?php

/**
 *	Example file for IB class
 */


// require class itself
require_once __DIR__.'/IB.class.php';



// init class. N.B. - no connection made at this point
$db = new IB(array(
		'host'	=>	'localhost',
		'base'	=>	'example.fdb',
		'user'	=>	'someuser',
		'pass'	=>	'somepass',
));

/**
 *	Fetch data
 *
 *	example:
 *	$val = $db->fetch_value('select current_Timestamp from rdb$database');
 *	will return DB timestamp
 */

// fetch single value
$data = $db->fetch_value($query, $params);

// fetch single row as object
$data = $db->fetch_object($query, $params);

// fetch single row as assoc array
$data = $db->fetch_assoc($query, $params);

// fetch array of objects
$data = $db->fetch_array($query, $params);

// fetch array of associative arrays
$data = $db->fetch_assoc_array($query, $params);

// fetch array of values (column 0 from result set)
// ex. select user_name from users
// will return numeric array with user names
$data = $db->fetch_values($query, $params);

// fetch two columns and return array with col 0 as keys and col 1 as values
// ex. select id, user_name from users
// will return array('id' => 'user_name', )
$data = $db->fetch_key_val($query, $params);


/**
 *	Transactions
 */

// begin transacrion
$db->begin();

// commit transacrion
$db->commit();

// rollback transacrion
$db->rollback();


/**
 *	HELPERS
 *
 *	- insert
 *	- update
 *	- replace
 *	- is_table
 *
 *
 *	$table - name of the table
 *	$data - associative array with insert/update/replace values
 *	$keys - associative array with primary key(s) for update/replace
 *
 *	example:
 *
 *	$db->update('users', array('name' => 'John', 'surname' => 'Smith'), array('id' => 5));
 *	will result to query:
 *	update users set name = 'John', surname = 'Smith' where id = 5
 *
 */



// insert new row
$result = $db->insert($table, $data);

// update row
$result = $db->update($table, $data, $keys);

// replace row
$result = $db->replace($table, $data, $keys);

// check table exists
$result = $db->is_table($table);

