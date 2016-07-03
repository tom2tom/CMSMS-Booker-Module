<?php
#----------------------------------------------------------------------
# Class: Cart - a shopping-cart class
#----------------------------------------------------------------------
# Derived from code by Riešenia, spol. s r.o. <www.riesenia.com>
# Licensed under the MIT licence
# requires PHP 5.3+
# This class would normally be sub-classed to support interface(s)
# e.g. CartItemInterface or WeightedCartItemInterface
#----------------------------------------------------------------------

namespace Riesenia\Cart;

class Cart
{
	/**
	Cart items

	@var array
	*/
	protected $_items = [];

	/**
	Recognised properties of items

	@var array
	*/
	protected $_propeties = ['price', 'tax', 'weight', 'count'];

	/**
	Context data

	@var mixed
	*/
	protected $_context;

	/**
	If prices are listed as gross

	@var bool
	*/
	protected $_pricesWithTax;

	/**
	Rounding decimals

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

	public function __set()
	{
	}

	public function __get()
	{
	}

	public function __isset()
	{
	}

	public function __unset()
	{
	}

	/**
	Format decimal number

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
	Set sorting by type

	@param array
	@return void
	*/
	public function sortByType($sorting)
	{
		$sorting = array_flip($sorting);

		uasort($this->_items, function (CartItemInterface $a, CartItemInterface $b) use ($sorting) {
			$aSort = isset($sorting[$a->getCartType()]) ? $sorting[$a->getCartType()] : 1000;
			$bSort = isset($sorting[$b->getCartType()]) ? $sorting[$b->getCartType()] : 1000;

			return ($aSort < $bSort) ? -1 : 1;
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
	Get items

	@param NULL|callable filter
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
		if (isset($this->_items[$item->getCartId()])) {
			$quantity += $this->_items[$item->getCartId()]->getCartQuantity();
		}

		$item->setCartQuantity($quantity);
		$item->setCartContext($this->_context);
		$this->_items[$item->getCartId()] = $item;

		$this->_cartModified();
	}

	/**
	Set cart items

	@param array|Traversable items
	@return void
	*/
	public function setItems($items)
	{
		if (!is_array($items) && !$items instanceof \Traversable) {
			throw new \InvalidArgumentException('Only an array or Traversable is allowed for setItems.');
		}

		$this->clear();

		foreach ($items as $item) {
			if (!$item instanceof CartItemInterface) {
				throw new \InvalidArgumentException('All items have to implement CartItemInterface.');
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
	Set item quantity by cart id

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
			return;
		}

		$this->getItem($cartId)->setCartQuantity($quantity);
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

		return $this->countPrice($item->getUnitPrice(), $item->getTaxRate(), $quantity ?: $item->getCartQuantity());
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

		return $this->decimal_format($price * $quantity,$roundingDecimals);
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

	@param mixed filter
	@return array
	*/
	public function getTotals($filter = '~')
	{
		$store = FALSE;

		if (is_string($filter)) {
			$store = $filter;

			if (isset($this->_totals[$store])) {
				return $this->_totals[$store];
			}

			$filter = $this->_getTypeCondition($filter);
		}

		if (!is_callable($filter)) {
			throw new \InvalidArgumentException('Filter for getTotals method has to be callable.');
		}

		$totals = $this->_calculateTotals($filter);

		if ($store) {
			$this->_totals[$store] = $totals;
		}

		return $totals;
	}

	/**
	Get subtotal

	@param string type
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

	@param string type
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

	@param string type
	@return array
	*/
	public function getTaxes($type = '~')
	{
		return $this->getTotals($type)['taxes'];
	}

	/**
	Get tax bases

	@param string type
	@return array
	*/
	public function getTaxBases($type = '~')
	{
		return $this->getTotals($type)['subtotals'];
	}

	/**
	Get tax totals

	@param string type
	@return array
	*/
	public function getTaxTotals($type = '~')
	{
		return $this->getTotals($type)['totals'];
	}

	/**
	Get weight

	@param string type
	@return array
	*/
	public function getWeight($type = '~')
	{
		return $this->getTotals($type)['weight'];
	}

	/**
	Get property value for item|all

	@param string type
	@return mixed
	*/
	public function getProperty($propname, $type = '~', $item = NULL)
	{
		return FALSE;
	}

	/**
	Get boolean indicating whether item|all has named property

	@param string type
	@return boolean
	*/
	public function hasProperty($propname, $type = '~', $item = NULL)
	{
		return FALSE;
	}

	/**
	Set property value for item|all, after adding the property type if necessary

	@param string type
	@return boolean
	*/
	public function setProperty($propname, $type = '~', $value, $item = NULL)
	{
		return FALSE;
	}

	/**
	Remove property value for item|all

	@param string type
	@return boolean
	*/
	public function deleteProperty($propname, $type = '~', $item = NULL)
	{
		return FALSE;
	}

	/**
	Calculate totals

	@param callable filter
	@return array
	*/
	protected function _calculateTotals($filter)
	{
		if (!is_callable($filter)) {
			throw new \InvalidArgumentException('Filter for _calculateTotals method has to be callable.');
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

		if (strpos($type, '~') === 0) {
			$negative = TRUE;
			$type = substr($type, 1);
		} else {
			$negative = FALSE;
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
