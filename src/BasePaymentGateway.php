<?php
/**
 * @link https://github.com/yii2-vn/payment
 * @copyright Copyright (c) 2017 Yii2VN
 * @license [New BSD License](http://www.opensource.org/licenses/bsd-license.php)
 */


namespace yii2vn\payment;

use Yii;

use yii\base\Component;
use yii\base\InvalidArgumentException;
use yii\base\InvalidConfigException;
use yii\httpclient\Client as HttpClient;

/**
 * @package yii2vn\payment
 *
 * @property HttpClient $httpClient
 * @property MerchantInterface $defaultMerchant
 * @property MerchantInterface $merchant
 *
 * @author Vuong Minh <vuongxuongminh@gmail.com>
 * @since 1.0
 */
abstract class BasePaymentGateway extends Component implements PaymentGatewayInterface
{

    public $checkoutBankInstanceClass;

    public $checkoutTelCardInstanceClass;

    private $_merchants = [];

    /**
     * @param bool $load
     * @return array|MerchantInterface[]
     * @throws InvalidConfigException
     */
    public function getMerchants($load = true): array
    {
        if ($load) {
            $merchants = [];
            foreach ($this->_merchants as $id => $merchant) {
                $merchants[$id] = $this->getMerchant($id);
            }
        } else {
            return $this->_merchants;
        }
    }

    /**
     * @param array|MerchantInterface[] $merchants
     * @return bool
     */
    public function setMerchants(array $merchants): bool
    {
        foreach ($merchants as $id => $merchant) {
            $this->setMerchant($id, $merchant);
        }

        return true;
    }

    /**
     * @param null|string|int $id
     * @return mixed|MerchantInterface
     * @throws InvalidConfigException
     */
    public function getMerchant($id = null): MerchantInterface
    {
        if ($id === null) {
            return $this->getDefaultMerchant();
        } elseif ((is_string($id) || is_int($id)) && isset($this->_merchants[$id])) {
            $merchant = $this->_merchants[$id];

            if (is_string($merchant)) {
                $merchant = ['class' => $merchant, 'paymentGateway' => $this];
            } elseif (is_array($merchant)) {
                $merchant['paymentGateway'] = $this;
            }

            if (!$merchant instanceof MerchantInterface) {
                return $this->_merchants[$id] = Yii::createObject($merchant);
            } else {
                return $merchant;
            }
        } else {
            throw new InvalidArgumentException('Only accept get merchant via string or int key type!');
        }
    }

    /**
     * @param $id
     * @param null $merchant
     * @return bool
     */
    public function setMerchant($id, $merchant = null): bool
    {
        if ($merchant === null) {
            $this->_merchants[] = $id;
        } else {
            $this->_merchants[$id] = $merchant;
        }

        return true;
    }


    /**
     * @var null|MerchantInterface
     */
    private $_defaultMerchant;

    /**
     * @return MerchantInterface|BaseMerchant
     * @throws InvalidConfigException
     */
    public function getDefaultMerchant(): MerchantInterface
    {
        if (!$this->_defaultMerchant) {
            $merchantIds = array_keys($this->_merchants);
            if ($merchantId = reset($merchantIds)) {
                return $this->_defaultMerchant = $this->getMerchant($merchantId);
            } else {
                throw new InvalidConfigException('Can not get default merchant on an empty array merchants list!');
            }
        } else {
            return $this->_defaultMerchant;
        }
    }

    /**
     * @param array|string|CheckoutInstanceInterface $instance
     * @param string $method
     * @return CheckoutResponseDataInterface|bool
     * @throws InvalidConfigException
     */
    public function checkout($instance, string $method = self::CHECKOUT_METHOD_INTERNET_BANKING)
    {
        $instance = $this->prepareCheckoutInstance($instance, $method);

        $event = Yii::createObject([
            'class' => CheckoutEvent::class,
            'instance' => $instance,
            'method' => $method,
        ]);

        $this->trigger(self::EVENT_BEFORE_CHECKOUT, $event);

        if ($event->isValid) {
            $responseData = $this->checkoutInternal($info, $method);
            $event->responseData = $responseData;
            $this->trigger(self::EVENT_AFTER_CHECKOUT, $event);
            return $responseData;
        } else {
            return false;
        }
    }

    /**
     * @param CheckoutInstanceInterface $instance
     * @param string $method
     * @return CheckoutResponseDataInterface
     */
    abstract protected function checkoutInternal(CheckoutInstanceInterface $instance, string $method): CheckoutResponseDataInterface;

    /**
     * @param array|string|CheckoutInstanceInterface $instance
     * @param string $method
     * @return object|CheckoutInstanceInterface
     * @throws InvalidConfigException
     */
    protected function prepareCheckoutInstance($instance, string $method): CheckoutInstanceInterface
    {
        if ($instance instanceof CheckoutInstanceInterface) {
            return $instance;
        } elseif (is_array($instance) && !isset($instance['class'])) {
            if ($method === self::CHECKOUT_METHOD_TEL_CARD) {
                $class = $this->checkoutTelCardInstanceClass;
            } else {
                $class = $this->checkoutBankInstanceClass;
            }
            $instance['class'] = $class;
        }

        return Yii::createObject($instance);
    }

    /**
     * @var HttpClient|null
     */
    private $_httpClient;

    /**
     * @return HttpClient
     * @throws InvalidConfigException
     */
    public function getHttpClient()
    {
        if (!$this->_httpClient) {
            $this->setHttpClient([]);
        }

        return $this->_httpClient;
    }

    /**
     * @param $client
     * @throws InvalidConfigException
     */
    public function setHttpClient($client)
    {
        if (is_string($client)) {
            $client = ['class' => $client];
        } elseif (is_array($client) && !isset($client['class'])) {
            $client['class'] = HttpClient::class;
        }

        if (!$client instanceof HttpClient) {
            /** @var HttpClient $client */
            $client = Yii::createObject($client);
        }

        $client->baseUrl = $this->getBaseUrl();
        $this->_httpClient = $client;
    }
}