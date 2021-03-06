<?php

namespace Happypixels\Shopr\Repositories;

use Happypixels\Shopr\CartItem;
use Illuminate\Support\Collection;
use Happypixels\Shopr\Cart\BaseCart;
use Happypixels\Shopr\Contracts\Cart;
use Illuminate\Support\Facades\Event;
use Happypixels\Shopr\Helpers\SessionHelper;

class SessionCartRepository extends BaseCart
{
    private $cartKey = 'shopr.cart';
    private $session;

    public function __construct(SessionHelper $session)
    {
        $this->session = $session;
    }

    public function getAllItems() : Collection
    {
        return $this->session->get($this->cartKey) ?: collect();
    }

    public function addItem($shoppableType, $shoppableId, $quantity = 1, $options = [], $subItems = [], $price = null) : CartItem
    {
        $quantity = (is_numeric($quantity) && $quantity > 0) ? $quantity : 1;

        $items = $this->getAllItems();
        $item = new CartItem($shoppableType, $shoppableId, $quantity, $options, $subItems, $price);

        // Find already added items that are identical to current selection.
        $identicals = $items->filter(function ($row) use ($item) {
            return
                $row->shoppableType === $item->shoppableType &&
                $row->shoppableId === $item->shoppableId &&
                serialize($row->options) === serialize($item->options) &&
                serialize($row->subItems) === serialize($item->subItems);
        });

        // If an identical item already exists in the cart, add to it's quantity.
        // Otherwise, push it.
        if ($identicals->count() > 0) {
            $items->where('id', $identicals->first()->id)->first()->quantity += $quantity;
            $item->quantity = $items->where('id', $identicals->first()->id)->first()->quantity;

            $event = 'updated';
        } else {
            $items->push($item);

            $event = 'added';
        }

        $this->session->put($this->cartKey, $items);

        Event::fire('shopr.cart.items.'.$event, $item);

        return $item;
    }

    public function updateItem($id, $data)
    {
        $items = $this->getAllItems();
        $item = null;

        foreach ($items as $index => $item) {
            if ($item->id !== $id || empty($data['quantity'])) {
                continue;
            }

            $items[$index]->quantity = intval($data['quantity']);

            if (! empty($items[$index]->subItems)) {
                foreach ($items[$index]->subItems as $i => $subItem) {
                    $items[$index]->subItems[$i]->quantity = intval($data['quantity']);
                    $items[$index]->subItems[$i]->total = $items[$index]->subItems[$i]->total();
                }
            }

            $items[$index]->total = $items[$index]->total();
            $item = $items[$index];
        }

        $this->session->put($this->cartKey, $items);

        Event::fire('shopr.cart.items.updated', $item);

        return $item;
    }

    public function removeItem($id)
    {
        $items = $this->getAllItems();
        $removedItem = null;

        foreach ($items as $index => $item) {
            if ($item->id === $id) {
                $removedItem = $items[$index];

                unset($items[$index]);
            }
        }

        $this->session->put($this->cartKey, $items);

        if ($removedItem) {
            Event::fire('shopr.cart.items.deleted', $removedItem);
        }

        return $removedItem;
    }

    public function clear()
    {
        $this->session->put($this->cartKey, collect([]));

        Event::fire('shopr.cart.cleared');
    }
}
