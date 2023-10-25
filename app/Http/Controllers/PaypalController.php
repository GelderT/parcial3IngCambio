<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use PayPal\Rest\ApiContext;
use PayPal\Auth\OAuthTokenCredential;
use PayPal\Api\Amount;
use PayPal\Api\Details;
use PayPal\Api\Item;
use PayPal\Api\ItemList;
use PayPal\Api\Payer;
use PayPal\Api\Payment;
use PayPal\Api\RedirectUrls;
use PayPal\Api\ExecutePayment;
use PayPal\Api\PaymentExecution;
use PayPal\Api\Transaction;
use PayPal\Api\WebProfile;

use App\Order;
use App\OrderItem;

class PaypalController extends Controller
{
    private $_api_context;

    public function __construct()
    {
        // Configurar el contexto de la API de PayPal
        $paypal_conf = config('paypal');
        $this->_api_context = new ApiContext(new OAuthTokenCredential($paypal_conf['client_id'], $paypal_conf['secret']));
        $this->_api_context->setConfig($paypal_conf['settings']);
    }

    public function postPayment(Request $request)
    {
        $payer = new Payer();
        $payer->setPaymentMethod('paypal');
        
        $items = [];
        $subtotal = 0;
        $cart = $request->session()->get('cart');
        $currency = 'EUR';

        foreach ($cart as $producto) {
            $item = new Item();
            $item->setName($producto->name)
                ->setCurrency($currency)
                ->setDescription($producto->extract)
                ->setQuantity($producto->quantity)
                ->setPrice($producto->price);

            $items[] = $item;
            $subtotal += $producto->quantity * $producto->price;
        }

        $item_list = new ItemList();
        $item_list->setItems($items);

        $details = new Details();
        $details->setSubtotal($subtotal)->setShipping(100);

        $total = $subtotal + 100;

        $amount = new Amount();
        $amount->setCurrency($currency)->setTotal($total)->setDetails($details);

        $transaction = new Transaction();
        $transaction->setAmount($amount)->setItemList($item_list)->setDescription('Pedido de prueba en mi Laravel App Store');

        $redirect_urls = new RedirectUrls();
        $redirect_urls->setReturnUrl(route('payment.status'))->setCancelUrl(route('payment.status'));

        $payment = new Payment();
        $payment->setIntent('Sale')->setPayer($payer)->setRedirectUrls($redirect_urls)->setTransactions([$transaction]);

        try {
            $payment->create($this->_api_context);
        } catch (\PayPal\Exception\PayPalConnectionException $ex) {
            if (config('app.debug')) {
                echo "Exception: " . $ex->getMessage() . PHP_EOL;
                $err_data = json_decode($ex->getData(), true);
                exit;
            } else {
                die('Ups! Algo salió mal');
            }
        }

        foreach ($payment->getLinks() as $link) {
            if ($link->getRel() == 'approval_url') {
                $redirect_url = $link->getHref();
                break;
            }
        }

        // Agregar el ID de pago a la sesión
        $request->session()->put('paypal_payment_id', $payment->getId());

        if (isset($redirect_url)) {
            // Redireccionar al sitio de PayPal
            return redirect()->away($redirect_url);
        }

        return redirect()->route('cart-show')->with('error', 'Ups! Error desconocido.');
    }

    public function getPaymentStatus(Request $request)
    {
        // Obtener el ID de pago antes de borrarlo de la sesión
        $payment_id = $request->session()->get('paypal_payment_id');

        // Borrar el ID de pago de la sesión
        $request->session()->forget('paypal_payment_id');

        $payerId = $request->input('PayerID');
        $token = $request->input('token');

        if (empty($payerId) || empty($token)) {
            return redirect()->route('home')->with('message', 'Hubo un problema al intentar pagar con PayPal');
        }

        $payment = Payment::get($payment_id, $this->_api_context);

        $execution = new PaymentExecution();
        $execution->setPayerId($payerId);

        $result = $payment->execute($execution, $this->_api_context);

        if ($result->getState() == 'approved') {
            $this->saveOrder($request->session()->get('cart'));
            $request->session()->forget('cart');

            return redirect()->route('home')->with('message', 'Compra realizada de forma correcta');
        }

        return redirect()->route('home')->with('message', 'La compra fue cancelada');
    }

    private function saveOrder($cart)
    {
        $subtotal = 0;

        foreach ($cart as $item) {
            $subtotal += $item->price * $item->quantity;
        }

        $order = Order::create([
            'subtotal' => $subtotal,
            'shipping' => 100,
            'user_id' => auth()->user()->id,
        ]);

        foreach ($cart as $item) {
            $this->saveOrderItem($item, $order->id);
        }
    }

    private function saveOrderItem($item, $order_id)
    {
        OrderItem::create([
            'quantity' => $item->quantity,
            'price' => $item->price,
            'product_id' => $item->id,
            'order_id' => $order_id,
        ]);
    }
}
