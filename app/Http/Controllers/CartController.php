<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Product;

class CartController extends Controller
{
    public function __construct()
    {
        if (!session()->has('cart')) {
            session(['cart' => []]);
        }
    }

    // Show cart
    public function show()
    {
        $cart = session('cart');
        $total = $this->total();
        return view('store.cart', compact('cart', 'total'));
    }

    // Add item
    public function add(Product $product)
    {
        $cart = session('cart');
        $product->quantity = 1;
        $cart[$product->slug] = $product;
        session(['cart' => $cart]);

        return redirect()->route('cart-show');
    }

    // Delete item
    public function delete(Product $product)
    {
        $cart = session('cart');
        unset($cart[$product->slug]);
        session(['cart' => $cart]);

        return redirect()->route('cart-show');
    }

    // Update item
    public function update(Product $product, $quantity)
    {
        $cart = session('cart');
        $cart[$product->slug]->quantity = $quantity;
        session(['cart' => $cart]);

        return redirect()->route('cart-show');
    }

    // Trash cart
    public function trash()
    {
        session()->forget('cart');

        return redirect()->route('cart-show');
    }

    // Total
    private function total()
    {
        $cart = session('cart');
        $total = 0;

        foreach ($cart as $item) {
            $total += $item->price * $item->quantity;
        }

        return $total;
    }

    // Detalle del pedido
    public function orderDetail()
    {
        if (count(session('cart')) <= 0) {
            return redirect()->route('home');
        }

        $cart = session('cart');
        $total = $this->total();

        return view('store.order-detail', compact('cart', 'total'));
    }
}
