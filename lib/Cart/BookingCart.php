<?php
/*
This file is part of CMS Made Simple module: Booker.
Copyright(C) 2014-2016 Tom Phane <tpgww@onepost.net>
Refer to licence and other details at the top of file Booker.module.php
More info at http://dev.cmsmadesimple.org/projects/booker
*/
namespace Riesenia\Cart;

class BookingCartItem implements BookingCartItemInterface
{
	private $name;
	private $type;
	private $identifier;
	private $price;
	private $taxrate;
	//internal use only
	private $context = NULL;
	private $quantity = 0;

	public function __construct($name = '', $type = '',	$price = 0.0, $taxrate = 0.0)
	{
		$this->name = ($name) ? $name : 'Anonymous cart item';
		$this->type = ($type) ? $type : 'Untyped cart item';
		$this->identifier = uniqid(get_class().$this->name.$this->type);
		$this->price = $price;
		$this->taxrate = $taxrate;
	}

	/**
	Get this item's unique identifier

	@return mixed
	*/
	public function getCartId()
	{
		return $this->identifier;
	}

	public function setCartType($type)
	{
		$this->type = ($type) ? $type : 'Untyped cart item';
		$this->identifier = uniqid(get_class().$this->name.$this->type);
	}

	/**
	Get this item's type for discrimination within the cart

	@return string
	*/
	public function getCartType()
	{
		return $this->type;
	}

	public function setCartName($name)
	{
		$this->name = ($name) ? $name : 'Anonymous cart item';
		$this->identifier = uniqid(get_class().$this->name.$this->type);
	}

	/**
	Get this item's name

	@return string
	*/
	public function getCartName()
	{
		return $this->name;
	}

	/**
	Set cart-context for this item

	@param mixed
	@return void
	*/
	public function setCartContext($context)
	{
		$this->context = $context;
	}

	/**
	Get this item's context

	@return mixed
	*/
	public function getCartContext()
	{
		return $this->context;
	}

	/**
	Log the no. of this item in the cart

	@param int
	@return void
	*/
	public function setCartQuantity($quantity)
	{
		$this->quantity = $quantity;
	}

	/**
	Get the no. of this item in the cart

	@return int
	*/
	public function getCartQuantity()
	{
		return (int) $this->quantity;
	}

	public function setUnitPrice($price)
	{
		$this->price = (float)$price;
	}

	/**
	Get this item's unit-price (perhaps based on quantity and context)

	@return float
	*/
	public function getUnitPrice()
	{
		return (float)$this->price;
	}

	public function setTaxRate($taxrate)
	{
		$this->taxrate = (float)$taxrate;
	}

	/**
	Get this item's tax rate (percentage)

	@return float
	*/
	public function getTaxRate()
	{
		return (float)$this->taxrate;
	}
}

class BookingCart extends Cart
{
	/**
	Overloaded cache-properties
	*/
	private $_xtraprops = [];

	/**
	Recognised properties of cache-items

	@var array, members each an array propname=>additive, additive = FALSE returns mere count when totalled
	*/
	protected $_properties = [['price'=>TRUE], ['tax'=>TRUE], ['weight'=>TRUE], ['count'=>FALSE]];

	/**
	Constructor

	@param mixed context data (passed to cart items for custom price logic)
	@param bool TRUE if prices are listed as gross
	@param int number of decimals used for rounding
	*/
	public function __construct($context = NULL, $pricesWithTax = TRUE, $roundingDecimals = 2)
	{
		parent::__construct($context, $pricesWithTax, $roundingDecimals);
	}

	/**
	Cart-property overloading
	*/
	public function __set($name, $value)
	{
		$this->_xtraprops[$name] = $value;
		$this->_cartModified();
	}

	public function __get($name)
	{
		return (array_key_exists($name, $this->_xtraprops)) ? $this->_xtraprops[$name] : NULL;
	}

	public function __isset($name)
	{
		return (array_key_exists($name, $this->_xtraprops));
	}

	public function __unset($name)
	{
		unset($this->_xtraprops[$name]);
		$this->_cartModified();
	}

	/**
	Set property value for item|all, after adding the property type if necessary

	@param string propname
	@param mixed value
	@param string type, maybe ','-separated, leading ~ to negate
	@param CartItemInterface | callable filter | NULL
	@return void
	*/
	public function setProperty($propname, $value, $type = '~', $item = NULL)
	{
//CHECKME $this->_properties relevance, if totalling wanted
		$items = $this->getItemsByType($type);
		if ($items) {
			if ($item && $item instanceof CartItemInterface) {
				if (in_array($item, $items, TRUE)) {
					$item->$propname = $value;
				}
			} else {
				if ($item && is_callable($item)) {
					$items = array_filter($items, $item);
				}
				foreach ($items as &$one) {
					$one->$propname = $value;
				}
				unset($one);
			}
		}
	}

 	/**
	Remove property value from item|all

	@param string propname
	@param string type, maybe ','-separated, leading ~ to negate
	@param CartItemInterface | callable filter | NULL
	@return void
	*/
	public function deleteProperty($propname, $type = '~', $item = NULL)
	{
//CHECKME $this->_properties relevance, if totalling wanted
		$items = $this->getItemsByType($type);
		if ($items) {
			if ($item && $item instanceof CartItemInterface) {
				if (in_array($item, $items, TRUE)) {
					unset($item->$propname);
				}
			} else {
				if ($item && is_callable($item)) {
					$items = array_filter($items, $item);
				}
				foreach ($items as &$one) {
					unset($one->$propname);
				}
				unset($one);
			}
		}
	}

	/**
	Get property value for item

	@param string propname
	@param string type, maybe ','-separated, leading ~ to negate
	@param BookingCartItemInterface item
	@return mixed
	*/
	public function getProperty($propname, $type = '~', BookingCartItemInterface $item)
	{
		$items = $this->getItemsByType($type);
   		if ($items) {
			if (in_array($item, $items, TRUE)) {
				if (isset($item->$propname)) {
					return $item->$propname;
				}
			}
		}
		return NULL; //if not found
	}

	/**
	Get boolean indicating whether item has named property

	@param string propname
	@param string type, maybe ','-separated, leading ~ to negate
	@param BookingCartItemInterface item
	@return boolean
	*/
	public function hasProperty($propname, $type = '~', BookingCartItemInterface $item)
	{
		$items = $this->getItemsByType($type);
   		if ($items) {
			if (in_array($item, $items, TRUE)) {
				return isset($item->$propname);
			}
		}
		return FALSE;
	}

	/**
	Get json-encoded hash of all cart & item properties, for cacheing

	@return string
	*/
	public function collapseCart()
	{
		$itemprops = [];
		foreach ($this->_items as $item) {
			//$identifier property is recreated at erection
			$itemprops[] = [
			 'name' => $item->getCartName(),
			 'type' => $item->getCartType(),
			 'price' => $item->getUnitPrice(),
			 'taxrate' => $item->getTaxRate(),
			 'context' => $item->getCartContext(),
			 'quantity' => $item->getCartQuantity()
			];
		}
		$props = get_object_vars($this);
		$props['[_items]'] = $itemprops;
		$json = json_encode($props);
		return $json;
	}

	/**
	Incrementally populate all cart & item properties from supplied json (e.g. from cache)

	@param string json
	@return void
	*/
	public function erectCart($json)
	{
		if ($json) {
			$props = json_decode($json);
			if ($props !== NULL) {
				foreach($props as $name=>$val)
				{
					$key = trim($name,'[]');
					if ($key != '_items') {
						$this->$key = $val; //don't bother typing ?
					} else {
						$item = new BookingCartItem();
						foreach($val as $name=>$itmval) {
							$key = trim($name,'[]');
							switch($key)
							{
								case 'name': $item->setCartName($itmval); break;
								case 'type': $item->setCartType($itmval); break;
								case 'price':  $item->setUnitPrice($itmval); break;
								case 'taxrate': $item->setTaxRate($itmval); break;
								case 'context': $item->setCartContext($itmval); break;
								case 'quantity': $item->setCartQuantity($itmval); break;
								default: break 3;
							}
						}
						$id = $item->getCartId();
						$this->_items[$id] = $item;
					}
				}
			}
			throw new \Exception('Invalid property data for inflateCart');
		} else {
			throw new \BadMethodCallException('Missing property data for inflateCart');
		}
	}
}
?>
