<?php

// TODO : recommended extraction to esc repository and composer setup for composer require usage with build of esc client

namespace App\Esc;

use Symfony\Component\Process\ProcessBuilder;
use Symfony\Component\Process\Process;
use Symfony\Component\Process\InputStream;

/**
 *
 * Wrapper class used to interact with ESC wallet client
 *
 */
class Esc
{
    private $walletCommand;

    private $workingDir = '.';

    private $address;

    private $secret;

    private $host;

    private $port;

    private $my_account;

    /* args
     * --pk      ==> pkey *
     * --msid    ==> msid
     * --bank
     * --user    ==> account *
     * --ha  | hash of previous tx
     * --sk
     * --pn
     * --addr    ==> host*
     * --port    ==> port*
     */
    public function __construct($walletCommand, $workingDir, $address, $secret, $host, $port)
    {
        $this->walletCommand = $walletCommand;
        $this->workingDir = $workingDir;
        $this->address = $address;
        $this->secret = $secret;
        $this->host = $host;
        $this->port = $port;
    }

    private function getProcess()
    {
        $builder = new ProcessBuilder();
        if ($this->workingDir) {
            $builder->setWorkingDirectory($this->workingDir);
            if (!file_exists($this->workingDir)) {
                mkdir($this->workingDir, 0775, true);
            }
        }

        $args = [
            //'--secret=' . $this->secret,
            '--address=' . $this->address,
            '--host=' . $this->host,
            '--port=' . $this->port,
            '--nice=0',
        ];
        if ($this->my_account) {
            $args[] = '--hash=' . $this->my_account['hash'];
            $args[] = '--msid=' . $this->my_account['msid'];
        }
        $builder->setPrefix($this->walletCommand)
            ->setArguments($args)
            ->setTimeout(30);

        return $builder->getProcess();
    }

    private function executeCommand(EscMessage $message)
    {
        $process = $this->getProcess();

//         die($process->getCommandLine());
        $input = new InputStream();

        $process->setInput($input);
        $process->start();

        $input->write("{$this->secret}\n");
        $input->write($message->getText());
        $input->close();

        $process->wait();

//         echo("{$this->secret}\n");
//         echo($message->getText() . "\n");
//         die($process->getCommandLine());

        if ($process->getExitCode()) {
            throw new EscException($process->getErrorOutput());
        }

        try {
            $messages = EscMessage::createFromText($process->getOutput());
        } catch (\Exception $e) {
            throw new EscException($process->getOutput() ."\n". $process->getErrorOutput());
        }
        if ($messages[0]->error) {
            throw new EscException($messages[0], $message);
        }
        return $messages;
    }

    public function getAccount($address = null)
    {
        $payload = new EscMessage();
        $payload->setHeader("get_account");
        if ($address) {
            $payload->address = $address;
        }
        $response = $this->executeCommand($payload);
        if ($address === null || $address == $this->address) {
            $this->my_account = $response[0]->account;
        }
        return $response[0];
    }

    public function getLog($type, $inout, $from)
    {
        $payload = new EscMessage();
        $payload->setHeader("get_log");

        if ($type !== null) {
            $payload->type = $type;
        }

        if ($inout !== null) {
            $payload->inout = $inout;
        }
        if ($from !== null) {
            $payload->from = $from;
        }

        $reply = $this->executeCommand($payload)[0];

        // FIXME: should be done in wallet
        if ($type !== null || $inout !== null) {
            $types = (array)$type;
            $logs = [];
            foreach ($reply->log as $log) {
                if (($type === null || in_array($log['type'], $types)) && ($inout === null || ($log['inout'] ?? null) == $inout)) {
                    $logs[] = $log;
                }
            }
            $reply->log = $logs;
        }
        return $reply;
    }

    public function getBroadcastLog($from)
    {
        $payload = new EscMessage();
        $payload->setHeader("get_broadcast");
//         $payload->from = $from;

        return $this->executeCommand($payload)[0];
    }

    public function sendBroadcast($message)
    {
        if (!$this->my_account) {
            $this->getAccount();
        }
        $cmd = new EscMessage();
        $cmd->setHeader('broadcast');
        $cmd->message = bin2hex($message);
        return $this->executeCommand($cmd)[0];
    }

    public function sendMany(array $recipients)
    {
        if (!$this->my_account) {
            $this->getAccount();
        }
        $cmd = new EscMessage();
        $cmd->setHeader('send_many');
        $cmd->wires = $recipients;
        ;
        return $this->executeCommand($cmd)[0];
    }

    public static function normalizeAddress($address)
    {
        $x = preg_replace('/[^0-9A-FX]+/', '', strtoupper($address));
        if (strlen($x) != 16) {
            throw new \RuntimeException("Invalid adshares address");
        }
        return sprintf("%s-%s-%s", substr($x, 0, 4), substr($x, 4, 8), substr($x, 12, 4));
    }

    public static function normalizeTxid($txid)
    {
        $x = preg_replace('/[^0-9A-F]+/', '', strtoupper($txid));
        if (strlen($x) != 16) {
            throw new \RuntimeException("Invalid adshares address");
        }
        return sprintf("%s:%s:%s", substr($x, 0, 4), substr($x, 4, 8), substr($x, 12, 4));
    }
}
