<?php
namespace App\Esc;

class EscException extends \RuntimeException
{
    public function __construct($message = null, $sentMessage = null)
    {
        if ($message instanceof EscMessage) {
            $error = $message->getError();
            $text = $error["text"];
            $code = $error["code"];
            if ($sentMessage instanceof EscMessage) {
                $text = "$text\nrequest:\n{$sentMessage->getText()}";
            }
            parent::__construct($text, $code);
        } else {
            parent::__construct($message, 0);
        }
    }
}
