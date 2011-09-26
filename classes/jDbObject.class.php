<?php
/**
 * File jDbObjectWithXmlData
 *
 * @package jDbObjects
 *
 * @author  Yannick Le Guédart
 */

abstract class jDbObject
{
	/**
	 * The module name
	 * @access protected
	 * @var string
	 */

	protected $_moduleName = null;

	/**
	 * The name of the table associated with the class.
	 * @var string
	 */

	static public $_tableName = null;

	/**
	 * The dao name.
	 * @var dao
	 */

	protected $_dao_name = null;

	/**
	 * The dao.
	 * @var object
	 */

	protected $_dao = null;

	/**
	 * The daoRecord.
	 * @var object
	 */

	protected $_dao_record = null;

	/**
	 * Whether the object is saveable in the database (not the cas for example
	 * if the object is created from a view.
	 * @var boolean
	 */

	protected $_savable = true;

	/**
	 * The database profile.
	 * @var object
	 */

	protected $_db_profile = null;

	/**
	 * The external attribute. It can be used to store more information than
	 * the original data of the table from linked tables
	 *
	 * @var object
	 */

	public $external = null;

	/**
	 * The methodClass Class.
	 * @access protected
	 * @var string
	 */

	protected $_methodClass = null;

	/**
	 * The setterClass Class.
	 * @access protected
	 * @var string
	 */

	protected $_getterClass = null;

	/**
	 * The setterClass Class.
	 * @access protected
	 * @var string
	 */

	protected $_setterClass = null;

	/**
	 * Handling special data
	 *
	 * @avr array
	 */

	protected $_special = array();

	/**
	 * The class constructor
	 *
	 * $data contient soit une donnée numérique, qui correspond à l'id dans la
	 * base, soit un objet ou un tableau à partir desquels sera construit
	 * l'objet sans se soucier de la base.
	 *
	 * @param mixed 	$data
	 * @param array   	$params
	 *
	 * @return void
	 */

	public function __construct (
		$data 			= null,
		$params         = array ()
	)
	{
		// Initialisation

		$this->_init();

		// Maintenant, on va tenter de construire notre objet. Le permier cas,
		// c'est d'avoir un data non défini.

		if (is_null($data))
		{
			// Dans ce cas, on crée un objet vide...

			$this->_dao_record =
				jDao::createRecord(
					$this->_dao_name,
					$this->_db_profile
				);
		}
		else // sinon, on a un id
		{
			// Si $data est numérique, alors on va recherche les données en
			// base.

			if (is_numeric($data))
			{
				$this->_dao_record = $this->_dao->get($data);

				if (! $this->_dao_record)
				{
					throw new jException(
						'jDbObject~exceptions.error.pk_doesnt_exist',
						$data
					);
				}
			}
			elseif (is_array($data))
			{
				if (count($data) == count($this->_dao->getPrimaryKeyNames()))
				{
					$validPks = true;
					$pkParams = array();

					foreach($this->_dao->getPrimaryKeyNames() as $k => $v)
					{
						if (! isset($data[$v]))
						{
							$validPks = false;

							last();
						}

						$pkParams[$v] = $data[$v];
					}

					if ($validPks === true)
					{
						$this->_dao_record =
							call_user_func_array(
								array(
									$this->_dao,
									'get'
								),
								$pkParams
							);

						if (! $this->_dao_record)
						{
							throw new jException(
								'jDbObject~exceptions.error.pk_doesnt_exist',
								$data
							);
						}
					}
					else
					{
						$this->_dao_record =
							jDao::createRecord(
								$this->_dao_name,
								$this->_db_profile
							);

						$this->setFrom($data);
					}

				}
				else
				{
					$this->_dao_record =
						jDao::createRecord(
							$this->_dao_name,
							$this->_db_profile
						);

					$this->setFrom($data);
				}
			}
			elseif (is_object($data))
			{
				$this->_dao_record =
					jDao::createRecord(
						$this->_dao_name,
						$this->_db_profile
					);

				$this->setFrom($data);
			}
			else
			{
				throw new jException(
					'jDbObject~exceptions.error.invalid_data',
					gettype($data)
				);
			}
		}

		// Gestion un peu à l'arrache des champs booleens que jelix gère fort
		// mal

		$this->_initSpecialProperties();
	}

	/**
	 * Initialize the object before the constructor. Verify the existence of
	 * the _dao_name var and initialize the DAO object.
	 *
	 * @throws CoreException
	 *
	 * @return void
	 *
	 */

	protected $_init = false;

	protected final function _init()
	{
		// Si l'initalisation a déjà eu lieu, inutile de la refaire

		if ($this->_init === true)
		{
			return;
		}

		// Si on a pas de nom de DAO, inutile d'aller plus loin, la classe est
		// mal définie

		if (is_null($this->_dao_name))
		{
				throw new jException(
					'jDbObject~exceptions.error.dao_name_not_defined'
				);
		}

		// On instancie donc un objet DAO.

		if (is_null($this->_dao))
		{
			$this->_dao =
				jDao::get(
					$this->_dao_name,
					$this->_db_profile
				);
		}

		// On instancie l'objet external pour toute donnée externe à la
		// dao qu'on voudrait trimballer avec l'objet :

		$this->external = new StdClass();

		// Puis on note que l'initialisation a eu lieu pour cet objet

		$this->_init = true;
	}

	/**
	 * The magic setter
	 *
	 * @param string $name
	 * @param mixed  $value
	 *
	 * @return void
	 */

	public function __set($name, $value)
	{
		// Tout d'abord, bien entendu, si $name n'est pas une chaine, on ne
		// va pas plus loin...

		if (! is_string($name))
		{
			throw new jException(
				'jDbObject~exceptions.error.set_invalid_first_param',
				gettype($name)
			);
		}

		// Si la promriété n'existe pas dans la dao, on lève une exception, en
		// conseillant (sans vraiment donner le choix, certes), d'utiliser
		// external

		if (! property_exists(get_class($this->_dao_record), $name))
		{
			throw new jException(
				'jDbObject~exceptions.error.property_doesnt_exist_in_dao',
				$name
			);
		}

		// Ensuite, on regarde si, par le plus grand des hasards, on n'aurait
		// pas une classe de setters, et si c'est le cas, on regarde si on n'a
		// pas une méthode du type setName. Si c'est le cas, on l'utilise

		if (! is_null($this->_setterClass))
		{
			if (! class_exists($this->_setterClass))
			{
				jClasses::inc($this->_moduleName . '~' . $this->_setterClass);
			}

			$setterObject = new $this->_setterClass($this);
			$setterMethod = 'set' . ucfirst($name);

			// La méthode existe-t-elle ?

			if (method_exists($setterObject, $setterMethod))
			{
				call_user_func_array
				(
					array
					(
						$setterObject,
						$setterMethod,
					),
					$value
				);

				return;
			}
		}

		// special properties can't be set

		if (! is_null($this->_special[$name]))
		{
			throw new jException(
				'jDbObject~exceptions.error.special_properties_cant_be_set'
			);
		}

		// Et donc là on arrive et on met à jour la propriété du dao_record

		$this->_dao_record->{$name} = $value;
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
		// Tout d'abord, bien entendu, si $name n'est pas une chaine, on ne
		// va pas plus loin...

		if (! is_string($name))
		{
			throw new jException(
				'jDbObject~exceptions.error.get_invalid_first_param',
				gettype($name)
			);
		}

		// Si la promriété n'existe pas dans la dao, on lève une exception,
		// c'est vraisemblablement une erreur

		if (! property_exists(get_class($this->_dao_record), $name))
		{
			throw new jException(
				'jDbObject~exceptions.error.property_doesnt_exist_in_dao',
				$name
			);
		}

		// Special property

		if (array_key_exists($name, $this->_special))
		{
			return $this->_getSpecialProperty($name);
		}

		// Et sinon on renvoie la propriété de la DAO

		return $this->_dao_record->{$name};
	}


	/**
	 * __call
	 *
	 * The magic caller
	 *
	 * @param string $name
	 * @param string $params
	 *
	 * @return mixed
	 *
	 * @access public
	 * @author Yannick Le Guédart
	 */

	public function __call($name, $params)
	{
		if (is_string($name) and (strpos($name, '__') !== 0))
		{
			// -----------------------------------------------------------------
			// Setter method
			// -----------------------------------------------------------------

			if (strpos($name, 'set') === 0)
			{
				if (is_null($this->_setterClass))
				{
					throw new jException(
						'jDbObject~exceptions.error.setter_method_not_accepted',
						$name
					);
				}
				else
				{
					if (! class_exists($this->_setterClass))
					{
						jClasses::inc(
							$this->_moduleName . '~' . $this->_setterClass
						);
					}

					$setterObject = new $this->_setterClass($this);

					if (method_exists($setterObject, $name))
					{
						return
							call_user_func_array
							(
								array
								(
									$setterObject,
									$name,
								),
								$params
							);
					}
					else
					{
						throw new jException(
							'jDbObject~exceptions.error.setter_method_doesnt_exist',
							$name
						);
					}
				}
			}
			elseif (strpos($name, 'get') === 0)
			{
				if (is_null($this->_getterClass))
				{
					throw new jException(
						'jDbObject~exceptions.error.getter_method_not_accepted',
						$name
					);
				}
				else
				{
					if (! class_exists($this->_getterClass))
					{
						jClasses::inc(
							$this->_moduleName . '~' . $this->_getterClass
						);
					}

					$getterObject = new $this->_getterClass($this);

					if (method_exists($getterObject, $name))
					{
						return
							call_user_func_array
							(
								array
								(
									$getterObject,
									$name,
								),
								$params
							);
					}
					else
					{
						throw new jException(
							'jDbObject~exceptions.error.getter_method_doesnt_exist',
							$name
						);
					}
				}
			}

			// -----------------------------------------------------------------
			// More methods
			// -----------------------------------------------------------------

			if (is_null($this->_methodClass))
			{
				throw new jException(
					'jDbObject~exceptions.error.external_method_not_accepted',
					$name
				);
			}

			if (! class_exists($this->_methodClass))
			{
				jClasses::inc($this->_moduleName . '~' .$this->_methodClass);
			}

			$methodObject = new $this->_methodClass($this);

			if (method_exists($methodObject, $name))
			{
				return
					call_user_func_array
					(
						array
						(
							$methodObject,
							$name,
						),
						$params
					);
			}
			else
			{
				throw new jException(
					'jDbObject~exceptions.error.external_method_doesnt_exist',
					$name
				);
			}
		}
	}

	/**
	 * Saving the element to the database. As a matter of fact, this method
	 * first try to update the element, and then to insert it. The element is
	 * then updated with the data in the database.
	 *
	 * @return boolean
	 *
	 * @author Yannick Le Guédart
	 */

	public function save()
	{
		if ($this->_savable === false)
		{
			throw new jException(
				'jDbObject~exceptions.error.object_cant_be_saved',
				get_class($this)
			);
		}

		// Exécution de la méthode _beforeSave(), vide par défaut, mais
		// surchargeable dans chaque classe qui le nécessite.

		$this->_beforeSave();

		// Gestion des champs spéciaux

		$this->_saveSpecialProperties();

		// Si l'id n'est pas null, c'est vraisemblablement un objet existant. On
		// tente une update

		if (
				(
					property_exists($this->_dao_record, 'id')
					and
					! is_null($this->_dao_record->id)
				)
				or
				! property_exists($this->_dao_record, 'id')
			)
		{
			$result = null;

			if  (
					count($this->_dao->getPrimaryKeyNames())
					!==
					count($this->_dao->getProperties())
				)
			{
				$result = $this->_dao->update($this->_dao_record);
			}

			if ($result === 1)
			{
				// ça a marché, génial, rien de plus !

				return true;
			}
			else
			{
				// L'update a échoué, on tente d'insérer

				return ($this->_dao->insert($this->_dao_record) === 1);
			}
		}
		else
		{
			// l'id est à null, on ne peut faire qu'une insertion.

			return ($this->_dao->insert($this->_dao_record) === 1);
		}
	}

	/**
	 * Operations to be done before each save.
	 *
	 * @return boolean
	 *
	 * @author Yannick Le Guédart
	 */

	protected function _beforeSave()
	{
	}

	/**
	 * Delete the element from the database
	 *
	 * @return boolean
	 *
	 * @author Yannick Le Guédart
	 */

	public function delete()
	{
		$params = array();

		foreach ($this->_dao->getPrimaryKeyNames() as $pk)
		{
			if (is_null($this->{$pk}))
			{
				return false; // One pk is missing => no delete
			}
			else
			{
				$params[] = $this->{$pk};
			}
		}

		if (
			call_user_func_array(
				array (
					$this->_dao,
					'delete'
				),
				array(
					$params
				)
			)
		)
		{
			// On a effacé l'objet en base. L'objet n'a plus lieu d'être

			$this->_dao_record = null;
			$this->__destruct();

			return true;
		}

		return false;
	}

	/**
	 * set the element from another object or array
	 *
	 * @param mixed $newElement
	 * @param array $except
	 *
	 * @return boolean
	 *
	 * @author Yannick Le Guédart
	 */

	public function setFrom (
		$newElement,
		$except = array ())
	{
		if (! is_object($newElement) and ! is_array($newElement))
		{
			throw new jException(
				'jDbObject~exceptions.error.setfrom_invalid_first_param',
				gettype($newElement)
			);
		}

		if (! is_array($except))
		{
			throw new jException(
				'jDbObject~exceptions.error.setfrom_invalid_second_param',
				gettype($except)
			);
		}

		// Tout d'abord, on récupère les attributs voalides de la DAO

		$validFields = array_keys(get_object_vars($this->_dao_record));

		// Le cas le plus simple, c'est quand le tableau except est vide

		if (is_object($newElement) and empty($except))
		{
			if (get_class($newElement) === get_class($this))
			{
				// c'est une copie depuis un objet de même type.

				$this->_dao_record = $newElement->_dao_record;

				return true;
			}
			elseif (get_class($newElement) === get_class($this->_dao_record))
			{
				// c'est une copie depuis un dao_record de type adéquat.

				$this->_dao_record = $newElement;

				return true;
			}
		}

		// Dans le cas où l'objet et de même type que le courant, c'est
		// uniquement les données de la DAO qu'on veut copier, mais avec des
		// exceptions si on est là. On récupère le _dao_record de l'objet en
		// question en tant que $newElement.

		if (get_class($newElement) === get_class($this))
		{
			$newElement = $newElement->getDAORecord();
		}

		// Cas plus compliqué, soit except n'est pas vide, soit l'objet passé
		// en paramètre n'est pas d'un type adéquat : on va copier les objets à
		// l'ancienne

		foreach ($newElement as $varName => $varValue)
		{
			if (! in_array($varName, $validFields))
			{
				// C'est un attribut non géré par la DAO : il va dans external

				$this->external->{$varName} = $varValue;
			}
			elseif (! in_array($varName, $except))
			{
				// Pas d'exception sur cet attribut, on l'intègre donc à la DAO.

				if (in_array($varName, $validFields))
				{
					$this->_dao_record->{$varName} = $varValue;
				}
			}
		}
	}

	/**
	 * Get the dao record for the element
	 *
	 * @return object
	 *
	 * @author Yannick Le Guédart
	 */

	public function getDAORecord()
	{
		return $this->_dao_record;
	}

	/**
	 * Handle special properties
	 */

	private $_properties = null;

	protected function _initSpecialProperties()
	{
		if (is_null($this->_properties))
		{
			$this->_properties = array();

			foreach ($this->_dao->getProperties() as $k => $v)
			{
				$this->_properties[$k]	= $v['datatype'];

				switch ($v['datatype'])
				{
					case 'boolean':
					case 'date':
					case 'datetime':
					case 'time':
					case 'xml':
					case 'xmltype':
					case 'hstore':
					case 'array':
					case 'varbit':
					case 'bit varying':
					case 'bit':
					case 'bitfield':
					case 'bitfield_int':
						$this->_special[$k] 	= null;
						break;
				}
			}
		}
	}

	protected function _getSpecialProperty($name)
	{
		if (is_null($this->_special[$name]))
		{
			switch($this->_properties[$name])
			{
				case 'boolean':
					$this->_special[$name] =
						(
							(
								($this->_dao_record->{$name} === 'f')
								or
								($this->_dao_record->{$name} === false)
							)
							?
							false
							:
							true
						);
					break;
				case 'date':
					if (! is_null($this->_dao_record->{$name}))
					{
						$this->_special[$name] = new jDateTime();
						$this->_special[$name]->setFromString(
							$this->_dao_record->{$name},
							jDateTime::DB_DFORMAT
						);
					}
					break;
				case 'datetime':
					if (! is_null($this->_dao_record->{$name}))
					{
						$this->_special[$name] = new jDateTime();
						$this->_special[$name]->setFromString(
							$this->_dao_record->{$name},
							jDateTime::DB_DTFORMAT
						);
					}
					break;
				case 'time':
					if (! is_null($this->_dao_record->{$name}))
					{
						$this->_special[$name] = new jDateTime();
						$this->_special[$name]->setFromString(
							$this->_dao_record->{$name},
							jDateTime::DB_TFORMAT
						);
					}
					break;
				case 'xml':
				case 'xmltype':
					$this->_special[$name] =
						new jSimpleXMLElementExtended(
							$this->_dao_record->{$name}
						);
					break;
				case 'hstore':
					$this->_special[$name] =
						new HStore($this->_dao_record->{$name});
					break;
				case 'array':
					$this->_special[$name] =
						new PGArray($this->_dao_record->{$name});
					break;
				case 'varbit':
				case 'bit varying':
				case 'bit':
				case 'bitfield':
					$this->_special[$name] = new ArrayObject();
					for (
						 $i = 0 ;
						 $i < strlen($this->_dao_record->{$name}) ;
						 $i++
					)
					{
						$this->_special[$name][$i] =
							($this->_dao_record->{$name}[$i] === '1');
					}
					break;
				case 'bitfield_int':
					$this->_special[$name] 	= new ArrayObject();
					$rating_array 			=
						strrev(
							base_convert(
								$this->_dao_record->{$name},
								10,
								2
							)
						);
					for ($i = 0; $i < 16 ; $i++)
					{
						$this->_special[$name][$i] =
							($rating_array[$i] === '1') ? true : false;
					}
					break;
			}
		}

		return $this->_special[$name];
	}

	protected function _saveSpecialProperties()
	{
		foreach ($this->_special as $k => $v)
		{
			if (is_null($v))
			{
				continue;
			}

			switch($this->_properties[$k])
			{
				case 'date':
					if (! is_null($this->_special[$k]))
					{
						$this->_dao_record->{$k} =
							$this->_special[$k]->toString(
								jDateTime::DB_DFORMAT
							);
					}
					break;
				case 'datetime':
					if (! is_null($this->_special[$k]))
					{
						$this->_dao_record->{$k} =
							$this->_special[$k]->toString(
								jDateTime::DB_DTFORMAT
							);
					}
					break;
				case 'time':
					if (! is_null($this->_special[$k]))
					{
						$this->_dao_record->{$k} =
							$this->_special[$k]->toString(
								jDateTime::DB_TFORMAT
							);
					}
					break;
				case 'xml':
				case 'xmltype':
					$this->_dao_record->{$k} = $this->_special[$k]->asXML();
					break;
				case 'hstore':
				case 'array':
					$this->_dao_record->{$k} = $this->_special[$k]->toString();
					break;
				case 'varbit':
				case 'bit varying':
				case 'bit':
				case 'bitfield':
					$this->_dao_record->{$k} = '';
					foreach ($this->_special[$k] as $bit)
					{
						$this->_dao_record->{$k} .= $bit ? 1 : 0;
					}
					break;
				case 'bitfield_int':
					$base2string = '';
					foreach ($this->_special[$k] as $bit)
					{
						$base2string .= $bit ? 1 : 0;
					}
					$this->_dao_record->{$k} =
						base_convert(strrev($base2string), 2, 10);
					break;
			}
		}
	}


	////////////////////////////////////////////////////////////////////////////
	// Relation entre éléments - les enfants
	////////////////////////////////////////////////////////////////////////////

	/**
	 * Get an element collection according to the params
	 *
	 * Should be used only exceptionnally, just because _linkedElements
	 * mechanism exists
	 *
	 * @param string  $linkedClass
	 * @param array   $params
	 * @static
	 * @final
	 *
	 * @return ArrayObject of objects
	 *
	 * @author Yannick Le Guédart
	 */

	static public final function getAll($params	= array())
	{
		$params['countOnly'] = false;

		return
			call_user_func_array(
				array (
					get_called_class() . 'QueryComposer',
					'get'
				),
				array(
					get_called_class(),
					$params
				)
			);
	}

	/**
	 * Array containing the table corresponding to the element the current
	 * element can have as children
	 *
	 * @var array
	 */

	protected $_linkedElement = array();

	/**
	 * Get childs of a given type for an element
	 *
	 * @param string  $linkedClass
	 * @param array   $params
	 *
	 * @return ArrayObject of $linkedClass object
	 *
	 * @author Yannick Le Guédart
	 */

	public function get (
		$linkedClass = null,
		$params		= array()
	)
	{
		$params['countOnly'] = false;

		return $this->_getLinkedElements($linkedClass, $params);
	}

	/**
	 * Get one child of a given type for an element.
	 *
	 * The offset/limit parameters can be provided but are forced to 0/1
	 *
	 * @param string  $linkedClass
	 * @param array   $params
	 *
	 * @return $linkedClass object or null
	 *
	 * @author Yannick Le Guédart
	 */

	public function getOne(
		$linkedClass = null,
		$params		= array()
	)
	{
		$params['countOnly'] = false;
		$params['limit'] = 1;

		$returnArrayObject = $this->_getLinkedElements($linkedClass, $params);

		if ($returnArrayObject->count() > 0)
		{
			return $returnArrayObject->offsetGet(0);
		}

		return null;
	}

	/**
	 * Get the first child of a given type for an element.
	 *
	 * The offset/limit parameters can be provided but are forced to 0/1
	 *
	 * @param string  $linkedClass
	 * @param array   $params
	 *
	 * @return $linkedClass object or null
	 *
	 * @author Yannick Le Guédart
	 */

	public function getFirst(
		$linkedClass = null,
		$params		= array()
	)
	{
		$params['offset'] = 0;

		return $this->getOne($linkedClass, $params);
	}

	/**
	 * Get number of childs of a given type for an element
	 *
	 * @param string  $linkedClass
	 * @param array   $params
	 *
	 * @return integer
	 *
	 * @author Yannick Le Guédart
	 */

	public function getNb(
		$linkedClass 	= null,
		$params			= array()
	)
	{
		$params['countOnly'] = true;

		return $this->_getLinkedElements($linkedClass, $params);
	}

	/**
	 * Get linked elements or a number of linked elements of a given type for
	 * the current element.
	 *
	 * @param string  $linkedClass
	 * @param array   $params
	 *
	 * @return ArrayObject of $linkedClass object, or integer
	 *
	 * @author Yannick Le Guédart
	 */

	protected function _getLinkedElements(
		$linkedClass 	= null,
		$params			= array()
	)
	{
		// ---------------------------------------------------------------------
		// At first, we check if the required elements are indeed contained
		// in the current element
		// ---------------------------------------------------------------------

		$this->_checkLinkedElementType($linkedClass);

		// ---------------------------------------------------------------------
		// We include the child class
		// ---------------------------------------------------------------------

		$this->_include($linkedClass, $this->_linkedElement[$linkedClass]);

		// ---------------------------------------------------------------------
		// if a taregetClass param is provided, we change the $linkedClass
		// value to reflect this
		// ---------------------------------------------------------------------

		if (isset($this->_linkedElement[$linkedClass]['targetClass']))
		{
			 $targetClass =	$this->_linkedElement[$linkedClass]['targetClass'];
		}
		else
		{
			$targetClass = $linkedClass;
		}

		// ---------------------------------------------------------------------
		// We make sure that the required method exists
		// ---------------------------------------------------------------------

		if (! in_array('get', get_class_methods($targetClass . 'QueryComposer')))
		{
			throw new jException(
				'jDbObject~exceptions.error.method_get_unavailable',
				$targetClass
			);
		}

		// ---------------------------------------------------------------------
		// Link the current class to the linked element
		// ---------------------------------------------------------------------

		foreach ($this->_linkedElement[$linkedClass]['params'] as $k => $v)
		{
			if (preg_match('/^\{(.*?)\}$/', $v, $m))
			{
				if (strpos($m[1], ':') !== false)
				{
					$p = $this;

					foreach (split(':', $m[1]) as $attr)
					{
						$p = $p->{$attr};
					}

					$params[$k] = $p;
				}
				elseif (! is_null($this->{$m[1]}))
				{
					$params[$k] = $this->{$m[1]};
				}
				else
				{
					var_dump($m[1], $k);
					throw new jException(
						'jDbObject~exceptions.error.get_getnb_null_param',
						array(
							$m[1],
							$k
						)
					);
				}
			}
			else
			{
				$params[$k] = $v;
			}

		}

		// ---------------------------------------------------------------------
		// We execute the required request an return the result
		// ---------------------------------------------------------------------

		$func_args = array($targetClass, $params);

		return
			call_user_func_array(
				array (
					$targetClass . 'QueryComposer',
					'get'
				),
				$func_args
			);
	}

	////////////////////////////////////////////////////////////////////////////
	// Checkers
	////////////////////////////////////////////////////////////////////////////

	/**
	 * Checks if the current object can have linked elements of the given class.
	 * throws an exception if not.
	 *
	 * @param string $linkedClass
	 *
	 * @return void
	 *
	 * @throws CoreException
	 *
	 * @author Yannick Le Guédart
	 */

	protected function _checkLinkedElementType($linkedClass)
	{
		if (! isset($this->_linkedElement[$linkedClass]))
		{
			throw new jException(
				'jDbObject~exceptions.error.children_not_defined',
				$linkedClass
			);
		}
	}

	////////////////////////////////////////////////////////////////////////////
	// Includer
	////////////////////////////////////////////////////////////////////////////

	/**
	 * Includes the file that contains the class defined in the $definition
	 * array.
	 *
	 * The $definition array must contains a 'selector' entry or the entries
	 * $class and 'module'. If it's not the case, an the methods tries to
	 * include the Classe using the 'core~$classType' selector.
	 *
	 * If not available, a CoreException is thrown.
	 *
	 * @param string $class
	 * @param array  $definition
	 *
	 * @return void
	 *
	 * @throws CoreException
	 *
	 * @author Yannick Le Guédart
	 */

	protected function _include($class, $definition)
	{
		if (isset($definition['selector']))
		{
			jClasses::inc($definition['selector']);
		}
		elseif (isset($definition['module']))
		{
			jClasses::inc($definition['module'] . '~' . $class);
		}
		else
		{
			try
			{
				jClasses::inc("core~$class");
			}
			catch (jException $e)
			{
				throw new jException(
					'jDbObject~exceptions.error.wrong_definition',
					$linkedClass
				);
			}
		}
	}
}

jClasses::inc('jDbObject~jDbObjectOrder');

abstract class jDbObjectQueryComposer
{
	/**
	 * Constantes pour les types de sorties formies par la méthode get
	 */

	const OUTPUT_NORMAL = 1;
	const OUTPUT_RAW	= 2;
	const OUTPUT_JSON	= 3;

	/**
	 * profil DB lié à cette classe. Par défaut à null, ce qui s'ignifie qu'on
	 * utilise le profil par défaut.
	 */

	static protected $_db_profile = null;

	/**
	 * Paramètres de base d'une méthode get
	 *
	 * Ce tableau fixe les paramètres de base d'une requête get à moins d'une
	 * réécriture dans une classe fille. Il n'est pas nécessaire de réécrire
	 * tous les paramètres.
	 *
	 * @see get()
	 *
	 * @access protected
	 * @var    array
	 */

	static public $_getBaseParams =
		array
		(
			'countOnly'			=> true,
			'offset'			=> 0,
			'limit'				=> null,
			'orderType'			=> jDbObjectOrder::NONE,
			'orderAsc'			=> true,

			// Si l'on veut afficher le tableau des paramètres et la requete
			// dans le syslog

			'_debug'			=> false,

			// Fournit la possibilité de limiter la requète utiquement sur
			// les champs qui nous intéressent. Pour que ça marche, il faut
			// passer un tableau avec les champs voulus :
			// array('id', 'parent_id', ...)
			//
			// C'est une option au risque de l'utilisateur.
			// C'est une option ignorée dans le cas d'un getNb()

			'_onlyFields'		=> null,

			// Choix du format des résultats

			'_output'			=> self::OUTPUT_NORMAL,

		);

	/**
	 * Paramètres pour rajouter des données à une méthode get
	 *
	 * Ce tableau définit des données à rajouter aux résultats d'une requete
	 * get. En général, ces champs supplémentaires nécessiteront des jointures
	 * externes à la table de base.
	 *
	 * Par défaut, un paramètre supplémentaire est défini à false. Si l'ont
	 * souhaite ajouter de manière systématique un parmètre à toutes les
	 * requetes d'un _element, il suffit de fixer ce paramètre à true dans
	 * ce tableau.
	 *
	 * @see get()
	 * @see $_getJoins
	 *
	 * @access protected
	 * @var array
	 */

	static public  $_getMoreFieldsParams = array();

	/**
	 * Paramètres pour filtrer les données d'une méthode get
	 *
	 * Ce tableau définit comment les données seront filtrées d'une requete get.
	 * parent_id ou parent_path sont généralement settés par le paramettre
	 * $parentParam de la méthode get, même si un filtrage est toujours possible
	 * en sus. Il est tout à fait possible qu'un filtre nécessite une jointure.
	 *
	 * Si l'on veut qu'une requète get soit filtrée en permanence, il faut
	 * définir le filtre par défaut avec une valeur non nulle.
	 *
	 * @see get()
	 * @see $_getJoins
	 *
	 * @access protected
	 * @var array
	 */

	static public  $_getFilterParams =
		array
		(
			// Filtre récupération sur une liste d'id

			'fromIdArray'				=> null,
		);


	/**
	 * Paramètres pour définir les jointures possibles d'une méthode get
	 *
	 * Ce tableau définit les jointures acceptables pour une requete get. En
	 * général, les jointures sont définies par les paramètres
	 * _getMoreFieldsParams et _getFilterParams qui détermine de quelles tables
	 * on va avoir besoin pour filtre ou rajouter des champs. Il est totalement
	 * possible toutefois de rajouter une jointure en passant un paramètre
	 * idoïne lors d'un appel à une méthode get.
	 *
	 * Par défaut, on définit une jointure à false. Si l'on souhaite que cette
	 * jointure soit systématique, il est possible de la mettre à true.
	 * Attention néanmoins aux conséquences pour les serveurs de base de donnée.
	 *
	 * @see get()
	 * @see $_getMoreFieldsParams
	 * @see $_getFilterParams
	 *
	 * @access protected
	 * @var array
	 */

	static public $_getJoins = array();

	/**
	 * get the jDbConnection handler
	 *
	 * Cette méthode permet de jongler avec les connections vers les bases de
	 * données. On commence par récupérer la connection principale. Dans ce
	 * profil de connexion, il est possible de définir un paramètre 'slave_get'
	 * qui est constitué d'une liste de tables assez statiques pour pouvoir
	 * utiliser un serveur esclave pour les lectures. Si c'est le case on
	 * essaye de changer la connexion.
	 *
	 * @return object
	 *
	 * @author Yannick Le Guédart
	 */

	static protected function _getDb()
	{
		return jDb::getConnection();
	}

	/**
	 * La grande méthode du get, appelée par les méthodes de la classe
	 * jDbObject et toute sa déscendance. Cette méthode utilise les
	 * tableaux de configuration définis ci-dessus
	 *
	 * @param string  $className
	 * @param array   $params
	 *
	 * @return object
	 *
	 * @throws CoreException
	 *
	 * @see $_getBaseParams
	 * @see $_getMoreFieldsParams
	 * @see $_getFilterParams
	 * @see $_getJoins
	 *
	 * @author Yannick Le Guédart
	 */

	static public function get($className, $params = array())
	{
		if (! is_string($className))
		{
			throw new jException(
				'jDbObject~exceptions.error.classname_not_string',
				gettype($className)
			);
		}

		if (! is_array($params))
		{
			throw new jException(
				'jDbObject~exceptions.error.params_not_array',
				gettype($params)
			);
		}

		// Tablename

		if (is_null(get_class_static_property($className, '_tableName')))
		{
			throw new jException(
				'jDbObject~exceptions.error.tablename_not_set',
				$className
			);
		}

		$t = '"' . get_class_static_property($className, '_tableName') . '"';

		// DbClass for the requested object

		$dbClass = $className . "QueryComposer";

		/* ---------------------------------------------------------------------
		 * Génération des tableaux par défaut
		 *
		 * On va se servir de l'héritage pour déterminer dans quel classe la
		 * définition du paramètre se trouvera. On boucle sur l'héritage de
		 * classes  en partant de la plus petite fille.
		 *
		 * Le tableau $baseParams lui est défini de façon directe. Chaque fois
		 * qu'un paramètre est défini dans une classe lors de la boucle, ça
		 * devient le paramètre par défaut pour le get en cours. Lorsqu'on a
		 * bouclé sur l'ensemble de l'architecture, on est certain d'avoir un
		 * tableau de paramètres complets puisque la classe _element_db définit
		 * tous les paramètres.
		 *
		 * Pour les tableaux $moreFieldsDefault, $filterDefault et $joinsArray
		 * sont des tableaux contenant une association entre chaque paramètre et
		 * la classe dans laquelle il est défini. On utilisera cette information
		 * par la suite pour générer les bouts de la requête SQL en appelant les
		 * méthodes appropriées dans la classe qui définit se paramètre.
		 * ---------------------------------------------------------------------
		 */

		// Tableaux de paramètres par défaut.

		$baseDefault 		= array();
		$moreFieldsDefault  = array();
		$filterDefault		= array();
		$joinsDefault		= array();

		// Tableaux de paramètres réels.

		$moreFieldsParams 	= array();
		$filterParams 		= array();
		$joins 				= array();

		$dbcLoop = $dbClass;

		while ($dbcLoop)
		{
			/* -----------------------------------------------------------------
			 * Génération du tableau des paramètres de base par défaut.
			 *
			 * On boucle sur les paramètres du tableau _getBaseParams de la
			 * classe en cours de traitement. Si une association clef/valeur
			 * non définie est rencontrée, on l'insère dans le tableau des
			 * paramètres de base par défaut ($baseDefault)
			 * -----------------------------------------------------------------
			 */

			foreach (
				get_class_static_property($dbcLoop, '_getBaseParams')
					as $k => $v)
			{
				if (! isset($baseDefault[$k]))
				{
					$baseDefault[$k] = $v;
				}
			}

			/* -----------------------------------------------------------------
			 * Génération du tableau des paramètres d'ajout de champs par
			 * défaut.
			 *
			 * On boucle sur les paramètres du tableau _getMoreFieldsParams de la
			 * classe en cours de traitement. Si une association clef/valeur
			 * non définie est rencontrée, on l'insère dans le tableau des
			 * paramètres d'ajout de champs par défaut ($moreFieldsDefault), en
			 * associant à la clef du paramètre la classe dans lequel ce
			 * paramètre est défini. On se servira de cette association par la
			 * suite pour définir l'endroit où trouver le code nécessaire à
			 * la construction de la requète pour ce paramètre.
			 *
			 * Si un élément est déjà défini dans une classe fille, on l'ignore.
			 *
			 * On procède de la même manière pour les paramètres de filtres par
			 * défaut et les paramètres de jointures ($filterDefault et
			 * $joinsDefault)
			 *
			 * Il peut arriver qu'on se retrouve avec un filtre possible qui
			 * soit activé par défaut. Pour cette raison, si une clef de filtre
			 * est non nulle, on met à jour l'entrée correspondante dans le
			 * tableau $filterParams. Elle sera écrasée si les paramètres de la
			 * méthode get sont ainsi définis, mais dans le cas contraire, on
			 * aura quand même le filtre correctement positionné.
			 * -----------------------------------------------------------------
			 */

			foreach (
				get_class_static_property($dbcLoop, '_getMoreFieldsParams')
					as $k => $v)
			{
				if (! isset($moreFieldsDefault[$k]))
				{
					$moreFieldsDefault[$k] = $dbcLoop;
				}
			}

			foreach (
				get_class_static_property($dbcLoop, '_getFilterParams')
					as $k => $v)
			{
				if (! isset($filterDefault[$k]))
				{
					$filterDefault[$k] = $dbcLoop;

					if (! is_null($v))
					{
						$filterParams[$k] = $v;
					}
				}
			}

			foreach (
				get_class_static_property($dbcLoop, '_getJoins')
					as $k => $v)
			{
				if (! isset($joinsDefault[$k]))
				{
					$joinsDefault[$k] = $dbcLoop;
				}
			}

			/* -----------------------------------------------------------------
			 * Les mises à jour des tableaux par défaut étant effectuées, on
			 * passe à la classe parent. S'il n'y a pas de classe parente,
			 * $dbcLoop va se retrouver à false, et on va donc sortir de la
			 * boucle
			 *
			 * Si on en est à jDbObjectQueryComposer, on sort
			 * -----------------------------------------------------------------
			 */

			if ($dbcLoop === 'jDbObjectQueryComposer')
			{
				break;
			}

			$dbcLoop = get_parent_class($dbcLoop);
		}

		/* ---------------------------------------------------------------------
		 * Génération des tableaux de paramètres réels
		 *
		 * En parcourant le tableau $param passé en paramètre de la méthode, on
		 * va mettre à jour les tableau de paramètres réels si le tableau existe
		 * réellement dans le tableau des paramètres par défaut.
		 *
		 * On affecte les parametres de base par défaut aux tableau des
		 * paramètres de base réels au préalable, dont on écrasera les valeurs
		 * par la suite si nécessaire.
		 * ---------------------------------------------------------------------
		 */

		$baseParams = $baseDefault;

		foreach ($params as $k => $v)
		{
			if (array_key_exists($k, $baseParams))
			{
				$baseParams[$k] = $v;
			}
			elseif (array_key_exists($k, $moreFieldsDefault))
			{
				$moreFieldsParams[$k] = $v;
			}
			elseif (array_key_exists($k, $filterDefault))
			{
				$filterParams[$k] = $v;
			}
			elseif (array_key_exists($k, $joinsDefault))
			{
				// Nothing to do but valid
			}
			else
			{
				throw new jException(
					'jDbObject~exceptions.error.invalid_parameter',
					$k,
					$t
				);
			}
		}

		/* ---------------------------------------------------------------------
		 * Génération du tableau de bouts de requetes SQL utilisées poru les
		 * filtres.
		 *
		 * Pour chaque paramètre du tableau $filterParams, on appelle la méthode
		 * _getFilterSQL de la classe définie dans $filterDefault pour récupérer
		 * la définition du paramètre et récupérer le morceau de requète SQL
		 * approprié. Il peut arriver que certains paramètres ne nécessitent
		 * aucun code SQL, mais par exemple simplement la création d'une
		 * jointure. Dans ce cas la méthode _getFilterSQL est censée renvoyer
		 * null, et le filtre n'est pas ajouté dans le tableau $filterSQL
		 * ---------------------------------------------------------------------
		 */

		$filterSQLArray = array();

		foreach ($filterParams as $k => $v)
		{
			if (! is_null($v))
			{
				$oneFilter =
					call_user_func_array
					(
						array
						(
							$filterDefault[$k],
							'_getFilterSQL'
						),
						array
						(
							$k,
							$v,
							$t,
							$filterParams,
							&$joins,
							&$moreFieldsParams
						)
					);

				if (! is_null($oneFilter) and ($oneFilter !== ''))
				{
					$filterSQLArray[] = $oneFilter;
				}
			}
		}

		if (count($filterSQLArray) > 0)
		{
			$filterSQL = "WHERE " . join(' AND ', array_unique($filterSQLArray));
		}
		else
		{
			$filterSQL = '';
		}

		/* ---------------------------------------------------------------------
		 * Gestion des paramètres par défaut.
		 *
		 * C'est ici qu'on va gérer les options par défaut.
		 *
		 * Tout d'abord, $baseParams['countOnly'] détermine si l'ont fait un
		 * décompte ou si l'on veut des éléments. Dans le cas d'un décompte, on
		 * peut ignorer tous les paramètres du tableau de base, mais aussi tous
		 * paramètres de champs supplémentaires.
		 *
		 * On commence à construire la chaine des champs à recupérer dans la
		 * requète, la chaine liée à l'order by, ainsi que les offset et limit.
		 * ---------------------------------------------------------------------
		 */

		$orderSQL 	= null;
		$offsetSQL 	= '';
		$limitSQL 	= '';

		if ($baseParams['countOnly'] === true)
		{
			$fieldSQL = array("COUNT ($t.*) AS c");

			/* -----------------------------------------------------------------
			 * Order by. On appelle la méthode de la classe appelée
			 * Sauf que tant qu'on n'a pas de résultat non null, il faut
			 * remonter dans les classes.
			 *
			 * Par contre on ignore le résultat. on n'appelle que pour que les
			 * jointures soient appliquées
			 * -----------------------------------------------------------------
			 */

			$dbcLoop = $dbClass;

			while (is_null($orderSQL))
			{
				call_user_func_array
				(
					array
					(
						$dbcLoop,
						'_getOrderSQL'
					),
					array
					(
						$t,
						$baseParams['orderType'],
						$baseParams['orderAsc'],
						&$joins,
						&$moreFieldsParams
					)
				);

				if ($dbcLoop === 'jDbObjectQueryComposer')
				{
					break;
				}

				$dbcLoop = get_parent_class($dbcLoop);
			}

			/* -----------------------------------------------------------------
			 * Gestion des paramètres de champs supplémentaires.
			 *
			 * Encore une fois, sur un getNb, on ne récupérera aucun champ
			 * supplémentaire mais il faut quand même faire les jointures
			 * nécessaires
			 * -----------------------------------------------------------------
			 */

			foreach ($moreFieldsParams as $k => $v)
			{
				if ($v !== false)
				{
					call_user_func_array
					(
						array
						(
							$moreFieldsDefault[$k],
							'_getMoreFieldsSQL'
						),
						array
						(
							$k,
							$v,
							$t,
							$filterParams,
							$moreFieldsParams,
							&$joins
						)
					);
				}
			}
		}
		else
		{
			/* -----------------------------------------------------------------
			 * Offset / Limit
			 *
			 * Un offset de 0 ou un offset non numérique est ignoré.
			 * -----------------------------------------------------------------
			 */

			if (is_numeric($baseParams['offset'])
					and ($baseParams['offset'] > 0))
			{
				$offsetSQL 	= 'OFFSET ' . $baseParams['offset'];
			}

			if (! is_null($baseParams['limit']))
			{
				$limitSQL = 'LIMIT ' . $baseParams['limit'];
			}

			/* -----------------------------------------------------------------
			 * Order by. On appelle la méthode de la classe appelée
			 * Sauf que tant qu'on n'a pas de résultat non null, il faut
			 * remonter dans les classes.
			 * -----------------------------------------------------------------
			 */

			$dbcLoop = $dbClass;

			while (is_null($orderSQL))
			{
				$orderSQL =
					call_user_func_array
					(
						array
						(
							$dbcLoop,
							'_getOrderSQL'
						),
						array
						(
							$t,
							$baseParams['orderType'],
							$baseParams['orderAsc'],
							&$joins,
							&$moreFieldsParams
						)
					);

				if ($dbcLoop === 'jDbObjectQueryComposer')
				{
					break;
				}

				$dbcLoop = get_parent_class($dbcLoop);
			}

			/* -----------------------------------------------------------------
			 * Gestion des paramètres de champs supplémentaires.
			 *
			 * Par défaut, $fieldSQL est égal à "$t.*" pour récupérer toutes les
			 * colonnes de la table. Par contre, si le paramètre _onlyFields est
			 * renseigné, on ne récupère que les champs voulus.
			 *
			 * Pour chaque paramètre du tableau $moreFieldsParams, on appelle la
			 * méthode _getMoreFieldsSQL de la classe définie dans
			 * $moreFieldsDefault pour récupérer la définition du paramètre et
			 * récupérer le morceau de requète SQL approprié. Il peut arriver
			 * que certains paramètres ne nécessitent aucun code SQL, mais par
			 * exemple simplement la création d'une jointure. Dans ce cas la
			 * méthode _getFi_getMoreFieldsSQLlterSQL est censée renvoyer null
			 * ou, une chaine vide. dans ce cas là, aucune entrée n'est ajoutée
			 * au tableau des champs $fieldSQL
			 * -----------------------------------------------------------------
			 */

			if  (
					is_array($baseParams['_onlyFields'])
					and
					(count($baseParams['_onlyFields']) > 0)
				)
			{
				$fieldSQL = array();

				foreach ($baseParams['_onlyFields'] as $field)
				{
					$fieldSQL[] = "$t.$field";
				}
			}
			else
			{
				$fieldSQL = array("$t.*");
			}

			foreach ($moreFieldsParams as $k => $v)
			{
				//if ($v === true)
				if ($v !== false)
				{
					$oneField =
						call_user_func_array
						(
							array
							(
								$moreFieldsDefault[$k],
								'_getMoreFieldsSQL'
							),
							array
							(
								$k,
								$v,
								$t,
								$filterParams,
						        $moreFieldsParams,
								&$joins
							)
						);

					if (($oneField !== '') and ! is_null($oneField))
					{
						$fieldSQL[] = $oneField;
					}

				}
			}
		}

		/* ---------------------------------------------------------------------
		 * Gestion des jointures.
		 *
		 * On va construire le tableau des jointures nécessaires pour exécuter
		 * notre requète
		 *
		 * Pour chaque paramètre du tableau $joinsDefault, si la jointure est
		 * définie dans le tableau $joins et qu'elle est à vrai, on appelle la
		 * méthode _getJoinSQL de la classe définie dans $joinsDefault pour
		 * récupérer la définition de la jointure, qu'on ajoute ensuite dans le
		 * tableau $joinSQLArray
		 * ---------------------------------------------------------------------
		 */

		$joinSQLArray 		= array();
		$additionnalFilters	= array();

		foreach ($joinsDefault as $k => $dbc)
		{
			if (isset($joins[$k]) and ($joins[$k] === true))
			{
				$joinSQLArray[] =
					call_user_func_array
					(
						array
						(
							$dbc,
							'_getJoinSQL'
						),
						array
						(
							$k,
							$v,
							$t,
							$joins,
							&$additionnalFilters
						)
					);
			}
		}

		// Si les jointures nécessitent de nouveaux filtres on les traite ici

		if (count($additionnalFilters) > 0)
		{
			if ($filterSQL === '')
			{
				$filterSQL = "WHERE " . join(' AND ', $additionnalFilters);
			}
			else
			{
				$filterSQL .= " AND " . join(' AND ', $additionnalFilters);
			}

		}

		$joinSQL = join("\n", $joinSQLArray);

		/* ---------------------------------------------------------------------
		 * Création de la requête SQL.
		 * ---------------------------------------------------------------------
		 */

		$sql = "
			SELECT " . join(',', $fieldSQL) . "
			FROM   $t
			$joinSQL
			$filterSQL
			$orderSQL
			$offsetSQL
			$limitSQL";

		/* ---------------------------------------------------------------------
		 * Affichage du débug
		 * ---------------------------------------------------------------------
		 */

		if ($baseParams['_debug'] === true)
		{
			syslog(LOG_DEBUG, print_r($params, true));
			syslog(LOG_DEBUG, $sql);

			if (1 && print_r($params, true)) {}
			if (1 && print_r($sql, true)) {}

			trigger_error(
				print_r($params, true)
			);

			trigger_error($sql);
		}

		/* ---------------------------------------------------------------------
		 * Exécution de la requête SQL.
		 *
		 * En fonction du type de requète, $baseParams['countOnly'] ou pas, on
		 * revoit un entier ou un ArrayObject.
		 * ---------------------------------------------------------------------
		 */

		$db = call_user_func_array(array($dbClass, '_getDb'), array());
		$rs = $db->query($sql);

		if ($baseParams['countOnly'])
		{
			$record = $rs->fetch ();

			return intval($record->c);
		}
		else
		{
			$returnArray = array ();

			$record = $rs->fetch ();

			while ($record)
			{
				/* -------------------------------------------------------------
				 * Pour certains get, on a parfois des opérations à effectuer
				 * après la requète (par exemple pour mettre à jour des champs)
				 *
				 * Pour chaque record récupéré, on exécute la méthode
				 * _getAfterQuery de la classe fille et on récupère le record
				 * ainsi modifié.
				 * -------------------------------------------------------------
				 */

				call_user_func_array
				(
					array
					(
						$dbClass,
						'_getAfterQuery'
					),
					array
					(
						$record
					)
				);

				if ($baseParams['_output'] === self::OUTPUT_NORMAL)
				{
					$returnArray[] = new $className($record);
				}
				elseif ($baseParams['_output'] === self::OUTPUT_RAW)
				{
					$returnArray[] = $record;
				}
				else
				{
					// Que faire si l'output n'est pas défini ? CoreException ?
				}

				$record = $rs->fetch();
			}

			return new ArrayObject($returnArray);
		}
	}

	/**
	 * _getFilterSQL
	 *
	 * Cette méthode prend en entrée une clef et une valeur de filtrage pour
	 * la méthode get.
	 *
	 * Le paramètre $filterParams est passé en paramètre en lecture seul, car
	 * il est possible pour un filtre sur un type donné, d'optimiser la requête
	 * si l'on dispose d'une autre variable.
	 *
	 * Le paramètre $join correspond au tableau de jointures. Il est passé par
	 * référence, car l'ajout d'un filtre peut necessiter l'ajout d'une
	 * jointure sur la table concernée.
	 *
	 * Le paramètre $moreFieldsParams correspond au tableau d'ajout de champs.
	 * Il est passé par référence, car l'ajout d'un filtre peut necessiter
	 * l'ajout d'un ou plusieurs champs à la requête.
	 *
	 * @param string 	$k					Nom du filtre
	 * @param mixed 	$v					Valeur du filtre
	 * @param string 	$t					Table concerné par le get
	 * @param integer  	$filterParams		Tableau de filtrage
	 * @param array 	&$joins				Tableau de jointures
	 * @param array		&$moreFieldsParams	Tableau de champs supplémentaires
	 *
	 * @return string   filtre SQL à intégrer la requete SQL dans le WHERE.
	 *
	 * @see get()
	 * @see $_getFilterParams
	 *
	 * @access public
	 * @author Yannick Le Guédart <yannick@over-blog.com>
	 * @version 1.1
	 */

	static public function _getFilterSQL (
        $k,
        $v,
		$t,
		$filterParams,
		&$joins,
		&$moreFieldsParams)
	{
		$db = self::_getDb();

		$filterSQL = null;

		switch ($k)
		{
			case 'fromIdArray':
				if (is_array($v))
				{
					$joins['_idArray'] = $v;

					if (count($v) > 0)
					{
						$filterSQL = "
							$t.id IN ( " . join (', ', $v) . ") ";
					}
					else // empty array => no result
					{
						$filterSQL = "FALSE";
					}
				}
				else
				{
					throw new jException(
						'jDbObject~exceptions.error.fromidarray_param_not_array'
					);
				}
				break;
		}

		return $filterSQL;
	}

	/**
	 * _getOrderSQL
	 *
	 * Cette méthode génère la partie ORDER BY de la requète get
	 *
	 * Le paramètre $join correspond au tableau de jointures. Il est passé par
	 * référence, car l'ajout d'un filtre peut necessiter l'ajout d'une
	 * jointure sur la table concernée.
	 *
	 * Le paramètre $moreFieldsParams correspond au tableau d'ajout de champs.
	 * Il est passé par référence, car l'ajout d'un filtre peut necessiter
	 * l'ajout d'un ou plusieurs champs à la requête.
	 *
	 * Les requètes _getOrderSQL dans des classes filles doivent permettre de
	 * remonter à la méthode mère en utilisant le code suivant pour le paramètre
	 * default du switch.
	 *
	 * <code>
	 * 		return
	 * 			parent::_getOrderSQL
	 * 			(
	 *				$t,
	 *				$orderType,
	 * 				$orderAsc,
	 *				$joins,
	 *	 			$moreFieldsParams
	 *	 		);
	 * </code>
	 *
	 * @param string 	$t					Table concerné par le get
	 * @param integer  	$orderType			type d'ordre (cf. {@link Order})
	 * @param boolean  	$orderAsc			ASC/DESC
	 * @param array 	&$joins				Tableau de jointures
	 * @param array		&$moreFieldsParams	Tableau de champs supplémentaires
	 *
	 * @return string   Partie ORDER BY de la requète get
	 *
	 * @todo rajouter une exception sur le default quand toutes les méthodes
	 * filles sont à jour.
	 * @todo Remplacer date_creation par id dans le ORDER_TYPE_DATE_CREATION.
	 *
	 * @link Order
	 * @see get()
	 * @see $_getBaseParams
	 *
	 * @author Yannick Le Guédart
	 */

	static public function _getOrderSQL (
		$t,
		$orderType,
		$orderAsc,
		&$joins,
		&$moreFieldsParams)
	{
		$orderAscSQL = (($orderAsc)?"ASC":"DESC");

		$orderSQL = '';

		switch ($orderType)
		{
			case jDbObjectOrder::ID:
				$orderSQL = "ORDER BY $t.id $orderAscSQL";
				break;
			case jDbObjectOrder::ID_ARRAY:
				if (! isset($joins['_idArray']))
				{
					self::_coreExceptionStatic(44);
				}
				elseif (count($joins['_idArray']) === 0)
				{
					$orderSQL = '';
				}
				else
				{
					$orderSQL = "ORDER BY CASE $t.id ";
					foreach($joins['_idArray'] as $i => $id)
					{
						$orderSQL .= "WHEN $id THEN $i ";
					}
					$orderSQL .= "ELSE " . ++$i . " END $orderAscSQL";
				}
				break;
			case jDbObjectOrder::NONE:
			default:
				$orderSQL = '';
				break;
		}

		return $orderSQL;
	}

	/**
	 * _getMoreFieldsSQL
	 *
	 * Cette méthode prend en entrée une clef et une valeur pour l'ajout de
	 * champs supplémentaires à une requète get.
	 *
	 * Le paramètre $filterParams est passé en paramètre en lecture seule, car
	 * il est possible pour un filtre sur un type donné, d'optimiser la requête
	 * si l'on dispose d'une autre variable.
	 *
	 * Le paramètre $moreFieldsParams correspond au tableau d'ajout de champs.
	 * Il est passéen lecture seule car il est possible pour un filtre sur un
	 * type donné, d'optimiser la requête si l'on dispose d'une autre variable.
	 *
	 * Le paramètre $join correspond au tableau de jointures. Il est passé par
	 * référence, car l'ajout d'un champ peut necessiter l'ajout d'une
	 * jointure sur la table concernée.
	 *
	 * @param string 	$k					Nom du filtre
	 * @param mixed 	$v					Valeur du filtre
	 * @param string 	$t					Table concerné par le get
	 * @param integer  	$filterParams		Tableau de filtrage
	 * @param array		$moreFieldsParams	Tableau de champs supplémentaires
	 * @param array 	&$joins				Tableau de jointures
	 *
	 * @return string   champ(s) SQL à rajouter au SELECT.
	 *
	 * @see get()
	 * @see $_getMoreFieldsParams
	 *
	 * @author Yannick Le Guédart
	 */

	static public function _getMoreFieldsSQL (
        $k,
        $v,
		$t,
		$filterParams,
        $moreFieldsParams,
		&$joins)
	{
		$fieldSQL = null;

		return $fieldSQL;
	}

	/**
	 * Cette méthode prend en entrée une clef et une valeur pour l'ajout de
	 * jointures à une requète get.
	 *
	 * Le tableau $join est passé en paramètre en lecture car il est susceptible
	 * de contenir des informations nécessaire à l'optimisation de la requète
	 * en construction
	 *
	 * @param string 	$k		Nom du filtre
	 * @param mixed 	$v		Valeur du filtre
	 * @param string 	$t		Table concerné par le get
	 * @param array 	$joins	Tableau de jointures
	 * @param array 	$additionnalFilters Filtres additionnels
	 *
	 * @return string   jointure(s) SQL à rajouter au SELECT.
	 *
	 * @author Yannick Le Guédart <yannick@over-blog.com>
	 */

	static public function _getJoinSQL(
        $k,
        $v,
        $t,
		$joins,
        &$additionnalFilters
	)
	{
		$joinSQL = '';

		return $joinSQL;
	}


	/**
	 * _getAfterQuery
	 *
	 * Special handling of fields 'after' the query. Called for each record
	 *
	 * @param object  &$record
	 *
	 * @return void
	 *
	 * @author Yannick Le Guédart
	 */

	static public function _getAfterQuery (&$record)
	{
	}
}


function get_class_constant($className, $constantName)
{
	$class = new ReflectionClass($className);

	return $class->getConstant($constantName);
}

/**
 * This function returns the value of a given static property defined in the
 * class named $className. Uses Reflection.
 *
 * @param string $className
 * @param string $propertyName
 *
 * @return mixed
 *
 * @author Yannick Le Guédart
 */

function get_class_static_property($className, $propertyName)
{
	static $allProperties = array();

	if (! isset($allProperties[$className]))
	{
		$class = new ReflectionClass($className);

		$allProperties[$className] = $class->getStaticProperties();
	}

	return $allProperties[$className][$propertyName];
}

/**
 * Just because php 5.2 sucks...
 *
 * @return mixed
 *
 * @link http://www.php.net/manual/fr/function.get-called-class.php
 */

if (!function_exists('get_called_class'))
{
	function get_called_class($bt = false,$l = 1) {
		if (!$bt) $bt = debug_backtrace();
		if (!isset($bt[$l]))
			throw new Exception("Cannot find called class -> stack level too deep.");
		if (!isset($bt[$l]['type'])) {
			throw new Exception ('type not set');
		}
		else switch ($bt[$l]['type']) {
			case '::':
				if (! isset($bt[$l]['file'])) {var_dump($bt[$l], $bt[$l+1]); die();}
				$lines = file($bt[$l]['file']);
				$i = 0;
				$callerLine = '';
				do {
					$i++;
					$callerLine = $lines[$bt[$l]['line']-$i] . $callerLine;
				} while (stripos($callerLine,$bt[$l]['function']) === false);
				preg_match('/([a-zA-Z0-9\_]+)::'.$bt[$l]['function'].'/',
							$callerLine,
							$matches);
				if (!isset($matches[1])) {
					// must be an edge case.
					throw new Exception ("Could not find caller class: originating method call is obscured.");
				}
				switch ($matches[1]) {
					case 'self':
					case 'parent':
						return get_called_class($bt,$l+1);
					default:
						return $matches[1];
				}
				// won't get here.
			case '->': switch ($bt[$l]['function']) {
					case '__get':
						// edge case -> get class of calling object
						if (!is_object($bt[$l]['object']))
							throw new Exception (
								"Edge case fail. __get called on non object."
							);
						return get_class($bt[$l]['object']);
					default: return $bt[$l]['class'];
				}

			default: throw new Exception ("Unknown backtrace method type");
		}
	}
}
