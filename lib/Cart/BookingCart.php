<?php
/*
This file is part of CMS Made Simple module: Booker.
Copyright(C) 2014-2016 Tom Phane <tpgww@onepost.net>
Refer to licence and other details at the top of file Booker.module.php
More info at http://dev.cmsmadesimple.org/projects/booker

Class: BookingCart
*/

namespace Booker\Cart;

class BookingCart extends Cart implements CartItemInterface
{
	/**
	Get cart identifier

	@return mixed
	*/
	public function getCartId()
	{
	}

	/**
	Get type of the cart

	@return string
	*/
	public function getCartType()
	{
	}

	/**
	Get name of the cart

	@return string
	*/
	public function getCartName()
	{
	}

	/**
	Set cart context

	@param mixed
	@return void
	*/
	public function setCartContext($context)
	{
	}

	/**
	Set cart quantity

	@param int
	@return void
	*/
	public function setCartQuantity($quantity)
	{
	}

	/**
	Get cart quantity

	@return int
	*/
	public function getCartQuantity()
	{
	}

	/**
	Get unit price based on quantity and context

	@return float
	*/
	public function getUnitPrice()
	{
	}

	/**
	Get tax rate percentage

	@return float
	*/
	public function getTaxRate()
	{
	}
}
?>
