<?php

/**
 * File jDbObjectSetter
 *
 * @package jDbObjects
 *
 * @author  Yannick Le Guédart
 */

/**
 * Class jDbObjectSetter
 *
 * @package jDbObjects
 */

abstract class jDbObjectSetter
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
	 * Sets data in the element dao record
	 *
	 * @param string $name The name of the DAO attribute to set
	 * @param mixed $value The value of the DAO attribute to set
	 *
	 * @return mixed The value set
	 *
	 * @access protected
	 * @author Yannick Le Guédart
	 * @version 1.1
	 */

	protected function _setDaoAttribute($name, $value)
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
			);
		}

		return $this->_element->getDAORecord()->{$name} = $value;
	}
}
