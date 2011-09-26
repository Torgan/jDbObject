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

abstract class jDbObjectMethod
{
	/**
	 * The Object object.
	 * @access private
	 * @var object
	 */

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
}
