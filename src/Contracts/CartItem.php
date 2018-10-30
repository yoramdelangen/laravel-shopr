<?php

namespace Happypixels\Shopr\Contracts;

interface CartItem
{
    public function setQuantity($quantity = 1) : CartItem;

    public function setOptions($options = []) : CartItem;

    public function setSubItems($subItems = []) : CartItem;

    public function setFixedPrice($price = null) : CartItem;
}
