<?php
#----------------------------------------------------------------------
# Class: Cart - a shopping-cart class
#----------------------------------------------------------------------
# Derived from code by Riešenia, spol. s r.o. <www.riesenia.com>
# Licensed under the MIT licence
# Requires PHP 5.3+
# Manages array of items each of which implements CartItemInterface or an extension of that
#----------------------------------------------------------------------
namespace Booker\Cart;

class Cart
{
	/**
	Cart items

	@var array of objects
	*/
	protected $_items = [];

	/**
	Something usable for in-cart determinations e.g. cart name

	@var mixed
	*/
	protected $_context;

	/**
	Whether prices are listed as gross

	@var bool
	*/
	protected $_pricesWithTax;

	/**
	Rounding decimal-places

	@var int
	*/
	protected $_roundingDecimals;

	/**
	Totals

	@var array
	*/
	protected $_totals = [];

	/**
	Constructor

	@param mixed context data (passed to cart items for custom price logic)
	@param bool TRUE if prices are listed as gross
	@param int number of decimals used for rounding
	*/
	public function __construct($context = NULL, $pricesWithTax = TRUE, $roundingDecimals = 2)
	{
		$this->setContext($context);
		$this->setPricesWithTax($pricesWithTax);
		$this->setRoundingDecimals($roundingDecimals);
	}

	/**
	Format (rounded) decimal number

	@param optional no. of decimal places
	@return float with specified no. of decimal places
	*/
	protected function decimal_format($number, $placesCount = FALSE)
	{
		if ($placesCount === FALSE) {
			$placesCount = $this->_roundingDecimals;
		}
		return (float) number_format((float)$number, $placesCount, '.', '');
	}

	/**
	Set context

	@param mixed context data (passed to cart items for custom price logic)
	@return void
	*/
	public function setContext($context)
	{
		$this->_context = $context;
		$this->_cartModified();
	}

	/**
	Set prices with sales-tax (tax etc)

	@param bool TRUE if prices are listed as gross
	@return void
	*/
	public function setPricesWithTax($pricesWithTax)
	{
		$this->_pricesWithTax = (bool) $pricesWithTax;
		$this->_cartModified();
	}

	/**
	Set rounding decimals

	@param bool TRUE if prices are listed as gross
	@return void
	*/
	public function setRoundingDecimals($roundingDecimals)
	{
		$roundingDecimals = (int) $roundingDecimals;

		if ($roundingDecimals < 0) {
			throw new \RangeException('Invalid value for rounding decimals.');
		}

		$this->_roundingDecimals = $roundingDecimals;
		$this->_cartModified();
	}

	/**
	Sort cart items by prescribed order of item-types

	@param array of types and orders
	@return void
	*/
	public function sortByType($sorting)
	{
		$sorting = array_flip($sorting);

		uasort($this->_items, function (CartItemInterface $a, CartItemInterface $b) use ($sorting) {
			$ta = $a->getCartType();
			$aSort = isset($sorting[$ta]) ? (int)$sorting[$ta] : 1000;
			$tb = $b->getCartType();
			$bSort = isset($sorting[$tb]) ? (int)$sorting[$tb] : 1000;

			return ($aSort - $bSort);
		});
	}

	/**
	Check if cart is empty

	@return bool
	*/
	public function isEmpty()
	{
		return (count($this->_items) == 0);
	}

	/**
	Check if cart is empty by tyoe

	@param string type
	@return bool
	*/
	public function isEmptyByType($type)
	{
		return (count($this->getItemsByType($type)) == 0);
	}

	/**
	Get items count

	@return int
	*/
	public function countItems()
	{
		return count($this->_items);
	}

	/**
	Get items count by type

	@param string type
	@return int
	*/
	public function countItemsByType($type)
	{
		return count($this->getItemsByType($type));
	}

	/**
	Get cart items (as references), optionally filtered

	@param NULL|callable filter, must NOT be end-user definable (injection-risk)
	@return array
	*/
	public function getItems($filter = NULL)
	{
		if ($filter && !is_callable($filter)) {
			throw new \InvalidArgumentException('Filter for getItems method must be callable.');
		}

		return $filter ? array_filter($this->_items, $filter) : $this->_items;
	}

	/**
	Get items by type

	@param string type
	@return array
	*/
	public function getItemsByType($type)
	{
		return $this->getItems($this->_getTypeCondition($type));
	}

	/**
	Check if cart has item with id

	@return bool
	*/
	public function hasItem($cartId)
	{
		return isset($this->_items[$cartId]);
	}

	/**
	Get item by cart id

	@return CartItemInterface
	*/
	public function getItem($cartId)
	{
		if (!$this->hasItem($cartId)) {
			throw new \OutOfBoundsException('Requested cart item does not exist.');
		}

		return $this->_items[$cartId];
	}

	/**
	Add item to cart

	@param CartItemInterface item
	@param int quantity
	@return void
	*/
	public function addItem(CartItemInterface $item, $quantity = 1)
	{
		$id = $item->getCartId();
		if (isset($this->_items[$id])) {
			$quantity += $this->_items[$id]->getCartQuantity();
		}

		$item->setCartQuantity($quantity);
		$item->setCartContext($this->_context);
		$this->_items[$id] = $item;

		$this->_cartModified();
	}

	/**
	Set cart items

	@param array|Traversable items
	@return void
	*/
	public function setItems($items)
	{
		if (!(is_array($items) || $items instanceof \Traversable)) {
			throw new \InvalidArgumentException('Only an array or Traversable is allowed for setItems.');
		}

		$this->clear();

		foreach ($items as $item) {
			if (!$item instanceof CartItemInterface) {
				throw new \InvalidArgumentException('All items must implement CartItemInterface.');
			}

			$this->addItem($item, $item->getCartQuantity());
		}
	}

	/**
	Remove item by cart id

	@param mixed cart id
	@return void
	*/
	public function removeItem($cartId)
	{
		if (!$this->hasItem($cartId)) {
			throw new \OutOfBoundsException('Requested cart item does not exist.');
		}

		unset($this->_items[$cartId]);
		$this->_cartModified();
	}

	/**
	Set item quantity by cart id, <= 0 removes item

	@param mixed cart id
	@param int quantity
	@return void
	*/
	public function setItemQuantity($cartId, $quantity)
	{
		if (!$this->hasItem($cartId)) {
			throw new \OutOfBoundsException('Requested cart item does not exist.');
		}

		$quantity = (int) $quantity;

		if ($quantity <= 0) {
			$this->removeItem($cartId);
		} else {
			$this->getItem($cartId)->setCartQuantity($quantity);
		}
		$this->_cartModified();
	}

	/**
	Get item price (with or without tax based on _pricesWithTax property)

	@param CartItemInterface item
	@param int quantity (NULL to use item quantity)
	@return formatted decimal
	*/
	public function getItemPrice(CartItemInterface $item, $quantity = NULL)
	{
		$item->setCartContext($this->_context);

		return $this->countPrice($item->getUnitPrice(), $item->getTaxRate(), $quantity ? : $item->getCartQuantity());
	}

	/**
	Count price

	@param float unit price (without tax)
	@param float tax rate
	@param int quantity
	@param bool count price with tax (NULL to use cart default)
	@param int rounding decimals (NULL to use cart default)
	@return formatted decimal
	*/
	public function countPrice($unitPrice, $taxRate, $quantity = 1, $pricesWithTax = NULL, $roundingDecimals = NULL)
	{
		if ($pricesWithTax === NULL) {
			$pricesWithTax = $this->_pricesWithTax;
		}

		if ($roundingDecimals === NULL) {
			$roundingDecimals = $this->_roundingDecimals;
		}

		$price = $this->decimal_format($unitPrice,4);

		if ($pricesWithTax) {
			$price *= (1 + $this->decimal_format($taxRate / 100,3));
		}

		return $this->decimal_format($price * $quantity, $roundingDecimals);
	}

	/**
	Clear cart contents

	@return void
	*/
	public function clear()
	{
		$this->_items = [];
		$this->_cartModified();
	}

	/**
	Get totals using filter
	If filter is string, uses _getTypeCondition to build filter function.

	@param callable|string, if string  then maybe ','-separated, leading ~ to negate
	@return array
	*/
	public function getTotals($filter = '~')
	{
		$store = FALSE;

		if (is_string($filter)) {

			if (isset($this->_totals[$filter])) {
				return $this->_totals[$filter];
			}

			$store = $filter;
			$filter = $this->_getTypeCondition($filter);
		}

		if (!is_callable($filter)) {
			throw new \InvalidArgumentException('Filter for getTotals method must be callable.');
		}

		$totals = $this->_calculateTotals($filter);

		if ($store) {
			$this->_totals[$store] = $totals;
		}

		return $totals;
	}

	/**
	Get subtotal

	@param string type, maybe ','-separated, leading ~ to negate
	@return formatted decimal
	*/
	public function getSubtotal($type = '~')
	{
		$subtotal = 0.0;

		foreach ($this->getTotals($type)['subtotals'] as $amount) {
			$subtotal += $amount;
		}

		return $this->decimal_format($subtotal);
	}

	/**
	Get total

	@param string type, maybe ','-separated, leading ~ to negate
	@return formatted decimal
	*/
	public function getTotal($type = '~')
	{
		$total = 0.0;

		foreach ($this->getTotals($type)['totals'] as $amount) {
			$total += $amount;
		}

		return $this->decimal_format($total);
	}

	/**
	Get taxes

	@param string type, maybe ','-separated, leading ~ to negate
	@return array
	*/
	public function getTaxes($type = '~')
	{
		return $this->getTotals($type)['taxes'];
	}

	/**
	Get tax bases

	@param string type, maybe ','-separated, leading ~ to negate
	@return array
	*/
	public function getTaxBases($type = '~')
	{
		return $this->getTotals($type)['subtotals'];
	}

	/**
	Get tax totals

	@param string type, maybe ','-separated, leading ~ to negate
	@return array
	*/
	public function getTaxTotals($type = '~')
	{
		return $this->getTotals($type)['totals'];
	}

	/**
	Get weight

	@param string type, maybe ','-separated, leading ~ to negate
	@return array
	*/
	public function getWeight($type = '~')
	{
		return $this->getTotals($type)['weight'];
	}

	/**
	Calculate totals

	@param callable filter
	@return array
	*/
	protected function _calculateTotals($filter)
	{
		if (!is_callable($filter)) {
			throw new \InvalidArgumentException('Filter for _calculateTotals method must be callable.');
		}

		$taxTotals = [];
		$weight = 0.0;

		foreach ($this->getItems($filter) as $item) {
			$price = $this->getItemPrice($item);

			$taxRate = $item->getTaxRate();
			if (!isset($taxTotals[$taxRate])) {
				$taxTotals[$taxRate] = 0.0;
			}

			$taxTotals[$taxRate] += $price;

			// weight
			if ($item instanceof WeightedCartItemInterface) {
				$itemWeight = (float)$item->getWeight() * (int)$item->getCartQuantity();
				$weight += $this->decimal_format($itemWeight,1);
			}
		}

		$totals = ['subtotals' => [], 'taxes' => [], 'totals' => [], 'weight' => $weight];

		foreach ($taxTotals as $taxRate => $amount) {
			if ($this->_pricesWithTax) {
				$totals['totals'][$taxRate] = $amount;
				$raw = $amount * (1 - 1 / (1 + (float) $taxRate / 100));
				$totals['taxes'][$taxRate] = $this->decimal_format($raw);
				$totals['subtotals'][$taxRate] = $amount - $totals['taxes'][$taxRate];
			} else {
				$totals['subtotals'][$taxRate] = $amount;
				$totals['taxes'][$taxRate] = $this->decimal_format($amount * ((float) $taxRate / 100));
				$totals['totals'][$taxRate] = $amount + $totals['taxes'][$taxRate];
			}
		}
		return $totals;
	}

	/**
	Build condition for item type

	@param string item type
	@return Closure
	*/
	protected function _getTypeCondition($type)
	{
		$negative = ($type[0] == '~');
		if ($negative) {
			$type = substr($type, 1);
		}

		$type = explode(',', $type);

		return function (CartItemInterface $item) use ($type, $negative) {
			return $negative ? !in_array($item->getCartType(), $type) : in_array($item->getCartType(), $type);
		};
	}

	/**
	Clear cached totals

	@return void
	*/
	protected function _cartModified()
	{
		$this->_totals = NULL;
	}
}
?>
