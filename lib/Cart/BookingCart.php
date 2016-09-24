<?php
#----------------------------------------------------------------------
# Module: Booker - a resource booking module
# Library file: BookingCart
#----------------------------------------------------------------------
# See file Booker.module.php for full details of copyright, licence, etc.
#----------------------------------------------------------------------
namespace Booker\Cart;

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
	protected $properties = array('price'=>TRUE, 'tax'=>TRUE, 'status'=>FALSE, 'weight'=>TRUE, 'count'=>FALSE);

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

	public function __toString()
	{
		return json_encode(get_object_vars($this));
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


	public function getRoundingDecimals()
	{
		return $this->roundingDecimals;
	}

	/**
	Get status

	@param optional filter callable|string, if string then per getTypeCondition(),
		default match everything in cart
	@return array with members: key is a detected status-value,
		value is an array of cart-id's for items with that status
	*/
	public function getStatus($filter='~')
	{
		if (is_string($filter)) {
			$filter = $this->getTypeCondition($filter);
		}
		if (!is_callable($filter)) {
			throw new \InvalidArgumentException('Filter for getStatus method must be callable.');
		}
		$totals = array();
		foreach ($this->getItems($filter) as $item) {
			$status = $item->getStatus();
			if (isset($totals[$status])) {
				$totals[$status][] = $item->getCartId();
			} else {
				$totals[$status] = array($item->getCartId());
			}
		}
		return $totals;
	}

	/**
	Check if cart is empty or has only items marked as deleted

	@return bool
	*/
	public function seemsEmpty()
	{
		if ($this->items) {
			return !$this->getItems(function ($item) {
				return $item->getStatus() >= 0; //not flagged as deleted
			}); //TODO CHECK need to filter 'my' items if 'unique' cache can ever be shared ?
		}
		return TRUE;
	}

	/**
	Incrementally populate all cart & item properties from supplied json (e.g. from cache)

	@param string json
	@param optional bool except whether to throw exception upon failure, default FALSE
	@return void
	*/
	public function restoreCart($json,$except=FALSE)
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
									case 'id': $item->id = $itmval; break;
									case 'name': $item->setCartName($itmval); break;
									case 'type': $item->setCartType($itmval); break;
									case 'price':  $item->setUnitPrice($itmval); break;
									case 'taxrate': $item->setTaxRate($itmval); break;
									case 'status' : $item->setStatus($itmval); break;
									case 'data': $item->setPackage($itmval); break;
									case 'context': $item->setCartContext($itmval); break;
									case 'quantity': $item->setCartQuantity($itmval); break;
									default: throw new \Exception('restoreCart: invalid property data for item');
								}
							}
							$itmid = $item->getCartId();
							$this->items[$itmid] = $item;
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
