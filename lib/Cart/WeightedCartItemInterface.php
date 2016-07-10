<?php
/**
Iterface for cart-items whose weight is relevant to the commerce

@author Tomas Saghy <segy@riesenia.com>
*/
namespace Booker\Cart;

interface WeightedCartItemInterface extends CartItemInterface
{
	/**
	Get unit weight

	@return float
	*/
	public function getWeight();
}
?>
