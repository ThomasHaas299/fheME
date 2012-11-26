<?php

/*
 * Copyright (c) 2012, Matthias Bolte (matthias@tinkerforge.com)
 *
 * Redistribution and use in source and binary forms of this file,
 * with or without modification, are permitted.
 */

namespace Tinkerforge;


class Base58
{
    private static $alphabet = '123456789abcdefghijkmnopqrstuvwxyzABCDEFGHJKLMNPQRSTUVWXYZ';

    /**
     * Encode string from Base10 to Base58.
     *
     * \param $value Base10 encoded string
     * \returns Base58 encoded string
     */
    public static function encode($value)
    {
        $encoded = '';

        while (bccomp($value, '58') >= 0) {
            $div = bcdiv($value, '58');
            $mod = bcmod($value, '58');
            $encoded = self::$alphabet[intval($mod)] . $encoded;
            $value = $div;
        }

        return self::$alphabet[intval($value)] . $encoded;
    }

    /**
     * Decode string from Base58 to Base10.
     *
     * \param $encoded Base58 encoded string
     * \returns Base10 encoded string
     */
    public static function decode($encoded)
    {
        $length = strlen($encoded);
        $value = '0';
        $base = '1';

        for ($i = $length - 1; $i >= 0; $i--)
        {
            $index = strval(strpos(self::$alphabet, $encoded[$i]));
            $value = bcadd($value, bcmul($index, $base));
            $base = bcmul($base, '58');
        }

        return $value;
    }
}


class Base256
{
    /**
     * Encode from Base10 string to Base256 array.
     *
     * \param $value Base10 encoded string
     * \returns array of bytes (little endian)
     */
    public static function encode($value, $length)
    {
        $bytes = array();

        while (bccomp($value, '256') >= 0) {
            $div = bcdiv($value, '256');
            $mod = bcmod($value, '256');
            array_push($bytes, intval($mod));
            $value = $div;
        }

        array_push($bytes, intval($value));

        return array_pad($bytes, $length, 0);
    }

    public static function encodeAndPack($value, $length)
    {
        $bytes = self::encode($value, $length);
        $packed = '';

        foreach ($bytes as $byte) {
            $packed .= pack('C', $byte);
        }

        return $packed;
    }

    /**
     * Decode from Base256 array to Base10 string.
     *
     * \param $bytes array of bytes (little endian)
     * \returns Base10 encoded string
     */
    public static function decode($bytes)
    {
        $value = '0';
        $base = '1';

        foreach ($bytes as $byte) {
            $value = bcadd($value, bcmul(strval($byte), $base));
            $base = bcmul($base, '256');
        }

        return $value;
    }
}


class TimeoutException extends \Exception
{

}


abstract class Device
{
    public $uid = '0'; # Base10
    public $stackID = 0;
    public $expectedName = '';
    public $name = '';
    public $firmwareVersion = array(0, 0, 0);
    public $bindingVersion = array(0, 0, 0);

    public $ipcon = NULL;

    public $expectedResponseFunctionID = 0;
    public $expectedResponseLength = 0;
    public $receivedResponsePayload = NULL;

    public $registeredCallbacks = array();
    public $callbackWrappers = array();
    public $pendingCallbacks = array();

    public function __construct($uid)
    {
        $this->uid = Base58::decode($uid);
    }

    /**
     * Returns the name (including the hardware version), the firmware version
     * and the binding version of the device. The firmware and binding versions
     * are given in arrays of size 3 with the syntax (major, minor, revision).
     *
     * The returned array contains name, firmwareVersion and bindingVersion.
     */
    public function getVersion()
    {
        return array('name' => $this->name,
                     'firmwareVersion' => $this->firmwareVersion,
                     'bindingVersion' => $this->bindingVersion);
    }

    /**
     * @internal
     */
    public function dispatchCallbacks()
    {
        $pendingCallbacks = $this->pendingCallbacks;
        $this->pendingCallbacks = array();

        foreach ($pendingCallbacks as $pendingCallback) {
            $this->handleCallback($pendingCallback[0], $pendingCallback[1]);
        }
    }

    /**
     * @internal
     */
    protected function sendRequestNoResponse($functionID, $payload)
    {
        if ($this->ipcon == NULL) {
            throw new \Exception('Not added to IPConnection');
        }

        $header = pack('CCv', $this->stackID, $functionID, 4 + strlen($payload));
        $request = $header . $payload;

        $this->expectedResponseFunctionID = 0;
        $this->expectedResponseLength = 0;
        $this->receivedResponsePayload = NULL;

        $this->ipcon->send($request);
    }

    /**
     * @internal
     */
    protected function sendRequestExpectResponse($functionID, $payload,
                                                 $expectedResponsePayloadLength)
    {
        if ($this->ipcon == NULL) {
            throw new \Exception('Not added to IPConnection');
        }

        $header = pack('CCv', $this->stackID, $functionID, 4 + strlen($payload));
        $request = $header . $payload;

        $this->expectedResponseFunctionID = $functionID;
        $this->expectedResponseLength = 4 + $expectedResponsePayloadLength;
        $this->receivedResponsePayload = NULL;

        $this->ipcon->send($request);
        $this->ipcon->receive(IPConnection::RESPONSE_TIMEOUT, $this, FALSE);

        if ($this->receivedResponsePayload == NULL) {
            throw new TimeoutException('Did not receive response in time');
        }

        $payload = $this->receivedResponsePayload;

        $this->expectedResponseFunctionID = 0;
        $this->expectedResponseLength = 0;
        $this->receivedResponsePayload = NULL;

        return $payload;
    }
}


class IPConnection
{
    const RESPONSE_TIMEOUT = 2.5;

    const BROADCAST_ADDRESS = 0;

    const FUNCTION_GET_STACK_ID = 255;
    const FUNCTION_ENUMERATE = 254;
    const FUNCTION_ENUMERATE_CALLBACK = 253;

    private $socket = FALSE;
    private $pendingData = '';
    private $devices = array();
    private $pendingAddDevice = NULL;
    private $enumerateCallback = NULL;

    /**
     * Creates an IP connection to the Brick Daemon with the given *$host*
     * and *$port*. With the IP connection itself it is possible to enumerate the
     * available devices. Other then that it is only used to add Bricks and
     * Bricklets to the connection.
     *
     * @param string $host
     * @param int $port
     */
    public function __construct($host, $port)
    {
        $address = '';

        if (preg_match('/^\d+\.\d+\.\d+\.\d+$/', $host) == 0) {
            $address = gethostbyname($host);

            if ($address == $host) {
                throw new \Exception('Could not resolve hostname');
            }
        } else {
            $address = $host;
        }

        $this->socket = @socket_create(AF_INET, SOCK_STREAM, SOL_TCP);

        if ($this->socket === FALSE) {
            throw new \Exception('Could not create socket: ' .
                                 socket_strerror(socket_last_error()));
        }

        if (!@socket_connect($this->socket, $address, $port)) {
            $error = socket_strerror(socket_last_error($this->socket));

            socket_close($this->socket);
            $this->socket = FALSE;

            throw new \Exception('Could not connect socket: ' . $error);
        }
    }

    function __destruct()
    {
        $this->destroy();
    }

    /**
     * This method registers a callback with the signature:
     *
     *  void callback(string $uid, string $name, int $stackID, bool $isNew)
     *
     * that receives four parameters:
     *
     * - *$uid* - The UID of the device.
     * - *$name* - The name of the device (includes "Brick" or "Bricklet" and a version number).
     * - *$stackID* - The stack ID of the device (you can find out the position in a stack with this).
     * - *$isNew* - True if the device is added, false if it is removed.
     *
     * There are three different possibilities for the callback to be called.
     * Firstly, the callback is called with all currently available devices in the
     * IP connection (with *$isNew* true). Secondly, the callback is called if
     * a new Brick is plugged in via USB (with *$isNew* true) and lastly it is
     * called if a Brick is unplugged (with *$isNew* false).
     *
     * It should be possible to implement "plug 'n play" functionality with this
     * (as is done in Brick Viewer).
     *
     * You need to call IPConnection::dispatchCallbacks() in order to receive
     * the callbacks. The recommended dispatch time is 2.5s.
     *
     * @param callable $callback
     *
     * @return void
     */
    public function enumerate($callback)
    {
        $this->enumerateCallback = $callback;

        $request = pack('CCv', self::BROADCAST_ADDRESS, self::FUNCTION_ENUMERATE, 4);

        $this->send($request);
    }

    /**
     * Adds a device (Brick or Bricklet) to the IP connection. Every device
     * has to be added to an IP connection before it can be used. Examples for
     * this can be found in the API documentation for every Brick and Bricklet.
     *
     * @param Device $device
     *
     * @return void
     */
    public function addDevice($device)
    {
        $uid = Base256::encodeAndPack($device->uid, 8);
        $request = pack('CCv', self::BROADCAST_ADDRESS, self::FUNCTION_GET_STACK_ID, 12) . $uid;

        $this->pendingAddDevice = $device;

        $this->send($request);
        $this->receive(self::RESPONSE_TIMEOUT, NULL, FALSE);

        if ($this->pendingAddDevice != NULL) {
            $this->pendingAddDevice = NULL;
            throw new TimeoutException('Could not add device ' . Base58::encode($device->uid) . ', timeout');
        }

        $device->ipcon = $this;
    }

    /**
     * Dispatches incoming callbacks for the given amount of time (negative value
     * means infinity). Because PHP doesn't support threads you need to call this
     * method periodically to ensure that incoming callbacks are handled. If you
     * don't use callbacks you don't need to call this method.
     *
     * @param float $seconds
     *
     * @return void
     */
    public function dispatchCallbacks($seconds)
    {
        // Dispatch all pending callbacks
        foreach ($this->devices as $device) {
            $device->dispatchCallbacks();
        }

        if ($seconds < 0) {
            while (TRUE) {
                $this->receive(self::RESPONSE_TIMEOUT, NULL, TRUE);

                // Dispatch all pending callbacks that were received by getters in the meantime
                foreach ($this->devices as $device) {
                    $device->dispatchCallbacks();
                }
            }
        } else {
            $this->receive($seconds, NULL, TRUE);
        }
    }

    /**
     * @internal
     */
    public function receive($seconds, $device, $directCallbackDispatch)
    {
        if ($seconds < 0) {
            $seconds = 0;
        }

        $start = microtime(true);
        $end = $start + $seconds;

        do {
            $read = array($this->socket);
            $write = NULL;
            $except = NULL;
            $timeout = $end - microtime(true);

            if ($timeout < 0) {
                $timeout = 0;
            }

            $timeout_sec = floor($timeout);
            $timeout_usec = ceil(($timeout - $timeout_sec) * 1000000);
            $changed = @socket_select($read, $write, $except, $timeout_sec, $timeout_usec);

            if ($changed === FALSE) {
                throw new \Exception('Could not receive response: ' .
                                     socket_strerror(socket_last_error($this->socket)));
            } else if ($changed > 0) {
                $data = '';
                $length = @socket_recv($this->socket, $data, 8192, 0);

                if ($length === FALSE) {
                    throw new \Exception('Could not receive response: ' .
                                         socket_strerror(socket_last_error($this->socket)));
                }

                $isAddingDevice = $this->pendingAddDevice != NULL;

                $before = microtime(true);

                $this->pendingData .= $data;

                while (TRUE) {
                    if (strlen($this->pendingData) < 4) {
                        // Wait for complete header
                        break;
                    }

                    $header = unpack('CstackID/CfunctionID/vlength', $this->pendingData);
                    $length = $header['length'];

                    if (strlen($this->pendingData) < $length) {
                        // Wait for complete packet
                        break;
                    }

                    $packet = substr($this->pendingData, 0, $length);
                    $this->pendingData = substr($this->pendingData, $length);
                    $this->handleResponse($packet, $directCallbackDispatch);
                }

                $after = microtime(true);

                if ($after > $before) {
                    $end += $after - $before;
                }

                if (($isAddingDevice && $this->pendingAddDevice == NULL) ||
                    ($device != NULL && $device->expectedResponseLength > 0 &&
                     $device->receivedResponsePayload != NULL)) {
                    break;
                }
            }

            $now = microtime(true);
        } while ($now >= $start && $now < $end);
    }

    /**
     * Destroys the IP connection. The socket to the Brick Daemon will be closed
     * and the threads of the IP connection terminated.
     *
     * @return void
     */
    public function destroy()
    {
        if ($this->socket === FALSE) {
            return;
        }

        @socket_shutdown($this->socket, 2);
        @socket_close($this->socket);

        $this->socket = FALSE;
    }

    /**
     * @internal
     */
    public function send($request)
    {
        if (@socket_send($this->socket, $request, strlen($request), 0) === FALSE) {
            throw new \Exception('Could not send request: ' .
                                 socket_strerror(socket_last_error($this->socket)));
        }
    }

    /**
     * @internal
     */
    private function handleResponse($packet, $directCallbackDispatch)
    {
        $header = unpack('CstackID/CfunctionID/vlength', $packet);
        $payload = substr($packet, 4);

        if ($header['functionID'] == self::FUNCTION_GET_STACK_ID) {
            $this->handleAddDevice($header, $payload);
            return;
        } else if ($header['functionID'] == self::FUNCTION_ENUMERATE_CALLBACK) {
            $this->handleEnumerate($header, $payload);
            return;
        }

        if (!array_key_exists($header['stackID'], $this->devices)) {
            // Response from an unknown device, ignoring it
            return;
        }

        $device = $this->devices[$header['stackID']];

        if ($device->expectedResponseFunctionID == $header['functionID']) {
            if ($device->expectedResponseLength != $header['length']) {
                error_log('Received malformed packet from ' .
                          $header['stackID'] . ', ignoring it');
                return;
            }

            $device->receivedResponsePayload = $payload;
            return;
        }

        if (array_key_exists($header['functionID'], $device->registeredCallbacks)) {
            if ($directCallbackDispatch) {
                $device->handleCallback($header, $payload);
            } else {
                array_push($device->pendingCallbacks, array($header, $payload));
            }

            return;
        }

        // Response seems to be OK, but can't be handled, most likely
        // a callback without registered callback function
    }

    /**
     * @internal
     */
    private function handleAddDevice($header, $payload)
    {
        if ($this->pendingAddDevice == NULL) {
            return;
        }

        $payload = unpack('C8uid/C3firmwareVersion/c40name/CstackID', $payload);

        // uid
        $uid = Base256::decode(self::collectUnpackedArray($payload, 'uid', 8));

        if ($this->pendingAddDevice->uid != $uid) {
            return;
        }

        // firmware version
        $this->pendingAddDevice->firmwareVersion =
                self::collectUnpackedArray($payload, 'firmwareVersion', 3);

        // name
        $name = self::implodeUnpackedString($payload, 'name', 40);
        $i = strrpos($name, ' ');

        if ($i === FALSE || str_replace('-', ' ', substr($name, 0, $i)) != str_replace('-', ' ', $this->pendingAddDevice->expectedName)) {
            return;
        }

        $this->pendingAddDevice->name = $name;

        // stack ID
        $this->pendingAddDevice->stackID = $payload['stackID'];

        $this->devices[$this->pendingAddDevice->stackID] = $this->pendingAddDevice;
        $this->pendingAddDevice = NULL;
    }

    /**
     * @internal
     */
    private function handleEnumerate($header, $payload)
    {
        if ($this->enumerateCallback == NULL) {
            return;
        }

        $payload = unpack('C8uid/c40name/CstackID/CisNew', $payload);

        $uid = Base256::decode(self::collectUnpackedArray($payload, 'uid', 8));
        $name = self::implodeUnpackedString($payload, 'name', 40);
        $stackID = $payload['stackID'];
        $isNew = (bool)$payload['isNew'];

        call_user_func_array($this->enumerateCallback,
                             array(Base58::encode($uid), $name, $stackID, $isNew));
    }

    /**
     * @internal
     */
    static public function fixUnpackedInt16($value)
    {
        if ($value >= 32768) {
            $value -= 65536;
        }

        return $value;
    }

    /**
     * @internal
     */
    static public function fixUnpackedInt32($value)
    {
        if (bccomp($value, '2147483648') >= 0) {
            $value = bcsub($value, '4294967296');
        }

        return $value;
    }

    /**
     * @internal
     */
    static public function fixUnpackedUInt32($value)
    {
        if (bccomp($value, 0) < 0) {
            $value = bcadd($value, '4294967296');
        }

        return $value;
    }

    /**
     * @internal
     */
    static public function collectUnpackedInt16Array($payload, $field, $length)
    {
        $result = array();

        for ($i = 1; $i <= $length; $i++) {
            array_push($result, self::fixUnpackedInt16($payload[$field . $i]));
        }

        return $result;
    }

    /**
     * @internal
     */
    static public function collectUnpackedInt32Array($payload, $field, $length)
    {
        $result = array();

        for ($i = 1; $i <= $length; $i++) {
            array_push($result, self::fixUnpackedInt32($payload[$field . $i]));
        }

        return $result;
    }

    /**
     * @internal
     */
    static public function collectUnpackedUInt32Array($payload, $field, $length)
    {
        $result = array();

        for ($i = 1; $i <= $length; $i++) {
            array_push($result, self::fixUnpackedUInt32($payload[$field . $i]));
        }

        return $result;
    }

    /**
     * @internal
     */
    static public function collectUnpackedBoolArray($payload, $field, $length)
    {
        $result = array();

        for ($i = 1; $i <= $length; $i++) {
            array_push($result, (bool)$payload[$field . $i]);
        }

        return $result;
    }

    /**
     * @internal
     */
    static public function implodeUnpackedString($payload, $field, $length)
    {
        $result = array();

        for ($i = 1; $i <= $length; $i++) {
            $c = $payload[$field . $i];

            if ($c == 0) {
                break;
            }

            array_push($result, chr($c));
        }

        return implode($result);
    }

    /**
     * @internal
     */
    static public function collectUnpackedCharArray($payload, $field, $length)
    {
        $result = array();

        for ($i = 1; $i <= $length; $i++) {
            array_push($result, chr($payload[$field . $i]));
        }

        return $result;
    }

    /**
     * @internal
     */
    static public function collectUnpackedArray($payload, $field, $length)
    {
        $result = array();

        for ($i = 1; $i <= $length; $i++) {
            array_push($result, $payload[$field . $i]);
        }

        return $result;
    }
}

?>
