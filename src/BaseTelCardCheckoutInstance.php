<?php
/**
 * @link https://github.com/yii2-vn/payment
 * @copyright Copyright (c) 2017 Yii2VN
 * @license [New BSD License](http://www.opensource.org/licenses/bsd-license.php)
 */

namespace yii2vn\payment;

/**
 * Class BaseTelCardCheckoutInstance
 *
 * @author Vuong Minh <vuongxuongminh@gmail.com>
 * @since 1.0
 */
abstract class BaseTelCardCheckoutInstance extends BaseCheckoutInstance
{

    public $provider;

    public $serial;

    public $pinCode;

}