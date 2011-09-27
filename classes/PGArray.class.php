<?php
/**
 * File PGArray
 *
 * @package utils
 */

/**
 * Class PGArray
 *
 * @package utils
 * @version 1.0
 */

class PGArray extends ArrayObject
{
	/**
	 * The class constructor
	 *
	 * @param string $pgArrayString
	 *
	 * @return void
	 */

	public function __construct($pgArrayString = '{}')
	{
		// -----------------------------------------------------------------
		// The hstoreString parameter must be a string
		// -----------------------------------------------------------------

		if (! is_string($pgArrayString))
		{
			throw new HStoreException(
				'[PGArrayObject::__construct] $pgArrayString ' .
				'must be a string.',
				PGArrayObjectException::ERROR_PGARRAYSTRING_NOT_STRING
			);
		}

		// -----------------------------------------------------------------
		// We remove the external accolades and split, removing the '"'
		// -----------------------------------------------------------------

		$this->_parse($pgArrayString, $pgArray);

		parent::__construct($pgArray);
	}

	/**
	 * Convert to a valid pg string for arrays
	 *
	 * @return string
	 */

	public function toString()
	{
		$returnArray = array();

		foreach ($this as $v)
		{
			$v = str_replace('"', '\\"', $v);

			if (preg_match('/[,\s]/', $v, $m) or ($v === ''))
			{
				$v = '"' . $v . '"';
			}

			$returnArray[] = $v;
		}

		return '{' . implode(',', $returnArray) . '}';
	}

	/**
	 * recursive Parsing method
	 *
	 * @param string  	$text
	 * @param array  	$output
	 * @param integer 	$limit
	 * @param integer 	$offset
	 *
	 * @return array
	 */

	private function _parse(
		$text,
		&$output,
		$limit 		= false,
		$offset 	= 1
	)
	{
		if ($limit === false)
		{
			$limit = strlen ($text) - 1;
			$output = array();
		}

		if ($text != '{}')
		{
			do
			{
				if ($text{$offset} != '{')
				{
					preg_match(
						"/(\\{?\"([^\"\\\\]|\\\\.)*\"|[^,{}]+)+([,}]+)/",
						$text,
						$match,
						0,
						$offset
					);

					$offset += strlen($match[0]);

					$output[] =
						(
							$match[1]{0} != '"'
							?
							$match[1]
							:
							stripcslashes(substr($match[1], 1, -1))
						);

					if ($match[3] == '},')
					{
						return $offset;
					}
				}
				else
				{
					$offset =
						$this->_parse(
							$text,
							$output[],
							$limit,
							$offset+1
						);
				}
			}
			while ($limit > $offset);
		}

		return $output;
	}

}

/**
 * Class HStoreException
 *
 * @package utils
 * @subpackage Exception
 */

class PGArrayObjectException extends Exception
{
	const ERROR_PGARRAYSTRING_NOT_STRING	= 101;
}
