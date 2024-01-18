<?php

namespace PixelPenguin\Moneris;

use GuzzleHttp\Client;

/**
 * PixelPenguin\Moneris\Gateway
 *
 * @property bool $avs
 * @property-read array $avsCodes
 * @property bool $cvd
 * @property-read array $cvdCodes
 * @property-read string $environment
 * @property-read string $id
 * @property-read string $token
 * @property \PixelPenguin\Moneris\Transaction $transaction
 * @property bool $cof
 */
class Gateway
{
    use Gettable, Settable;

    /**
     * Determine if we will use the Address Verification Service.
     *
     * @var bool
     */
    protected $avs = false;

    /**
     * @var array
     */
    protected $avsCodes = ['A', 'B', 'D', 'M', 'P', 'W', 'X', 'Y', 'Z'];

    /**
     * Determine if we will use the Card Validation Digits.
     *
     * @var bool
     */
    protected $cvd = false;

    /**
     * @var array
     */
    protected $cvdCodes = ['M', 'Y', 'P', 'S', 'U'];

    /**
     * The environment used for connecting to the Moneris API.
     *
     * @var string
     */
    protected $environment;

    /**
     * The Moneris Store ID.
     *
     * @var string
     */
    protected $id;

    /**
     * The Moneris API Token.
     *
     * @var string
     */
    protected $token;

    /**
     * The current transaction.
     *
     * @var \PixelPenguin\Moneris\Transaction
     */
    protected $transaction;

    /**
     * Determine if we will use Credential On File.
     *
     * @var bool
     */
    protected $cof = false;

    /**
     * Create a new Moneris instance.
     *
     * @param string $id
     * @param string $token
     * @param string $environment
     *
     * @return void
     */
    public function __construct($id = '', $token = '', $environment = '')
    {
        $this->id = $id;
        $this->token = $token;
        $this->environment = $environment;
    }

    /**
     * Capture a pre-authorized a transaction.
     *
     * @param \PixelPenguin\Moneris\Transaction|string $transaction
     * @param string|null $order
     * @param mixed|null $amount
     *
     * @return \PixelPenguin\Moneris\Response
     */
    public function capture($transaction, $order = null, $amount = null)
    {
        if ($transaction instanceof Transaction) {
            $order = $transaction->order();
            $amount = !is_null($amount) ? $amount : $transaction->amount();
            $transaction = $transaction->number();
        }

        $params = [
            'type' => 'completion',
            'crypt_type' => Crypt::SSL_ENABLED_MERCHANT,
            'comp_amount' => $amount,
            'txn_number' => $transaction,
            'order_id' => $order,
        ];

        $transaction = $this->transaction($params);

        return $this->process($transaction);
    }

    /**
     * Create a new Vault instance.
     *
     * @return \PixelPenguin\Moneris\Vault
     */
    public function cards()
    {
        $vault = new Vault($this->id, $this->token, $this->environment);

        if (isset($this->avs)) {
            $vault->avs = boolval($this->avs);
        }

        if (isset($this->cvd)) {
            $vault->cvd = boolval($this->cvd);
        }

        if (isset($this->cof)) {
            $vault->cof = boolval($this->cof);
        }

        return $vault;
    }

    /**
     * Pre-authorize a purchase.
     *
     * @param array $params
     *
     * @return \PixelPenguin\Moneris\Response
     */
    public function preauth(array $params = [])
    {
        $params = array_merge($params, [
            'type' => 'preauth',
            'crypt_type' => Crypt::SSL_ENABLED_MERCHANT,
        ]);

        $transaction = $this->transaction($params);

        return $this->process($transaction);
    }

    /**
     * Make a purchase.
     *
     * @param array $params
     *
     * @return \PixelPenguin\Moneris\Response
     */
    public function purchase(array $params = [])
    {
        $params = array_merge($params, [
            'type' => 'purchase',
            'crypt_type' => Crypt::SSL_ENABLED_MERCHANT,
        ]);

        $transaction = $this->transaction($params);

        return $this->process($transaction);
    }

    /**
     * Process a transaction through the Moneris API.
     *
     * @param \PixelPenguin\Moneris\Transaction $transaction
     *
     * @return \PixelPenguin\Moneris\Response
     */
    protected function process(Transaction $transaction)
    {
        $processor = new Processor(new Client());

        return $processor->process($transaction);
    }

    /**
     * Refund a transaction.
     *
     * @param \PixelPenguin\Moneris\Transaction|string $transaction
     * @param string|null $order
     * @param mixed|null $amount
     *
     * @return \PixelPenguin\Moneris\Response
     */
    public function refund($transaction, $order = null, $amount = null)
    {
        if ($transaction instanceof Transaction) {
            $order = $transaction->order();
            $amount = !is_null($amount) ? $amount : $transaction->amount();
            $transaction = $transaction->number();
        }

        $params = [
            'type' => 'refund',
            'crypt_type' => Crypt::SSL_ENABLED_MERCHANT,
            'amount' => $amount,
            'txn_number' => $transaction,
            'order_id' => $order,
        ];

        $transaction = $this->transaction($params);

        return $this->process($transaction);
    }

    /**
     * Get or create a new Transaction instance.
     *
     * @param array|null $params
     *
     * @return \PixelPenguin\Moneris\Transaction
     */
    protected function transaction(array $params = null)
    {
        if (is_null($this->transaction) || !is_null($params)) {
            return $this->transaction = new Transaction($this, $params);
        }

        return $this->transaction;
    }

    /**
     * Validate CVD and/or AVS prior to attempting a purchase.
     *
     * @param array $params
     *
     * @return \PixelPenguin\Moneris\Response
     */
    public function verify(array $params = [])
    {
        $params = array_merge($params, [
            'type' => 'card_verification',
            'crypt_type' => Crypt::SSL_ENABLED_MERCHANT,
        ]);

        $transaction = $this->transaction($params);

        return $this->process($transaction);
    }

    /**
     * Void a transaction.
     *
     * @param \PixelPenguin\Moneris\Transaction|string $transaction
     * @param string|null $order
     *
     * @return \PixelPenguin\Moneris\Response
     */
    public function void($transaction, $order = null)
    {
        if ($transaction instanceof Transaction) {
            $order = $transaction->order();
            $transaction = $transaction->number();
        }

        $params = [
            'type' => 'purchasecorrection',
            'crypt_type' => Crypt::SSL_ENABLED_MERCHANT,
            'txn_number' => $transaction,
            'order_id' => $order,
        ];

        $transaction = $this->transaction($params);

        return $this->process($transaction);
    }
}
