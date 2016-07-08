<?php
/**
 Iterface for items in cart

 @author Tomas Saghy <segy@riesenia.com>
*/
namespace Booker\Cart;

interface CartItemInterface
{
	/**
	Get this item's unique identifier

	@return mixed
	*/
	public function getCartId();

	/**
	Get this item's type for discrimination within the cart

	@return string
	*/
	public function getCartType();

	/**
	Get this item's name

	@return string
	*/
	public function getCartName();

	/**
	Set cart-context for this item

	@param mixed
	@return void
	*/
	public function setCartContext($context);

	/**
	Log the no. of this item in the cart

	@param int
	@return void
	*/
	public function setCartQuantity($quantity);

	/**
	Log the no. of this item in the cart

	@return int
	*/
	public function getCartQuantity();

	/**
	Get this item's unit-price (perhaps based on quantity and context)

	@return float
	*/
	public function getUnitPrice();

	/**
	Get this item's tax rate (percentage)

	@return float
	*/
	public function getTaxRate();
}
?>
