<?php

namespace BB\Mailchimp;
class Mailchimp_Ecomm {
    public function __construct(Mailchimp $master) {
        $this->master = $master;
    }

    /**
     * Import Ecommerce Order Information to be used for Segmentation. This will generally be used by ecommerce package plugins
<a href="http://connect.mailchimp.com/category/ecommerce" target="_blank">provided by us or by 3rd part system developers</a>.
     * @param associative_array $order
     *     - id string the Order Id
     *     - campaign_id string optional the Campaign Id to track this order against (see the "mc_cid" query string variable a campaign passes)
     *     - email_id string optional (kind of) the Email Id of the subscriber we should attach this order to (see the "mc_eid" query string variable a campaign passes) - required if campaign_id is passed, otherwise either this or <strong>email</strong> is required. If both are provided, email_id takes precedence
     *     - email string optional (kind of) the Email Address we should attach this order to - either this or <strong>email_id</strong> is required. If both are provided, email_id takes precedence
     *     - total double The Order Total (ie, the full amount the customer ends up paying)
     *     - order_date string optional the date of the order - if this is not provided, we will default the date to now. Should be in the format of 2012-12-30
     *     - shipping double optional the total paid for Shipping Fees
     *     - tax double optional the total tax paid
     *     - store_id string a unique id for the store sending the order in (32 bytes max)
     *     - store_name string optional a "nice" name for the store - typically the base web address (ie, "store.mailchimp.com"). We will automatically update this if it changes (based on store_id)
     *     - items array structs for each individual line item including:
     *         - line_num int optional the line number of the item on the order. We will generate these if they are not passed
     *         - product_id int the store's internal Id for the product. Lines that do no contain this will be skipped
     *         - sku string optional the store's internal SKU for the product. (max 30 bytes)
     *         - product_name string the product name for the product_id associated with this item. We will auto update these as they change (based on product_id) (max 500 bytes)
     *         - category_id int (required) the store's internal Id for the (main) category associated with this product. Our testing has found this to be a "best guess" scenario
     *         - category_name string (required) the category name for the category_id this product is in. Our testing has found this to be a "best guess" scenario. Our plugins walk the category heirarchy up and send "Root - SubCat1 - SubCat4", etc.
     *         - qty double optional the quantity of the item ordered - defaults to 1
     *         - cost double optional the cost of a single item (ie, not the extended cost of the line) - defaults to 0
     * @return associative_array with a single entry:
     *     - complete bool whether the call worked. reallistically this will always be true as errors will be thrown otherwise.
     */
    public function orderAdd($order) {
        $_params = array("order" => $order);
        return $this->master->call('ecomm/order-add', $_params);
    }

    /**
     * Delete Ecommerce Order Information used for segmentation. This will generally be used by ecommerce package plugins
<a href="/plugins/ecomm360.phtml">that we provide</a> or by 3rd part system developers.
     * @param string $store_id
     * @param string $order_id
     * @return associative_array with a single entry:
     *     - complete bool whether the call worked. reallistically this will always be true as errors will be thrown otherwise.
     */
    public function orderDel($store_id, $order_id) {
        $_params = array("store_id" => $store_id, "order_id" => $order_id);
        return $this->master->call('ecomm/order-del', $_params);
    }

    /**
     * Retrieve the Ecommerce Orders for an account
     * @param string $cid
     * @param int $start
     * @param int $limit
     * @param string $since
     * @return associative_array the total matching orders and the specific orders for the requested page
     *     - total int the total matching orders
     *     - data array structs for each order being returned
     *         - store_id string the store id generated by the plugin used to uniquely identify a store
     *         - store_name string the store name collected by the plugin - often the domain name
     *         - order_id string the internal order id the store tracked this order by
     *         - email string the email address that received this campaign and is associated with this order
     *         - order_total double the order total
     *         - tax_total double the total tax for the order (if collected)
     *         - ship_total double the shipping total for the order (if collected)
     *         - order_date string the date the order was tracked - from the store if possible, otherwise the GMT time we received it
     *         - items array structs for each line item on this order.:
     *             - line_num int the line number
     *             - product_id int the product id
     *             - product_name string the product name
     *             - product_sku string the sku for the product
     *             - product_category_id int the category id for the product
     *             - product_category_name string the category name for the product
     *             - qty int the quantity ordered
     *             - cost double the cost of the item
     */
    public function orders($cid=null, $start=0, $limit=100, $since=null) {
        $_params = array("cid" => $cid, "start" => $start, "limit" => $limit, "since" => $since);
        return $this->master->call('ecomm/orders', $_params);
    }

}


