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
	//a deleted item's status is one of the following - STATUSMAX i.e. < 0
	const NORMAL = 0;
	const PAYABLE = 1;
	const CREDITED = 2;
	const PAID = 3;
	const CREDITEXPIRED = 4;
	const CREDITUSED = 5;
	const DEFERRED = 6;
	const FAILED = 9;
	const REQUESTED = 10; // add 0..9 to this when relevant
	const RECORDED = 20; //ditto
	const STATUSMAX = 30; //for [un]delete adjustments

	public $id = ''; //unique identifier
	public $name; // unused
	public $type; // resource item_id (int)
	public $price;
	public $taxrate;
	public $data; // parameters-container, arbitrary properties
	//internal use only
	public $context = NULL;
	public $quantity = 0;

	public function __construct($name = '', $type = 0, $price = 0.0, $taxrate = 0.0)
	{
		$this->name = $name;
		$this->type = $type;
		$this->price = $price;
		$this->taxrate = $taxrate;
		$this->data = new \stdClass();
		$this->data->status = self::NORMAL;
		$this->data->start = 0; //booking start, UTC timestamp
		$this->data->slen = 0; //booking length, seconds
	}

	/**
	Get this item's unique (32-byte) identifier

	@return mixed
	*/
	public function getCartId()
	{
		if (!$this->id)
			$this->id = hash('md4',uniqid($this->type)); //fastest conversion
		return $this->id;
	}

	public function setCartType($type)
	{
		$this->type = $type;
	}

	/**
	Get this item's type, for discrimination within the cart

	@return string
	*/
	public function getCartType()
	{
		return $this->type;
	}

	public function setCartName($name)
	{
		$this->name = $name;
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

	/**
	Log the status of this item in the cart

	@param mixed int|'deleted'|'undeleted'
	@return void
	*/
	public function setStatus($status)
	{
		if (is_numeric($status)) {
			$this->data->status = (int)($status);
		} elseif ($status == 'deleted' && $this->data->status >= 0) {
			$this->data->status -= self::STATUSMAX; //now < 0
		} elseif ($status == 'undeleted' && $this->data->status < 0) {
			$this->data->status += self::STATUSMAX; //now >= 0
		}
	}

	/**
	Get this item's status enuerator

	@return int
	*/
	public function getStatus()
	{
		return (int)$this->data->status;
	}
	/**
	Set this item's data-bundle

	@param stdClass object
	@return void
	*/
	public function setPackage(\stdClass $object)
	{
		$this->data = $object;
	}

	/**
	Get this item's data-bundle

	@return object
	*/
	public function getPackage()
	{
		return $this->data;
	}
}
