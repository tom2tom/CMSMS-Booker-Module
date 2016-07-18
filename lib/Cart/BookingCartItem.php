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
	//a deleted item's status is one of the following - 30 i.e. < 0
	const NORMAL = 0;
	const PAYABLE = 1;
	const CREDITED = 2;
	const PAID = 3;
	const REQUESTED = 10; //TODO add 1|2|3 when relevant
	const RECORDED = 20; //ditto

	public $name; // unused
	public $type; // resource item_id (int)
	public $price;
	public $taxrate;
	public $status = 0; //self::NORMAL
	public $start; //booking start, UTC timestamp
	public $slen; //booking length, seconds
	//internal use only
	public $context = NULL;
	public $quantity = 0;

	public function __construct($name = '', $type = 0,	$price = 0.0, $taxrate = 0.0)
	{
		$this->name = $name;
		$this->type = $type;
		$this->price = $price;
		$this->taxrate = $taxrate;
	}

	/**
	Get this item's unique (32-byte) identifier

	@return mixed
	*/
	public function getCartId()
	{
		return hash('md4',uniqid($this->type)); //fastest conversion
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

	/**
	Log the status of this item in the cart

	@param int
	@return void
	*/
	public function setStatus($status)
	{
		$this->status = (int)($status);
	}

	/**
	Get this item's status enuerator

	@return int
	*/
	public function getStatus()
	{
		return (int)$this->status;
	}
	/**
	Log the start, length of this item in the cart

	@param int timestamp
	@param int seconds
	@return void
	*/
	public function setStamps($start, $slen)
	{
		$this->start = (int)($start);
		$this->slen = (int)($slen);
	}

	/**
	Get this item's start-stamp and length values

	@return array[int start-stamp,int seconds-length]
	*/
	public function getStamps()
	{
		return array($this->start,$this->slen);
	}
}
