<?php
/**
 * Created by PhpStorm.
 * User: mirza
 * Date: 7/17/18
 * Time: 12:56 PM
 */

namespace Model\Mapper;

use Model\Entity\Order;
use PDO;
use PDOException;
use Component\DataMapper;

class StripeMapper extends DataMapper
{

    // get configuration
    public function getConfiguration()
    {
        return $this->configuration;
    }


    public function getAllOrdersData(){

        try {

            // set database instructions
            $sql = "SELECT 
                      o.name AS customer_name,
                      o.street AS customer_street,
                      o.city AS customer_city,
                      o.postal_code AS customer_postal_code,
                      o.customer_email,
                      o.country AS customer_country,
                      o.currency,
                      
                      FORMAT(((o.amount - o.shipping_amount - o.tax_amount) / 100), 2) AS subtotal,
                      FORMAT((o.shipping_amount / 100), 2) AS shipping,
                      FORMAT((o.tax_amount / 100), 2) AS tax, 
                      FORMAT((o.amount / 100), 2) AS total,
                    
                      o.date AS order_date,
                      o.shipped,
                      o.shipping_method,
                      o.paid,
                      GROUP_CONCAT(oi.name) AS product_names,
                      GROUP_CONCAT(oi.quantity) AS quantities
                    FROM orders AS o 
                    LEFT JOIN order_items AS oi ON o.id = oi.order_id
                    GROUP BY oi.order_id";
            $statement = $this->connection->prepare($sql);
            $statement->execute();

            $data = $statement->fetchAll(PDO::FETCH_ASSOC);


        }catch (PDOException $e){

            $data = [];
            // die($e->getMessage());
        }

        return $data;
    }


    public function writeOrderTransaction(Order $order){

        try {
            // set database instructions
            $sql = "INSERT INTO orders 
                      (order_id, charge_id, customer_email, name, street, postal_code, city, country, currency, amount, shipping_amount, tax_amount, shipping_method, paid)
                    VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)";
            $statement = $this->connection->prepare($sql);
            $statement->execute([
                $order->getOrderId(),
                $order->getChargeId(),
                $order->getCustomerEmail(),
                $order->getName(),
                $order->getStreet(),
                $order->getPostalCode(),
                $order->getCity(),
                $order->getCountry(),
                $order->getCurrency(),
                $order->getAmount(),
                $order->getShippingAmount(),
                $order->getTaxAmount(),
                $order->getShippingMethod(),
                $order->getPaid()
            ]);

            if($statement->rowCount() > 0){

                // get parent id
                $parentId = $this->connection->lastInsertId();

                // get order items
                $items = $order->getOrderItems();
                $counter = 0;

                $sqlItem = "INSERT INTO order_items 
                          (name, quantity, order_id)
                        VALUES (?,?,?)";
                $statementItem = $this->connection->prepare($sqlItem);

                foreach($items as $item){
                    $statementItem->execute([
                        $item['name'],
                        $item['quantity'],
                        $parentId
                    ]);

                    $counter++;
                }

            }

        }catch (PDOException $e){

        }
    }
}