<?php

namespace ekinhbayar\SMSBroadcast;

class SMSBroadcast
{
    /**
     * API endpoint URL.
     */
    private $api_endpoint = 'https://api.smsbroadcast.com.au/api-adv.php';

    /**
     * API account username.
     *
     * Your SMS Broadcast username.
     * This is the same username that you would use to login to the SMS Broadcast website.
     *
     */
    private $username = '';

    /**
     * API account password.
     *
     * Your SMS Broadcast password.
     * This is the same password that you would use to login to the SMS Broadcast website.
     *
     */
    private $password = '';

    /**
     * The sender ID for the messages.
     *
     * Can be a mobile number or letters, up to 11 characters.
     * Should not contain punctuation or spaces.
     * Leave blank to use SMS Broadcast's 2-way number.
     *
     */
    private $sender = '';

    /**
     * Array of recipient phone numbers.
     *
     * The numbers can be in the format:
     *   - 04xxxxxxxx (Australian format)
     *   - 614xxxxxxxx (International format without a preceding +)
     *   - 4xxxxxxxx (missing leading 0)
     *
     * SMS Broadcast recommends using the international format,
     * but your messages will be accepted in any of the above formats.
     * The recipients should contain only numbers, with no spaces or other characters.
     *
     */
    public $recipients = [];

    /**
     * The content of the SMS message.
     *
     * Must not be longer than 160 characters unless the maxsplit parameter is used.
     * Must be URL encoded.
     *
     */
    public $message = '';

    /**
     * Maximum length of the SMS.
     *
     * Long SMS Messages (maxsplit)
     *
     * Standard SMS messages are limited to 160 characters, however our system
     * allows you to send SMS messages up to 765 characters. This is achieved by
     * splitting the message into parts. Each part is a normal SMS and is charged
     * at the normal price. The SMS is then reconstructed by the receiving mobile
     * phone and should display as a single SMS.
     *
     * This setting determines how many times you are willing to split the message.
     * This allows you to control the maximum cost and length of each message.
     * The default setting is 1 (160 characters).
     * The maximum is 5 (765 characters).
     *
     * If your SMS is 160 characters or less, it will be sent (and cost) as a
     * single SMS, regardless of the value of this setting.
     *
     * If your message is longer than 160 characters, it is split into parts of up
     * to 153 characters (not 160).
     *
     * When null, the class automagically determines the value in the send() method.
     *
     */
    public $maxsplit = null;

    /**
     * Your reference number for the message to help you track the message status.
     * This parameter is optional and can be up to 20 characters.
     */
    public $ref = '';

    /**
     * SMSBroadcast constructor.
     *
     * @param array $configuration
     */
    public function __construct(array $configuration)
    {
        $this->setAuthenticationCredentials($configuration);
    }

    /**
     * @param array $configuration
     *
     * @throws \ekinhbayar\SMSBroadcast\InvalidCredentialsException
     */
    public function setAuthenticationCredentials(array $configuration)
    {
        if (!isset($configuration['username'])) {
            throw new InvalidCredentialsException('Username is not Specified.');
        }

        if (!isset($configuration['password'])) {
            throw new InvalidCredentialsException('Password is not Specified.');
        }

        if (isset($configuration['sender_name'] )) {
            $this->setSenderName($configuration['sender_name']);
        }

        $this->username = $configuration['username'];
        $this->password = $configuration['password'];
    }

    /**
     * @param string $senderName
     */
    public function setSenderName(/*string*/ $senderName)
    {
        if (!is_string($senderName)) {
            throw new \InvalidArgumentException('Sender name must be a string.');
        }

        $this->sender = $senderName;
    }

    /**
     * @param string $number
     */
    public function addRecipient(/*string*/ $number)
    {
        if (!is_string($number)) {
            throw new \InvalidArgumentException('Recipient mobile number must be a string.');
        }

        $this->recipients[] = $number;
    }

    /**
     * @param int $message_length
     *
     * @return int
     */
    public function checkMessageLength(/*int*/ $message_length)
    {
        if ($this->maxsplit) return $this->maxsplit;

        return ($message_length > Limits::MAX_CHARS_PER_MESSAGE_SINGLE)
            ? (int) ceil($message_length / Limits::MAX_CHARS_PER_MESSAGE_MULTI)
            : 1;
    }

    /**
     * @param array $recipients
     *
     * @throws \ekinhbayar\SMSBroadcast\SmsDeliveryException
     */
    public function validateRecipients(array $recipients)
    {
        if (!$recipients) {
            throw new SmsDeliveryException('No valid recipients were specified.', 1);
        }
    }

    /**
     * @param string $sender
     *
     * @throws \ekinhbayar\SMSBroadcast\SmsDeliveryException
     */
    public function validateSenderString(/*string*/ $sender)
    {
        if (strlen($sender) > Limits::MAX_CHARS_SENDER) {
            throw new SmsDeliveryException('From string length must be less or equal to 11 characters.', 2);
        }
    }

    /**
     * @param int $multipart
     *
     * @throws \ekinhbayar\SMSBroadcast\SmsDeliveryException
     */
    public function checkMultiPartLength(/*int*/ $multipart)
    {
        if ($multipart > Limits::MAX_SMS_PER_MULTIPART) {
            throw new SmsDeliveryException('Cannot send a multi-part message longer than 7 SMSes.', 3);
        }
    }

    /**
     * @param array $data
     */
    public function validateData(array $data)
    {
        foreach ($data as $key => $value) {
            switch ($key) {
                case 'to':
                    $this->validateRecipients($value);
                    break;

                case 'from':
                    $this->validateSenderString($value);
                    break;

                case 'multisplit':
                    $this->checkMultiPartLength($value);
                    break;
            }
        }
    }

    /**
     * Prepare actual data to be POSTed from the given array.
     *
     * @param array $data
     *
     * @return string
     *   URL encoded POST data.
     */
    protected function prepareData(array $data) {

        foreach ($data as $key => $value) {
            if ($key === 'to') {
                # Support multiple recipients.
                $data[$key] = implode(',', array_unique($value));
            }
        }

        return http_build_query($data, "", "&", PHP_QUERY_RFC3986);
    }

    /**
     * Execute cURL POST request for API calls.
     *
     * @param string $data
     *   Data to be POSTed.
     *
     * @return string $gatewayResponse
     *   Raw SMS Broadcast gateway response.
     */
    protected function request(/*string*/ $data) {
        $handle = curl_init($this->api_endpoint);
        curl_setopt($handle, CURLOPT_POST, TRUE);
        curl_setopt($handle, CURLOPT_POSTFIELDS, $data);
        curl_setopt($handle, CURLOPT_RETURNTRANSFER, TRUE);
        $gatewayResponse = curl_exec($handle);
        curl_close($handle);

        return $gatewayResponse;
    }

    /**
     * @param array $gatewayResponse
     *
     * @return array
     */
    public function formatGatewayResponse(array $gatewayResponse)
    {
        $result = [];

        foreach ($gatewayResponse as $index => $line) {
            list($status, $receiving_number, $response) = $line;

            $result[$index] = [
                'status'           => trim($status),
                'receiving_number' => trim($receiving_number),
                'response'         => trim($response),
            ];
        }

        return $result;
    }

    /**
     * @param string $result
     *
     * @return array
     */
    public function buildGatewayResponse(/*string*/ $result)
    {
        $gatewayResponse = [];

        $lines = explode("\n", $result);
        foreach (array_filter($lines) as $index => $line) {
            $line = trim($line);
            $gatewayResponse[$index] = explode(':', $line);
        }

        return $gatewayResponse;
    }

    /**
     * Send a request to SMS gateway.
     *
     * @param array $data
     *   Data to POST to SMS Broadcast endpoint.
     *
     * @return array
     *   Response from SMS gateway.
     *
     * @throws \ekinhbayar\SMSBroadcast\GatewayRequestException
     */
    public function requestFromGateway(array $data) {
        $data = $this->prepareData($data);
        $result = $this->request($data);

        list($status, $response) = explode(':', $result);

        if ($status === 'ERROR') {
            throw new GatewayRequestException('There was an error with this request: ' . $response);
        }

        return $this->buildGatewayResponse($result);
    }

    /**
     * Send SMS.
     *
     * @return array
     *   Response from SMS Broadcast API.
     *
     *   status: Will show if your messages have been accepted by the API. There are 3 possible results:
     *     - OK : This message was accepted.
     *     - BAD:  This message was invalid. (eg, invalid phone number)
     *     - ERROR:  The request failed. (eg, wrong username / password or missing a required parameter)
     *
     *   receiving_number: The receiving mobile number.
     *     This will be shown in international format (614xxxxxxxx) regardless of the format it was submitted in.
     *     If you submit an invalid number, the invalid number will be shown in the same format as it was submitted.
     *
     *   response: Will display our reference number for the SMS message, or the reason for a failed SMS message.
     */
    public function send() {

        $data = [
            'username' => $this->username,
            'password' => $this->password,
            'to'       => $this->recipients,
            'from'     => $this->sender,
            'message'  => $this->message,
            'ref'      => $this->ref,
        ];

        $message_length = strlen($data['message']);

        # Automagically detect if the maxsplit setting is required by checking message length.
        $data['maxsplit'] = $this->checkMessageLength($message_length);

        $this->validateData($data);

        $gatewayResponse = $this->requestFromGateway($data);

        return $this->formatGatewayResponse($gatewayResponse);
    }

    /**
     * Checks the account SMS balance.
     *
     * @return int
     */
    public function checkBalance() {
        $data = [
            'username' => $this->username,
            'password' => $this->password,
            'action'   => 'balance',
        ];

        $response = $this->requestFromGateway($data);
        list(, $balance) = array_values($response);

        return (int) $balance;
    }
}
