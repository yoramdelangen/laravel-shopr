<?php

namespace Happypixels\Shopr\Cart;

use Happypixels\Shopr\Contracts\CartItem;
use Happypixels\Shopr\Money\Formatter;
use Happypixels\Shopr\Contracts\Shoppable;

class SessionCartItem implements CartItem
{
    public $id;

    public $quantity;

    public $shoppableType;

    public $shoppableId;

    public $shoppable;

    public $options;

    public $subItems;

    public $total;

    public $price;

    public function __construct(Shoppable $shoppable)
    {
        $this->id            = uniqid(time());
        $this->shoppableType = get_class($shoppable);
        $this->shoppableId   = $shoppable->id;
        $this->shoppable     = $shoppable;
    }

    public function setQuantity($quantity = 1) : CartItem
    {
        $this->quantity = (is_numeric($quantity) && $quantity > 0) ? $quantity : 1;

        if (!empty($this->subItems)) {
            foreach ($this->subItems as $subItem) {
                $subItem->setQuantity($this->quantity);
            }
        }

        $this->setTotal();

        return $this;
    }

    public function setOptions($options = []) : CartItem
    {
        $this->options = $options;

        return $this;
    }

    public function setSubItems($subItems = []) : CartItem
    {
        if (empty($subItems)) {
            return $this;
        }

        $this->subItems = collect();

        foreach ($subItems as $item) {
            $shoppable = (new $item['shoppable_type'])->findOrFail($item['shoppable_id']);
            $options   = (!empty($item['options'])) ? $item['options'] : [];
            $price     = (!empty($item['price'])) ? $item['price'] : null;

            $this->subItems->push(
                (new SessionCartItem($shoppable))
                    ->setQuantity($this->quantity)
                    ->setOptions($options)
                    ->setFixedPrice($price)
            );
        }

        $this->setTotal();

        return $this;
    }

    public function setFixedPrice($price = null) : CartItem
    {
        $this->price           = ($price) ?? $this->shoppable->getPrice();
        $this->price_formatted = (new Formatter)->format($this->price);

        $this->setTotal();

        return $this;
    }

    private function setTotal()
    {
        $this->total = $this->quantity * $this->price;

        // Parents' total amount include their children's totals.
        if ($this->subItems && $this->subItems->count() > 0) {
            foreach ($this->subItems as $subItem) {
                $this->total += $subItem->total;
            }
        }
    }
}
