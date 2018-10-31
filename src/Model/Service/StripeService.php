<?php
/**
 * Created by PhpStorm.
 * User: mirza
 * Date: 7/17/18
 * Time: 12:56 PM
 */

namespace Model\Service;

use Model\Entity\Order;
use Model\Entity\ResponseBootstrap;
use Model\Mapper\StripeMapper;
use Stripe\Card;
use Stripe\Charge;
use Stripe\Customer;
use Stripe\EphemeralKey;
use Stripe\Source;
use Stripe\Stripe;
use Symfony\Component\Config\Definition\Exception\Exception;

class StripeService
{

    private $stripeMapper;
    private $configuration;
    private $response;
    // stripe customer
    private $customer;
    private $customerId = '';

    public function __construct(StripeMapper $stripeMapper)
    {
        $this->stripeMapper = $stripeMapper;
        $this->configuration = $stripeMapper->getConfiguration();
        $this->response = new ResponseBootstrap();

        // setup stripe
        $this->__setup();
    }


    /**
     * Setup Stripe
     */
    public function __setup()
    {
        Stripe::setApiKey($this->configuration['api_key']);
        Stripe::setMaxNetworkRetries(50);
    }



    public function getAllOrders():ResponseBootstrap {

        // $data = \Stripe\Order::all(array("limit" => 100));

        $response = new ResponseBootstrap();

        // call mapper for data
        $data = $this->stripeMapper->getAllOrdersData();

        $list = array('customer_name', 'customer_street', 'customer_city', 'customer_postal_code',
            'customer_email', 'customer_country', 'currency', 'subtotal', 'shipping', 'tax', 'total', 'order_date', 'shipped',
            'shipping_method', 'paid', 'product_name', 'quantity');

        $fp = fopen('stripe_orders.csv', 'wb');

        fputcsv($fp, $list);

        foreach ($data as $fields) {
            fputcsv($fp, $fields);
        }

        fclose($fp);

        $file = 'stripe_orders.csv';

        if (file_exists($file)) {
            header('Content-Description: File Transfer');
            header('Content-Type: application/octet-stream');
            header('Content-Disposition: attachment; filename="'.basename($file).'"');
            header('Expires: 0');
            header('Cache-Control: must-revalidate');
            header('Pragma: public');
            header('Content-Length: ' . filesize($file));
            readfile($file);
            exit;
        }

        $response->setStatus(200);
        $response->setMessage('Success');

        return $response;
    }


    /**
     * @param $tokenObject
     * @return ResponseBootstrap
     * @throws \Stripe\Error\Api
     */
    public function setOrder($tokenObject, $orderObject):ResponseBootstrap {
        // crete new response object
        $response = new ResponseBootstrap();

        $email = $tokenObject['email'];
        $items = [$orderObject['items']];
        $shipping = $orderObject['shipping'];

        // check if customer with this email already exists
        $userExists = $this->checkIfCustomerIsSet($email);

        // if no create it
        if(!$userExists){
            // create customer
            $customer = \Stripe\Customer::create(array(
                'email' => $email,
                'source' => $tokenObject['id']
            ));

            $this->customerId = $customer['id'];
        }

        // create order
        $order = \Stripe\Order::create([
            'currency' => 'usd',
            'email' => $email,
            'items' => $items,
            'shipping' => $shipping
        ]);

        $order['customer_id'] = $this->customerId;

        // set response
        $response->setStatus(200);
        $response->setMessage('Success');
        $response->setData([
            $order
        ]);

        // return response
        return $response;
    }

//    public function getShippingCost(){
//
//        \EasyPost\EasyPost::setApiKey('EZTK1d6925ae5ee94762aadd838fb900a788QhIWZUwigrsVtnunalyjkw');
//
//        $to_address = \EasyPost\Address::create(
//            array(
//                "name"    => "Dr. Steve Brule",
//                "street1" => "179 N Harbor Dr",
//                "city"    => "Redondo Beach",
//                "state"   => "CA",
//                "zip"     => "90277",
//                "phone"   => "310-808-5243"
//            )
//        );
//        $from_address = \EasyPost\Address::create(
//            array(
//                "company" => "EasyPost",
//                "street1" => "118 2nd Street",
//                "street2" => "4th Floor",
//                "city"    => "San Francisco",
//                "state"   => "CA",
//                "zip"     => "94105",
//                "phone"   => "415-456-7890"
//            )
//        );
//
//        $parcel = \EasyPost\Parcel::create(
//            array(
//                "predefined_package" => "LargeFlatRateBox",
//                "weight" => 76.9
//            )
//        );
//
//        $shipment = \EasyPost\Shipment::create(
//            array(
//                "to_address"   => $to_address,
//                "from_address" => $from_address,
//                "parcel"       => $parcel
//            )
//        );
//
//       // die(print_r($shipment->get_rates()));
//
//       // die($shipment->lowest_rate());
//
////        $shipment->buy($shipment->lowest_rate());
////
////        $shipment->insure(array('amount' => 100));
////
////        echo $shipment->postage_label->label_url;
//
//        return $shipment->lowest_rate();
//    }

    /**
     * @param $emailAddress
     * @return mixed
     * @throws \Stripe\Error\Api
     */
    public function checkIfCustomerIsSet($emailAddress) {
        $response = \Stripe\Customer::all(["limit" => 100, "email" => $emailAddress]);

        $exist = false;

        // check if returned data is empty or not
        if(!empty($response['data'])){
            // if not set  that user exists
            $exist = true;
            $this->customerId = $response['data'][0]['id'];
        }

        // $this->customerId = $response['data'][0]['id'];

        return $exist;
    }


    /**
     * Pay order
     *
     * @param $order
     * @param $token
     * @param $method
     * @return ResponseBootstrap
     */
    public function pay($order, $token, $method, $customer):ResponseBootstrap {
        // crete new response object
        $response = new ResponseBootstrap();

        try {
            // get order
            $order = \Stripe\Order::retrieve($order);

            // get user email
            $email = $token['email'];

            // update shipping method
            $order->selected_shipping_method = $method;
            $order->save();

            // pay order
            $data = $order->pay([
                'email' => $email,
                //'source' => $token['id']
                'customer' => $customer
            ]);

            // die(print_r($data));

            // create entity and set its values
            $entity = new Order();
            $entity->setOrderId($data['id']);
            $entity->setChargeId($data['charge']);
            $entity->setCurrency($data['currency']);
            $entity->setCustomerEmail($data['email']);
            $entity->setName($data['shipping']['name']);

            $entity->setCity($data['shipping']['address']['city']);
            $entity->setPostalCode($data['shipping']['address']['postal_code']);
            $entity->setStreet($data['shipping']['address']['line1']);

            $entity->setCountry($data['shipping']['address']['country']);
            $entity->setAmount($data['amount']);
            $entity->setPaid($data['status']);

            $items = [];
            $counter = 0;
            $itemsData = $data['items'];

            foreach($itemsData as $item){

                if($item['type'] === 'sku'){
                    $items[$counter]['name'] = $item['description'];
                    $items[$counter]['quantity'] = $item['quantity'];
                }else if($item['type'] === 'shipping'){
                    $entity->setShippingAmount($item['amount']);
                    $entity->setShippingMethod($item['description']);
                }else if ($item['type'] === 'tax'){
                    $entity->setTaxAmount($item['amount']);
                }

                $counter++;
            }

            $entity->setOrderItems($items);

            // call mapper and write transaction record into database
            $this->stripeMapper->writeOrderTransaction($entity);

            // set response
            $response->setStatus(200);
            $response->setMessage('Success');
            $response->setData([
                $data
            ]);

            // return response
            return $response;

        }catch(\Stripe\Error\Card $e) {
            // Since it's a decline, \Stripe\Error\Card will be caught
            $body = $e->getJsonBody();
            $err  = $body['error'];

            $response->setStatus(404);
            $response->setMessage($err['message']);
            $response->setData([
                ['message' => $err['message']]
            ]);

        } catch (\Stripe\Error\RateLimit $e) {
            $body = $e->getJsonBody();
            $err  = $body['error'];

            $response->setStatus(404);
            $response->setMessage($err['message']);
            $response->setData([
                ['message' => $err['message']]
            ]);

        } catch (\Stripe\Error\InvalidRequest $e) {
            $body = $e->getJsonBody();
            $err  = $body['error'];
            $response->setStatus(404);
            $response->setMessage($err['message']);
            $response->setData([
                ['message' => $err['message']]
            ]);

        } catch (\Stripe\Error\Authentication $e) {
            // Authentication with Stripe's API failed
            $body = $e->getJsonBody();
            $err  = $body['error'];

            $response->setStatus(404);
            $response->setMessage($err['message']);
            $response->setData([
                ['message' => $err['message']]
            ]);

        } catch (\Stripe\Error\ApiConnection $e) {
            // Network communication with Stripe failed
            $body = $e->getJsonBody();
            $err  = $body['error'];

            $response->setStatus(404);
            $response->setMessage($err['message']);
            $response->setData([
                ['message' => $err['message']]
            ]);

        } catch (\Stripe\Error\Base $e) {
            $body = $e->getJsonBody();
            $err  = $body['error'];

            $response->setStatus(404);
            $response->setMessage($err['message']);
            $response->setData([
                ['message' => $err['message']]
            ]);

        }

        return $response;
    }


    /**
     * Cancel order
     *
     * @param $order
     * @return ResponseBootstrap
     */
    public function cancelOrder($order):ResponseBootstrap {
        // crete new response object
        $response = new ResponseBootstrap();

        $order = \Stripe\Order::retrieve($order);
        $order->status = 'canceled';
        $order->save();

        // set response
        $response->setStatus(200);
        $response->setMessage('Success');
        $response->setData([]);

        // return response
        return $response;
    }





    /**
     * Create Customer
     *
     * @param string $email
     * @return ResponseBootstrap
     */
    public function createCustomer(string $email):ResponseBootstrap
    {
        $this->customer = Customer::create(array(
            "email" => $email
        ));

        $this->response->setStatus(200);
        $this->response->setData($this->customer->jsonSerialize());

        return $this->response;
    }


    /**
     * Get Ephemeral Key
     *
     * @param string $apiVersion
     * @param string $customerId
     * @return ResponseBootstrap
     */
    public function getEphemeral(string $apiVersion, string $customerId):ResponseBootstrap
    {
        $key = EphemeralKey::create(
            array("customer" => $customerId),
            array("stripe_version" => $apiVersion)
        );

        $this->response->setStatus(200);
        $this->response->setData($key->jsonSerialize());

        return $this->response;
    }


    /**
     * Get Customer by Id
     *
     * @param int $id
     */
    public function getCustomer(string $id)
    {
        $customer = Customer::retrieve($id);

        $this->response->setStatus(200);
        $this->response->setData($customer->jsonSerialize());

        return $this->response;
    }


    /**
     * Get All Customers
     *
     * @param int $limit
     * @return array
     */
    public function getAllCustomers(int $limit = 1):ResponseBootstrap
    {
        $customers = Customer::all(array("limit" => $limit));

        $this->response->setStatus(200);
        $this->response->setData($customers->data);

        return $this->response;
    }


    /**
     * Delete Customer
     *
     * @param string $id
     * @return Customer
     */
    public function deleteCustomer(string $id):ResponseBootstrap
    {
        $customer = Customer::retrieve($id);
        $customer = $customer->delete();

        $this->response->setStatus(200);
        $this->response->setData($customer->jsonSerialize());

        return $this->response;
    }


    /**
     * Charge Customer
     *
     * @param int $amount
     * @param string $currency
     * @param stirng $source
     * @param string $email
     * @return Charge
     */
    public function chargeCustomer(string $customerId, int $amount, string $currency, string $source, string $email):ResponseBootstrap
    {
        // attach source
        $customer = Customer::retrieve($customerId);
        $customer->sources->create(array("source" => $source));

        $charge = Charge::create([
            'customer' => $customerId,
            'amount' => $amount,
            'currency' => $currency,
            'source' => $source,
            'receipt_email' => $email,
        ]);

        // deatach source
        $customer->sources->retrieve($source)->detach();

        $this->response->setStatus(200);
        $this->response->setData($charge->jsonSerialize());

        return $this->response;
    }


    /**
     * Create Source
     *
     * @param string $type
     * @param string $currency
     * @param int $amount
     * @param string $email
     * @return ResponseBootstrap
     */
    public function createSource(string $type, string $currency, int $amount, string $email):ResponseBootstrap
    {
        $source = Source::create(array(
            "type" => $type,
            "currency" => $currency,
            "amount" => $amount,
            "owner" => array(
                "email" => $email
            )
        ));

        $this->response->setStatus(200);
        $this->response->setData($source->jsonSerialize());

        return $this->response;
    }


    public function createProduct():ResponseBootstrap
    {
        \Stripe\Product::create(array(
            "name" => 'T-shirt',
            "type" => "good",
            "description" => "Comfortable cotton t-shirt",
            "attributes" => ["size", "gender"]
        ));


        $this->response->setStatus(200);
        $this->response->setData();

        return $this->response;
    }

    public function editProduct():ResponseBootstrap
    {
        $this->response->setStatus(200);
        $this->response->setData();

        return $this->response;
    }


    public function deleteProduct():ResponseBootstrap
    {
        $product = \Stripe\Product::retrieve("prod_DdNL71Ob5zHSir");
        $product->delete();

        $this->response->setStatus(200);
        $this->response->setData();

        return $this->response;
    }


    public function getProduct():ResponseBootstrap
    {
        \Stripe\Product::retrieve("prod_DdNL71Ob5zHSir");

        $this->response->setStatus(200);
        $this->response->setData();

        return $this->response;
    }


    public function getProducts():ResponseBootstrap
    {
        \Stripe\Product::all(array("limit" => 3));

        $this->response->setStatus(200);
        $this->response->setData();

        return $this->response;
    }


}