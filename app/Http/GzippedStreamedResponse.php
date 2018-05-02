<?php
namespace App\Http;

use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * StreamedResponse represents a streamed HTTP response.
 *
 * A StreamedResponse uses a callback for its content.
 *
 * The callback should use the standard PHP functions like echo
 * to stream the response back to the client. The flush() method
 * can also be used if needed.
 *
 * @see flush()
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class GzippedStreamedResponse extends StreamedResponse
{
    public function sendContent()
    {
        ob_start(function ($output) {
            header('Content-Length: ' . strlen($output));
            return false;
        });
        if (function_exists('ob_gzhandler')) {
            ob_start("ob_gzhandler");
        }
        parent::sendContent();

        ob_end_flush();
        if (function_exists('ob_gzhandler')) {
            ob_end_flush();
        }
    }
}
