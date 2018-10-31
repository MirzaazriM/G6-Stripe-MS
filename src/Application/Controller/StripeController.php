<?php
/**
 * Created by PhpStorm.
 * User: mirza
 * Date: 7/17/18
 * Time: 12:55 PM
 */

namespace Application\Controller;


use Model\Entity\ResponseBootstrap;
use Model\Service\StripeService;
use Symfony\Component\HttpFoundation\Request;

class StripeController
{

    private $stripeService;
    private $response;


    public function __construct(StripeService $stripeService)
    {
        $this->stripeService = $stripeService;
        $this->response = new ResponseBootstrap();
    }


    /**
     * Create order
     *
     * @param Request $request
     * @return ResponseBootstrap
     * @throws \Stripe\Error\Api
     */
    public function postOrder(Request $request){
        // crete new response object
        $response = new ResponseBootstrap();

        // get data
        $data = json_decode($request->getContent(), true);

        // get token data
        $tokenData = $data['token'];
        $orderData = $data['order'];

        // check if token data is present
        if(isset($tokenData) && isset($orderData)){
            // call service to make the charge
            return $this->stripeService->setOrder($tokenData, $orderData);
        }else {
            $response->setStatus(404);
            $response->setMessage('Bad request');
        }

        // return response
        return $response;
    }


    /**
     * Get all orders
     */
    public function getOrders(Request $request){
        return $this->stripeService->getAllOrders();
    }


    /**
     * Pay order
     *
     * @param Request $request
     * @return ResponseBootstrap
     */
    public function postPay(Request $request):ResponseBootstrap {
        // crete new response object
        $response = new ResponseBootstrap();

        // get data
        $data = json_decode($request->getContent(), true);

        // get token data
        $token = $data['token'];
        $order = $data['order'];
        $shippingMethod = $data['shipping_method'];
        $customer = $data['customer_id'];

        // check if token data is present
        if(isset($order) && isset($token) && isset($shippingMethod) && isset($customer)){
            // call service to make the charge
            return $this->stripeService->pay($order, $token, $shippingMethod, $customer);
        }else {
            $response->setStatus(404);
            $response->setMessage('Bad request');
        }

        // return response
        return $response;

    }


    /**
     * Decline order
     *
     * @param Request $request
     * @return ResponseBootstrap
     */
    public function postDecline(Request $request):ResponseBootstrap {
        // crete new response object
        $response = new ResponseBootstrap();

        // get data
        $data = json_decode($request->getContent(), true);

        // get data
        $order = $data['order'];

        // check if token data is present
        if(isset($order)){
            // call service to make the charge
            return $this->stripeService->cancelOrder($order);
        }else {
            $response->setStatus(404);
            $response->setMessage('Bad request');
        }

        // return response
        return $response;

    }



    /**
     * Create Customer
     *
     * @param Request $request
     * @return ResponseBootstrap
     */
    public function postCustomer(Request $request):ResponseBootstrap
    {
        $data = json_decode($request->getContent(), true);
        // parameters
        $email = $data['email'];

        if(!empty($email)){
            return $this->stripeService->createCustomer($email);
        }else{
            $this->response->setStatus(404);
            $this->response->setMessage('Api Version Missing');
        }

        return $this->response;
    }


    /**
     * Get Ephemeral Key
     *
     * @param Request $request
     * @return ResponseBootstrap
     */
    public function postEphemeral(Request $request):ResponseBootstrap
    {
        $data = json_decode($request->getContent(), true);
        // parameters
        $apiVersion = $data['api_version'];
        $customerId = $data['customer_id'];

        if(!empty($apiVersion) && !empty($apiVersion)){
            return $this->stripeService->getEphemeral($apiVersion, $customerId);
        }else{
            $this->response->setStatus(404);
            $this->response->setMessage('Api Version Missing');
        }

        return $this->response;
    }


    /**
     * Get Customer by Id
     *
     * @param Request $request
     * @return ResponseBootstrap
     */
    public function getCustomer(Request $request):ResponseBootstrap
    {
        $id = $request->get('id');

        if(!empty($id)){
            return $this->stripeService->getCustomer($id);
        }else{
            $this->response->setStatus(404);
            $this->response->setMessage('Bad request');
        }

        return $this->response;
    }


    /**
     * Get Customers
     *
     * @param Request $request
     * @return ResponseBootstrap
     */
    public function getCustomers(Request $request):ResponseBootstrap
    {
        $limit = $request->get('limit');

        if(!empty($limit)){
            return $this->stripeService->getAllCustomers($limit);
        }else{
            $this->response->setStatus(404);
            $this->response->setMessage('Bad request');
        }

        return $this->response;
    }


    /**
     * Delete Customer
     *
     * @param Request $request
     * @return ResponseBootstrap
     */
    public function deleteCustomer(Request $request):ResponseBootstrap
    {
        $id = $request->get('id');

        if(!empty($id)){
            return $this->stripeService->deleteCustomer($id);
        }else{
            $this->response->setStatus(404);
            $this->response->setMessage('Bad request');
        }

        return $this->response;
    }


    /**
     * Charege a Customer
     *
     * @param Request $request
     * @return ResponseBootstrap
     */
    public function postCharge(Request $request):ResponseBootstrap
    {
        $data = json_decode($request->getContent(), true);
        // parameters
        $customerId = $data['customer_id'];
        $amount = $data['amount'];
        $currency = $data['currency'];
        $source = $data['source'];
        $email = $data['email'];

        if(!empty($customerId) && !empty($amount) && !empty($currency) && !empty($source) && !empty($email)){
            return $this->stripeService->chargeCustomer($customerId, $amount, $currency, $source, $email);
        }else{
            $this->response->setStatus(404);
            $this->response->setMessage('Bad request');
        }

        return $this->response;
    }


    /**
     * Create a Source
     *
     * @param Request $request
     * @return ResponseBootstrap
     */
    public function postSource(Request $request):ResponseBootstrap
    {
        $data = json_decode($request->getContent(), true);
        // parameters
        $type = $data['type'];
        $currency = $data['currency'];
        $amount = $data['amount'];
        $email = $data['email'];

        if(!empty($type) && !empty($currency) && !empty($amount) && !empty($email)) {
            return $this->stripeService->createSource($type, $currency, $amount, $email);
        }else{
            $this->response->setStatus(404);
            $this->response->setMessage('Bad request');
        }

        return $this->response;
    }


    /**
     * Create Product
     *
     * @param Request $request
     * @return ResponseBootstrap
     */
    public function postProduct(Request $request):ResponseBootstrap
    {
        return $this->response;
    }


    /**
     * Edit Product
     *
     * @param Request $request
     * @return ResponseBootstrap
     */
    public function putProduct(Request $request):ResponseBootstrap
    {
        return $this->response;
    }


    /**
     * Delete Product
     *
     * @param Request $request
     * @return ResponseBootstrap
     */
    public function deleteProduct(Request $request):ResponseBootstrap
    {
        return $this->response;
    }


    /**
     * Get Products
     *
     * @param Request $request
     * @return ResponseBootstrap
     */
    public function getProducts(Request $request):ResponseBootstrap
    {
        return $this->response;
    }


    /**
     * Get Product by Id
     *
     * @param Request $request
     * @return ResponseBootstrap
     */
    public function getProduct(Request $request):ResponseBootstrap
    {
        return $this->response;
    }

}