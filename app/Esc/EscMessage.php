<?php

namespace App\Esc;

// TODO; this should be extracted to esc as part of composer build

/**
 *
 * Message allows to construct and parse messages use by ESC wallet
 *
 */
class EscMessage
{
    private $data = [];

    public function __set($key, $value)
    {
        $this->data[$key] = $value;
    }

    public function &__get($key)
    {
        return $this->data[$key];
    }

    public function __isset($key)
    {
        return isset($this->data[$key]);
    }

    public function __unset($key)
    {
        unset($this->data[$key]);
    }

    public function getHeader()
    {
        return $this->data['run'];
    }

    public function setHeader($name)
    {
        $this->data['run'] = $name;
    }

    public function setData(array $params)
    {
        foreach ($params as $name => $value) {
            $this->data[$name] = $value;
        }
    }

    public function getData()
    {
        return $this->data;
    }

    public function setError($text, $code)
    {
        $this->data["error"] = [
            "code" => $code,
            "text" => $text,
        ];
    }

    public function getError()
    {
        return $this->data["error"] ?? null;
    }

    public function getText()
    {
        return json_encode($this->data);
    }

    public static function createFromText($message_text)
    {
        $messages = [];
        $lines = explode("\n", $message_text);

        foreach ($lines as $line) {
            if (!$line) {
                continue;
            }
            $msg = new ApiMessage();
            $msg->data = json_decode($line, true);
            if (json_last_error() != JSON_ERROR_NONE) {
                throw new ApiException("Json error: ". json_last_error_msg(), 400);
            }
            $messages[] = $msg;
        }

        if (!$messages) {
            throw new ApiException("Empty response", 500);
        }
        return $messages;
    }
}
