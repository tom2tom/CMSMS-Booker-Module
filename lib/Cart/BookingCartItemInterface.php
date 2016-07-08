<?php
/**
Iterface for items in bookings cart

@author Tom Phane <tpgww@onepost.net>
*/
namespace Booker\Cart;

interface BookingCartItemInterface extends CartItemInterface
{
	public function getCartContext();

	public function setCartName($name);

	public function setCartType($type);

	public function setUnitPrice($price);

	public function setTaxRate($taxrate);
}
?>
