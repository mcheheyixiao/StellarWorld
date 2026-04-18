<?php
declare(strict_types=1);

namespace Core;

final class RconClient
{
    private string $host;
    private int $port;
    private string $password;
    private float $timeout;

    public function __construct(string $host, int $port, string $password, float $timeout = 3.0)
    {
        $this->host = $host;
        $this->port = $port;
        $this->password = $password;
        $this->timeout = $timeout;
    }

    public function sendCommand(string $command): string
    {
        $fp = @fsockopen($this->host, $this->port, $errno, $errstr, $this->timeout);
        if (!is_resource($fp)) {
            throw new \RuntimeException('RCON connect failed: ' . $errno . ' ' . $errstr);
        }

        stream_set_timeout($fp, (int)max(1, ceil($this->timeout)));
        $requestId = random_int(1, PHP_INT_MAX);

        try {
            $this->writePacket($fp, $requestId, 3, $this->password);
            $authResp = $this->readPacket($fp);
            if (!is_array($authResp) || (int)($authResp['id'] ?? -1) !== $requestId) {
                throw new \RuntimeException('RCON authentication failed');
            }

            $this->writePacket($fp, $requestId, 2, $command);
            $cmdResp = $this->readPacket($fp);
            if (!is_array($cmdResp)) {
                throw new \RuntimeException('RCON command response is empty');
            }

            return (string)($cmdResp['body'] ?? '');
        } finally {
            fclose($fp);
        }
    }

    private function writePacket($fp, int $id, int $type, string $body): void
    {
        $payload = pack('V', $id) . pack('V', $type) . $body . "\x00\x00";
        $packet = pack('V', strlen($payload)) . $payload;
        $written = fwrite($fp, $packet);
        if (!is_int($written) || $written !== strlen($packet)) {
            throw new \RuntimeException('RCON write failed');
        }
    }

    /**
     * @return array{id:int,type:int,body:string}|null
     */
    private function readPacket($fp): ?array
    {
        $lenRaw = fread($fp, 4);
        if (!is_string($lenRaw) || strlen($lenRaw) !== 4) {
            return null;
        }
        $len = unpack('Vlen', $lenRaw);
        $packetLen = (int)($len['len'] ?? 0);
        if ($packetLen < 10 || $packetLen > 8192) {
            return null;
        }

        $packet = '';
        while (strlen($packet) < $packetLen) {
            $chunk = fread($fp, $packetLen - strlen($packet));
            if (!is_string($chunk) || $chunk === '') {
                break;
            }
            $packet .= $chunk;
        }
        if (strlen($packet) !== $packetLen) {
            return null;
        }

        $idArr = unpack('Vid', substr($packet, 0, 4));
        $typeArr = unpack('Vtype', substr($packet, 4, 4));
        $body = substr($packet, 8, -2);

        return [
            'id' => (int)($idArr['id'] ?? -1),
            'type' => (int)($typeArr['type'] ?? -1),
            'body' => is_string($body) ? $body : '',
        ];
    }
}
