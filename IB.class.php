<?php

class IB {

	public $dbh = false;
	public $raise = false;
	public $params = false;

	/**
	 * params: type, host, base, user, pass ... etc. ;)
	 */
	function __construct($params = array())
	{
		if(is_array($params) && count($params))
			$this->params = $params;
			//$this->connect($params);
	}


	public function free_result($res)
	{
		if($this->dbh)
			ibase_free_result($res);
	}

	public function close()
	{
		if($this->dbh)
			ibase_close($this->dbh);
	}

	public function connect()
	{
		if(!$this->params || !is_array($this->params))
			throw new Exception('No database information');

		extract($this->params);

		if(!isset($user)) $user = null;
		if(!isset($pass)) $pass = null;

		if(isset($raise)) $this->raise = $raise;

		try {
			$this->dbh = ibase_connect($host.':'.$base, $user, $pass);
		} catch (Exception $e) {

			if($this->raise)
				throw $e;
			else
			{
				if(defined('DUSTY') && DUSTY)
					$err = $e->__toString();
				else
					$err = 'Сървърът е натоварен. Моля, опитайте след малко.';
				die($err);
			}
		}

		return $this->dbh;
	}


	public function prepareQuery($query, $data = array())
	{
		$params = array();
		$used = array();


		if(count($data))
		{
			$query = ' ' . $query . ' ';	// UGLY HACK
			$query = preg_replace("@\s*(?<!:)\-\-.*?$@m",'',$query); // strip comments

			preg_match_all('/\:".+"|\:\w+/', $query, $matches);

			foreach($matches[0] as $k => $m)
			{
				$m = strtolower($m);
				$key = trim(substr($m,1),'"');
				if(isset($data[$key]))
				{
					$used[$key] = $data[$key];
					$params[] = $data[$key];

					// TODO
					// php breaks where $m == ':"dbbalances.id"' ->  preg_quote -> \:"dbbalances\.id"
					// fix that hackaround
					//$query = preg_replace('/(\W)\Q'.$m.'\E(\W)/i', '$1?$2', $query);

					$query = preg_replace('/(\W)'.preg_quote($m,'/').'(\W)/i', '$1?$2', $query);
				}
			}
		}

		return array($query, $params, $used);
	}



	public function query($query, $data = array())
	{
		if(!$this->dbh) $this->connect();

		$data = dUtils::lowerKeys($data);

		//* EXECUTE BLOCK VARIANT

		preg_match_all("/EXECUTE\s+?BLOCK\s+?\(.+?\)/is", $query, $matches);

		$blocks = count($matches[0]);
		if(!$blocks)
			$block = '';
		elseif(1 == $blocks)
			$block = $matches[0][0];
		else
			throw new Exception('More than one block in query');

		$rest = str_replace($block, '', $query);

		$params1 = $params2 = array();

		if($blocks)
		{
			list($block, $params1, $used) = $this->prepareQuery($block, $data);
			foreach(array_keys($used) as $u)
				unset($data[$u]);
		}

		list($rest, $params2, $used2) = $this->prepareQuery($rest, $data);

		$params = array_merge($params1, $params2);
		$query = $block.$rest;

		if(substr_count($query, '?') != count($params))
			throw new Exception('Params count not match!', 1);

		array_unshift($params, $query);

		$result = call_user_func_array('ibase_query', $params);

		if (false === $result)
			throw new Exception(ibase_errmsg(), ibase_errcode ());

		return $result;

		// END OF EXECUTE BLOCK VARIANT */

	}


	/**
	 *	FETCH SINGLE VALUE
	 */
	public function fetch_value($query, $data = array())
	{
		$res = $this->query($query, $data);
		$row = ibase_fetch_row($res, IBASE_TEXT);
		$this->free_result($res);
		return is_array($row) ? $row[0] : false;
	}

	/**
	 *	FETCH SINGLE ROW AS OBJECT
	 */
	public function fetch_object($query, $data = array())
	{
		$row = $this->fetch_assoc($query, $data);
		return $row ? (object)$row : false;
		/*
		$res = $this->query($query, $data);
		//$row = ibase_fetch_object($res, IBASE_TEXT);
		$row = ibase_fetch_assoc($res, IBASE_TEXT);

		$this->free_result($res);

		return $row ? (object)array_combine(self::fixedKeys($row), $row) : false;
		*/
	}

	/**
	 *	FETCH SINGLE ROW AS ASSOC ARRAY
	 */
	public function fetch_assoc($query, $data = array())
	{
		$res = $this->query($query, $data);
		$row = ibase_fetch_assoc($res, IBASE_TEXT);
		$this->free_result($res);

		return $row ? array_combine(self::fixedKeys($row), $row) : false;
	}


	/**
	 *	FETCH ARRAY OF ROWS AS OBJECTS
	 */
	public function fetch_array($query, $data = array())
	{
		$res = $this->query($query, $data);
		$v = array();
		$keys = false;
		while (true)
		{
			if(!$keys)
			{
				$row = ibase_fetch_assoc($res, IBASE_TEXT);
				$keys = self::fixedKeys($row);
			}
			else
				$row = ibase_fetch_row($res, IBASE_TEXT);

			if($row)
				$v[] = (object)array_combine($keys, $row);
			else
				break;
		}

		$this->free_result($res);
		return $v;
	}

	/**
	 *	FETCH ARRAY OF ROWS AS ARRAYS
	 */
	public function fetch_assoc_array($query, $data = array())
	{
		$res = $this->query($query, $data);
		$v = array();
		$keys = false;
		while (true)
		{
			if(!$keys)
			{
				$row = ibase_fetch_assoc($res, IBASE_TEXT);
				$keys = self::fixedKeys($row);
			}
			else
				$row = ibase_fetch_row($res, IBASE_TEXT);

			if($row)
				$v[] = array_combine($keys, $row);
			else
				break;
		}

		$this->free_result($res);
		return $v;
	}



	/**
	 *	FETCH VALUES (COLUMN)
	 */
	public function fetch_values($query, $data = array())
	{
		$res = $this->query($query, $data);
		$v = array();
		while ($row = ibase_fetch_row($res, IBASE_TEXT))
			$v[] = $row[0];
		$this->free_result($res);
		return $v;
	}

	/**
	 *	FETCH TWO COLUMNS AS ARRAY col[0] => col[1]
	 */
	public function fetch_key_val($query, $data = array())
	{
		$res = $this->query($query, $data);
		$v = array();
		while ($row = ibase_fetch_row($res, IBASE_TEXT))
			$v[$row[0]] = $row[1];
		$this->free_result($res);
		return $v;
	}





	/**
	 *	fix field names, ex. in lowercase
	 */
	private static function fixedKeys($row)
	{
		return $row ? array_map("strtolower", array_keys((array)$row)) : false;
	}








	/**
	 *	GET LAST INSERTED ID
	 */
	public function last_insert_id()
	{
		throw new Exception("Not supported in IB!", 1);

		if(!$this->dbh) $this->connect();
		return $this->dbh->lastInsertId();
	}





	/**
	 *	TRANSACTIONS
	 *
	 *	- begin
	 *	- commit
	 *	- rollback
	 */


	/**
	 *	BEGIN TRANSACTION
	 */
	public function begin()
	{
		if(!$this->dbh) $this->connect();
		return ibase_trans($this->dbh);
	}

	/**
	 *	COMMIT TRANSACTION
	 */
	public function commit()
	{
		if(!$this->dbh) $this->connect();
		return ibase_commit($this->dbh);
	}

	/**
	 *	ROLLBACK TRANSACTION
	 */
	public function rollback()
	{
		if(!$this->dbh) $this->connect();
		return ibase_rollback($this->dbh);
	}




	/**
	 *	HELPERS
	 *
	 *	- insert
	 *	- update
	 *	- replace
	 *	- is_table
	 */


	/**
	 *	INSERT
	 */
	public function insert($table, $data = array())
	{
		$keys = array_keys($data);

		$q = 'insert into '.$table.' ('.implode(',',$keys).') values (:'.implode(',:',$keys).')';

		return $this->query($q, $data);
	}


	/**
	 *	REPLACE
	 */
	public function replace($table, $data = array())
	{
		$keys = array_keys($data);

		$q = 'replace into '.$table.' ('.implode(',',$keys).') values (:'.implode(',:',$keys).')';

		return $this->query($q);
	}


	/**
	 *	UPDATE
	 */
	public function update($table, $data = array(), $pKey = array())
	{
		$errors	= array();
		$fields	= array();
		$keys	= array();

		foreach($data as $key => $val)
			$fields[] = $key.'=:'.$key;

		foreach($pKey as $key => $val)
			$keys[] = $key.'=:'.$key;

		$query = 'update '.$table.' set '.implode(',',$fields).' where '.implode(' and ',$keys);
		return $this->query($query, $data + $pKey);
	}



	/**
	 *	CHECK TABLE EXISTS
	 */
	public function is_table($table)
	{
		$query = 'select 1 from rdb$relations where lower(rdb$relation_name) = lower(:table)';
		return (int)$this->fetch_value($query, array('table' => $table));
	}


}	//	END OF CLASS

