<?php
#----------------------------------------------------------------------
# Module: Booker - a resource booking module
# Library file: BookingCartItem
#----------------------------------------------------------------------
# See file Booker.module.php for full details of copyright, licence, etc.
#----------------------------------------------------------------------
namespace Booker\Cart;

class BookingCartItem implements BookingCartItemInterface
{
	public $name;
	public $type;
	public $price;
	public $taxrate;
	//internal use only
	public $context = NULL;
	public $quantity = 0;

	public function __construct($name = '', $type = '',	$price = 0.0, $taxrate = 0.0)
	{
		$this->name = ($name) ? $name : 'Anonymous cart item';
		$this->type = ($type) ? $type : 'Untyped cart item';
		$this->price = $price;
		$this->taxrate = $taxrate;
	}

	/**
	Get this item's unique identifier, cannot be 'randomised' or else its contents cannot be updated

	@return mixed
	*/
	public function getCartId()
	{
		$id = hash('md4',get_class().$this->name.$this->type); //fastest conversion
		return $id;
	}

	public function setCartType($type)
	{
		$this->type = ($type) ? $type : 'Untyped cart item';
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

class BookingCart extends Cart implements \Serializable
{
	/**
	Overloaded cart-properties
	*/
	private $xtraprops = array();

	/**
	Recognised properties of cart-items

	@var array, members each an array propname=>additive, additive = FALSE returns mere count when totalled
	*/
	protected $properties = array('price'=>TRUE, 'tax'=>TRUE, 'weight'=>TRUE, 'count'=>FALSE);

	/**
	Constructor

	@param mixed context data (passed to cart items for custom price logic)
	@param bool TRUE if prices are listed as gross
	@param int number of decimals used for rounding
	*/
	public function __construct($context=NULL, $pricesWithTax=TRUE, $roundingDecimals=2)
	{
		parent::__construct($context, $pricesWithTax, $roundingDecimals);
	}

	/**
	Cart-property overloading
	*/
	public function __set($name, $value)
	{
		$this->xtraprops[$name] = $value;
		$this->cartModified();
	}

	public function __get($name)
	{
		return (array_key_exists($name, $this->xtraprops)) ? $this->xtraprops[$name] : NULL;
	}

	public function __isset($name)
	{
		return (array_key_exists($name, $this->xtraprops));
	}

	public function __unset($name)
	{
		unset($this->xtraprops[$name]);
		$this->cartModified();
	}

	/**
	Set property value for item|all, after adding the property type if necessary

	@param string propname
	@param mixed value
	@param string type, maybe ','-separated, leading ~ to negate
	@param CartItemInterface | callable filter | NULL
	@return void
	*/
	public function setProperty($propname, $value, $type='~', $item=NULL)
	{
//CHECKME $this->properties relevance, if totalling wanted
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
	public function deleteProperty($propname, $type='~', $item=NULL)
	{
//CHECKME $this->properties relevance, if totalling wanted
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
	public function getProperty($propname, $type='~', BookingCartItemInterface $item)
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
	public function hasProperty($propname, $type='~', BookingCartItemInterface $item)
	{
		$items = $this->getItemsByType($type);
   		if ($items) {
			if (in_array($item, $items, TRUE)) {
				return isset($item->$propname);
			}
		}
		return FALSE;
	}

	public function __toString()
	{
		return json_encode(get_object_vars($this));
	}

	/**
	Incrementally populate all cart & item properties from supplied json (e.g. from cache)

	@param string json
	@param bool except whether to throw exception upon failure
	@return void
	*/
	public function restoreCart($json,$except=TRUE)
	{
		if ($json) {
			$props = json_decode($json);
			if ($props !== NULL) {
				$arr = (array)$props;
				foreach ($arr as $key=>$one) {
					if ($key != 'items')
						$this->$key = (is_object($one)) ? (array)$one : $one;
					else {
						$one = (array)$one;
						foreach ($one as $itmdata) {
							$item = new BookingCartItem();
							foreach ($itmdata as $key=>$itmval) {
								switch ($key) {
									case 'name': $item->setCartName($itmval); break;
									case 'type': $item->setCartType($itmval); break;
									case 'price':  $item->setUnitPrice($itmval); break;
									case 'taxrate': $item->setTaxRate($itmval); break;
									case 'context': $item->setCartContext($itmval); break;
									case 'quantity': $item->setCartQuantity($itmval); break;
									default: throw new \Exception('Invalid property data for inflateCart');
								}
							}
							$id = $item->getCartId();
							$this->items[$id] = $item;
						}
					}
				}
                return;
			}
			if ($except)
				throw new \Exception('Invalid property data for inflateCart');
		}
		if ($except)
			throw new \BadMethodCallException('Missing property data for inflateCart');
	}

	// Serializable API
	public function serialize()
	{
		return $this->__toString();
	}

	public function unserialize($serialized)
	{
		$this->restoreCart($serialized,FALSE);
	}
	
}
?>
