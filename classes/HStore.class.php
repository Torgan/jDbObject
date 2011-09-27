<?php
/**
 * File HStore
 *
 * @package utils
 */

/**
 * Class HStore
 *
 * @package utils
 * @version 1.0
 */

class HStore
{
	/**
	 * The exploded hashstore
	 * @access protected
	 * @var object
	 */

	private $_hstoreArray = array();

	/**
	 * The string to be escaped
	 * @var object
	 */

	static private $_escapeArray =
		array(
			'"'		=> '{_[DQUOT]_}',
			"\n"	=> '{_[BR]_}',
			'\\'	=> '{_[BS]_}'
		);


	/**
	 * The class constructor
	 *
	 * @param string $hstoreString
	 *
	 * @return void
	 */

	public function __construct($hstoreString = '')
	{
		// -----------------------------------------------------------------
		// The hstoreString parameter must be a string
		// -----------------------------------------------------------------

		if (! is_string($hstoreString))
		{
			throw new HStoreException(
				'[HStore::__construct] $hstoreString ' .
				'must be a string.',
				HStoreException::ERROR_HSTORESTRING_NOT_STRING
			);
		}

		// -----------------------------------------------------------------
		// Now the string is splitted according to the
		// -----------------------------------------------------------------

		if ($hstoreString !== '')
		{
			preg_match_all('/"(.+?)"=>"(.*?)"(,|$)/', $hstoreString, $m);

			$nbPairs = count($m[0]);

			for ($i = 0; $i < $nbPairs ; $i++)
			{
				$this->_hstoreArray[$m[1][$i]] = $m[2][$i];

				$this->_hstoreArray[$m[1][$i]] =
					self::decode($this->_hstoreArray[$m[1][$i]]);
			}
		}
	}

	/**
	 * __unset
	 *
	 * The magic unsetter (including constraint on attributes)
	 *
	 * @param string $name
	 * @param mixed  $value
	 *
	 * @return void
	 */

	public function __unset($name)
	{
		if (is_string($name))
		{
			unset($this->_hstoreArray[$name]);
		}
	}

	/**
	 * __set
	 *
	 * The magic setter (including constraint on attributes)
	 *
	 * @param string $name
	 * @param mixed  $value
	 *
	 * @return void
	 */

	public function __set($name, $value)
	{
		if (is_string($name))
		{
			$this->_hstoreArray[$name] = $value;
		}
	}

	/**
	 * __get
	 *
	 * The magic getter
	 *
	 * @param string $name
	 *
	 * @return mixed
	 */

	public function __get($name)
	{
		if (is_string($name) and isset($this->_hstoreArray[$name]))
		{
			return $this->_hstoreArray[$name];
		}

		return null;
	}

	/**
	 * toString
	 *
	 * Returns the string version of the hstore
	 *
	 * @return string
	 */

	public function toString()
	{
		$splittedItemsArray = array();

		foreach ($this->_hstoreArray as $k => $v)
		{
			$splittedItemsArray[] =
				'"' .
					self::encode($k) .
					'"=>"' .
					self::encode($v) .
				'"';
		}

		return implode(',', $splittedItemsArray);
	}

	/**
	 * Encode an hstore string
	 *
	 * @return string
	 */

	static public function encode($string)
	{
		return
			str_replace(
				array_keys(self::$_escapeArray),
				array_values(self::$_escapeArray),
				$string
			);
	}


	/**
	 * Decode an hstore string
	 *
	 * @return string
	 */

	static public function decode($string)
	{
		return
			str_replace(
				array_values(self::$_escapeArray),
				array_keys(self::$_escapeArray),
				$string
			);
	}
	
	/**
	 * toArray
	 *
	 * Returns the array version of the hstore
	 *
	 * @return string
	 */

	public function toArray()
	{
		return $this->_hstoreArray;
	}

}

/**
 * Class HStoreException
 *
 * @package utils
 * @subpackage Exception
 */

class HStoreException extends Exception
{
	const ERROR_HSTORESTRING_NOT_STRING	= 101;
}
