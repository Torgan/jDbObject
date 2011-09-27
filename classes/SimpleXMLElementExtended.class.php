<?php
/**
 * File SimpleXMLElementExtended
 *
 * @package utils
 *
 * @author  Yannick Le Guédart (yannick@over-blog.com)
 */

/**
 * Class SimpleXMLElementExtended
 *
 * @package utils
 * @version 1.0
 */

class SimpleXMLElementExtended extends SimpleXMLElement
{

	//@}
	////////////////////////////////////////////////////////////////////////////
	/// @name PROTECTED VARIABLES [Can be set in child classes]
	////////////////////////////////////////////////////////////////////////////
	//@{

	/**
	 * _simplexml_className
	 *
	 * @access protected
	 * @var _simplexml_className
	 */

	protected $_simplexml_className = 'SimpleXMLElementExtended';

	/**
	 * count
	 *
	 * duplicate the count() method, only available in php5.3
	 *
	 * @return int
	 */

	public function count()
	{
		return count($this->children());
	}

	/**
	 * copyNode
	 *
	 * Copy the content of the node $originalNode to a new created node
	 * with $destNodeName name.
	 *
	 * @param string $destNodeName
	 * @param object $originalNode
	 *
	 * @return object
	 *
	 * @access public
	 * @author Yannick Le Guédart <yannick@over-blog.com>
	 * @version 1.0
	 */

	public function copyNode ($destNodeName, $originalNode)
	{
		if (! $originalNode instanceof $this->_simplexml_className)
		{
			throw new Exception (
				"[" . $this->_simplexml_className . "] copyNode : " .
				"\$originalNode is not of " .
				$this->_simplexml_className . " class.");
		}

 	        $this->{$destNodeName} = '';

 	        $parent = dom_import_simplexml ($this->{$destNodeName});

 	        foreach ($originalNode->children () as $node)
 	        {
 	            $oldNode = dom_import_simplexml ($node);

				$_newNode =
					$parent->ownerDocument->importNode (
						$oldNode,
						true);
 	            $newNode = $_newNode->cloneNode (true);

 	            $parent->appendChild ($newNode);
 	        }

 	        return $this;
	}

	/**
	 * removeNode
	 *
	 * Remove the node from the XML structure.
	 *
	 * @return object
	 *
	 * @access public
	 * @author Yannick Le Guédart <yannick@over-blog.com>
	 * @version 1.0
	 */

	public function removeNode ()
	{
		$node = dom_import_simplexml ($this);
		$parent = $node->parentNode;

		return simplexml_import_dom (
			$parent->removeChild ($node),
			get_class($this));
	}

 	/**
	 * getNodeFromAttributeAndValue
	 *
    * @param    $nodeName   string
    * @param    $attribute	string
    * @param    $value   	string
	 *
	 * @return object
	 *
	 * @access public
	 * @author Yannick Le Guédart <yannick@over-blog.com>
	 * @version 1.0
	 */

	public function getNodeFromAttributeAndValue (
		$nodeName,
		$attribute,
		$value)
	{
		$xpathString = $nodeName . '[@' . "$attribute='$value'" . "]";

		$nodeList = $this->xpath ($xpathString);

		if (count ($nodeList) == 1)
		{
			return $nodeList[0];
		}
		else
		{
			return $nodeList;
		}
	}

	/**
	 * Returns the parent of a node
	 *
	 * @return object
	 *
	 * @access public
	 * @author Laurent Raufaste <laurent@jfg-networks.net>
	 */

	public function getParent ()
	{
		$node = dom_import_simplexml($this);
		$parent = simplexml_import_dom($node->parentNode);

		return ($parent);
	}

	/**
	 * Add a new child at the end of the children
	 *
	 * @param	SimpleXMLElement	$new	New node
	 * @return	object				The inserted node
	 * @author	Laurent Raufaste <laurent@jfg-networks.net>
	 */

	public function appendChild($new)
	{
		$dom_this = dom_import_simplexml($this);
		$dom = $dom_this->ownerDocument;
		$dom_new = $dom->importNode(dom_import_simplexml($new), true);

		$dom_this = $dom_this->appendChild($dom_new);

		return simplexml_import_dom($dom_new, get_class($this));
	}

	/**
	 * Add a CDATA section
	 *
	 * @param	SimpleXMLElement	$new	New node
	 * @return	object				The inserted node
	 * @author	Laurent Raufaste <laurent@jfg-networks.net>
	 */

	public function addCData($nodename, $cdata_text)
	{
		$node = $this->addChild($nodename); //Added a nodename to create inside the function
		$node = dom_import_simplexml($node);
		$no = $node->ownerDocument;
		$node->appendChild($no->createCDATASection($cdata_text));
	}

   /**
	* insertBefore
	*
    * Add a new child before a reference node
    *
    * @param    SimpleXMLElement    $new    New node
    * @param    SimpleXMLElement    $ref    Reference node
	*
	* @return	object
	*
 	* @access	public
	* @author	Yannick Le Guédart <yannick@over-blog.com>
	* @author	Laurent Raufaste <laurent@jfg-networks.net>
 	* @version	1.1
    */

    public function insertBefore($new, $ref = null)
    {
		$dom_this = dom_import_simplexml($this);
		$dom = $dom_this->ownerDocument;
		$dom_new = $dom->importNode(dom_import_simplexml($new), true);

		if (isset($ref))
		{
			$dom_ref = $dom->importNode(dom_import_simplexml($ref), true);
			$dom_parent = $dom_ref->parentNode->insertBefore($dom_new, $dom_ref);
		}
		else
		{
			$dom_this = $dom_this->insertBefore($dom_new);
		}

		if ($dom_this instanceof DOMNode)
		{
			$return = simplexml_import_dom($dom_new, get_class($this));
		}
		else
		{
			$return = false;
		}

		return $return;
    }

	//@}
	////////////////////////////////////////////////////////////////////////////
	/// @name CONVERSION METHODS
	////////////////////////////////////////////////////////////////////////////
	//@{

    /**
	 * toArray
	 *
     * returns an array representation of the XML tree
     *
	 * @return array
	 *
 	 * @access public
	 * @author Yannick Le Guédart <yannick@over-blog.com>
 	 * @version 1.0
    */

	public function toArray ()
	{
		return simplexml2array ($this);
	}


	/**
	 * Overriden version of asXML to allow export parameters
	 * as LIBXML_ options don't seem to work :/
	 *
	 *
	 * @param	boolean	$noDeclaration	true = remove <?xml declaration tag
	 *
	 * @return	string	XML string corresponding to current element
	 * @author	Swanny Lorenzi
	 */
	public function asXML(
		$noDeclaration = false
	)
	{
		$res = parent::asXML();

		if ($noDeclaration)
		{
			// declaration tag is currently 22 chars long, \n included
			// I know, very ugly workaround
			$res = trim(substr($res, 22));
		}

		return $res;
	}


	//@}
	////////////////////////////////////////////////////////////////////////////
	/// @name XPATH BASED METHODS
	////////////////////////////////////////////////////////////////////////////
	//@{

	/**
	 * Sets the value of a child based on its xpath
	 *
	 * @param	string	$xpath	xpath of the node
	 *							The xpath must point to a single node
	 * @param	mixed	$value	Value of the node
	 *
	 * @return	boolean
	 *
	 * @access	public
	 * @author	yannick Le Guédart <yannick@over-blog.com>
	 */

	public function setValueFromXpath($xpath, $value)
	{
		$nodes = $this->xpath($xpath);

		if (count($nodes) !== 1 or (count($nodes[0]->children()) > 1))
		{
			return false;
		}

		$parent = $nodes[0]->getParent();
		$name 	= $nodes[0]->getName();

		$parent->{$name} = $value;

		return true;
	}

	/**
	 * Add a child to the node pointed by an xpath
	 *
	 * @param	string	$xpath		xpath of the node to which add a child
	 *								The xpath must point to a single node
	 * @param	string	$childName	Name of the child
	 * @param	mixed	$childValue	Value of the child
	 *
	 * @return	object		Child node that has been added, null if failed
	 * @throws	Exception	If xpath points more than 1 node, an exception is
	 *						thrown.
	 *
	 * @access	public
	 * @author	Swanny Lorenzi <swanny@over-blog.com>
	 */

	public function addChildFromXpath($xpath, $childName, $childValue)
	{
		$nodes = $this->xpath($xpath);

		if (count($nodes) > 1 )
		{
			// more than 1 node found => unhandled case.
			throw new Exception(
				'addChildFromXpath ERROR :'
				. ' Given xpath ' . $xpath . ' points to more than one node.'
			);
		}

		if (isset($nodes[0]))
		{
			if (is_string ($childName) && !empty ($childName))
			{
				// 1 node found, a child can be added.
				return $nodes[0]->addChild($childName, $childValue);
			}
			else
			{
				// Muvais childName
				return null;
			}
		}
		else
		{
			// no node found
			return null;
		}
	}

	/**
	 * Insert a new node corresponding to XML code in a parent node,
	 * before a children
	 *
	 * @param	string	$parentXpath	xpath of the parent node to which insert
	 * 									the XML
	 * 									This xpath must point to a single node
	 * @param	string	$xml			XML to insert
	 * @param	string	$beforeXpath	Optionnal. Relative Xpath of the
	 * 									parent's child node before which XML
	 * 									must be inserted.
	 * 									Absolute xpath will be
	 * 										 $parentXpath . $before
	 * 									This xpath must point to a single node
	 *
	 * @return	object		The node that has been added, null if failed
	 * @throws	Exception	If one given xpath points more than 1 node
	 * @throws	Exception	If given $xml is not a valid XML
	 * @throws	Exception	If $beforeXpath does not point to a child of
	 * 						$parentXpath
	 *
	 * @access	public
	 * @author	Swanny Lorenzi <swanny@over-blog.com>
	 */

	public function insertXMLFromXpath(
		$parentXpath,
		$xml,
		$beforeXpath = null )
	{
		$nodes = $this->xpath($parentXpath);

		if (count($nodes) > 1 )
		{
			// more than 1 node found => unhandled case.
			throw new Exception(
				'insertXMLFromXpath ERROR :'
				. ' Given parent xpath ' . $parentXpath
				. ' points to more than one node.'
			);
		}

		if (isset($nodes[0]))
		{
			// 1 node found, insertion can continue.

			// creating new child node
			$newChild = @new SimpleXMLElementExtended($xml);

			if (! ($newChild instanceof SimpleXMLElementExtended))
			{
				throw new Exception(
					'insertXMLFromXpath ERROR :'
					. ' Given XML string ' . $xml
					. ' is not valid.'
				);

			}

			// dealing with $beforeXpath
			if ( !is_null($beforeXpath) )
			{
				// one $beforeXpath has been given
				$childNodes = $this->xpath($parentXpath . $beforeXpath);

				if ( count($childNodes) > 1 )
				{
					// more than 1 node found => unhandled case.
					throw new Exception(
						'insertXMLFromXpath ERROR :'
						. ' Given beforeXpath ' . $beforeXpath
						. ' points to more than one child node'
						. ' of parent ' . $parentXpath
					);
				}
				else if ( count($childNodes) == 0 )
				{
					// no node found => another unhandled case.
					throw new Exception(
						'insertXMLFromXpath ERROR :'
						. ' Given beforeXpath ' . $beforeXpath
						. ' does not point to any child node'
						. ' of parent ' . $parentXpath
					);
				}
				else
				{
					// We got a correct childNode, let's insert XML
					return $nodes[0]->insertBefore(
						$newChild,
						$childNodes[0]
					);
				}

			}
			else
			{
				// no $beforeXpath, let's insert XML
				return $nodes[0]->insertBefore($newChild);
			}
		}
		else
		{
			// no node found
			return null;
		}
	}

	/**
	 * Remove the node pointed by an xpath
	 *
	 * @param	string	$xpath	xpath of the note to remove
	 *							The xpath must point to a single node
	 *
	 * @return	object		Node that has been removed, null if failed
	 * @throws	Exception	If xpath points more than 1 node, an exception is
	 *						thrown.
	 *
	 * @access	public
	 * @author	Swanny Lorenzi <swanny@over-blog.com>
	 */

	public function removeNodeFromXpath($xpath)
	{
		$nodes = $this->xpath($xpath);

		if (count($nodes) > 1 )
		{
			// more than 1 node found => unhandled case.
			throw new Exception(
				'removeNodeFromXpath ERROR :'
				. ' Given xpath ' . $xpath . ' points to more than one node.'
			);
		}

		if (isset($nodes[0]))
		{
			// 1 node found, a child can be added.
			return $nodes[0]->removeNode();
		}
		else
		{
			// no node found
			return null;
		}
	}

}


/**
 * simplexml2array
 *
 * Convert SimpleXMLElement object to array
 *
 * anthony : replace the old method simplexml_to_array
 * this one seems to work
 *
 * @return string
 *
 * @author Daniel FAIVRE 2005 - www.geomaticien.com
 * @copyright Daniel FAIVRE 2005 - www.geomaticien.com
 * @license GPL
 */

function simplexml2array($xml) {
    if (get_class($xml) == 'SimpleXMLElementExtended') {
        $attributes = $xml->attributes();
        foreach($attributes as $k=>$v) {
            if ($v) $a[$k] = (string) $v;
        }
        $x = $xml;
        $xml = get_object_vars($xml);
    }
    if (is_array($xml)) {
        if (count($xml) == 0) return (string) $x; // for CDATA
        foreach($xml as $key=>$value) {
            $r[$key] = simplexml2array($value);
        }
        if (isset($a)) $r['@'] = $a;    // Attributes
        return $r;
    }
    return (string) $xml;
}
