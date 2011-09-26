<?php

/**
 * File jDbObjectMethod
 *
 * @package jDbObjects
 *
 * @author  Yannick Le Guédart
 */

/**
 * Class jDbObjectMethod
 *
 * @package jDbObjects
 */

abstract class jDbObjectGetter
{
	protected $_object = null;

	/**
	 * The class constructor
	 *
	 * @param object $object
	 *
	 * @return void
	 *
	 * @access public
	 * @author Yannick Le Guédart <yannick@over-blog.com>
	 * @version 1.0
	 */

	public function __construct($object)
	{
		if (is_object($object))
		{
			$this->_object = $object;
		}
	}
	
	/**
	 * Gets data from the element dao record
	 *
	 * @param string $name The name of the DAO attribute to get
	 *
	 * @return mixed The value got
	 */

	protected function _getDaoAttribute($name)
	{
		if (
				! property_exists(
					get_class(
						$this->_element->getDAORecord()
					),
					$name)
				)
		{
			throw new jException(
				'jDbObject~error.property_doesnt_exist_in_dao',
				$name
			);		}

		return $this->_element->getDAORecord()->{$name};
	}
}
