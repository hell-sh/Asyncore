<?php
namespace Asyncore;
use Exception;
/**
 * @since 2.2
 */
class Server
{
	/**
	 * @var Loop $loop
	 */
	private $loop;
	/**
	 * The streams the server listens for new connections on.
	 *
	 * @var array<resource> $streams
	 */
	public $streams;
	/**
	 * Contains clients that are still pending the TLS handshake.
	 *
	 * @var array $handshaking_clients
	 */
	public $handshaking_clients = [];
	/**
	 * @var callable $client_handler
	 */
	public $client_handler;

	/**
	 * @param array<resource> $streams The streams the server listens for new connections on.
	 * @see Server::createStream()
	 */
	public function __construct(array $streams = [])
    {
		$this->streams = $streams;
		$this->loop = Asyncore::add(function()
		{
			if($this->client_handler)
			{
				$this->onTick();
			}
		});
    }

	function __destruct()
	{
		$this->loop->remove();
	}

	/**
	 * Creates a stream for a server to listen for new connections on.
	 *
	 * @param string $address e.g. "0.0.0.0:80"
	 * @param string|null $public_key_file Path to the file containing your PEM-encoded public key or null if you don't want encryption
	 * @param string|null $private_key_file Path to the file containing your PEM-encoded private key or null if you don't want encryption
	 * @return resource
	 * @throws Exception
	 * @see https://en.wikipedia.org/wiki/Privacy-Enhanced_Mail
	 */
	public static function createStream(string $address, $public_key_file = null, $private_key_file = null)
	{
		if($public_key_file && $private_key_file)
		{
			$stream = stream_socket_server("tcp://".$address, $errno, $errstr, STREAM_SERVER_BIND | STREAM_SERVER_LISTEN, stream_context_create([
				"ssl" => [
					"verify_peer" => false,
					"verify_peer_name" => false,
					"allow_self_signed" => true,
					"local_cert" => $public_key_file,
					"local_pk" => $private_key_file,
					"ciphers" => "ECDHE-ECDSA-AES128-GCM-SHA256:ECDHE-RSA-AES128-GCM-SHA256:ECDHE-ECDSA-AES256-GCM-SHA384:ECDHE-RSA-AES256-GCM-SHA384:ECDHE-ECDSA-CHACHA20-POLY1305:ECDHE-RSA-CHACHA20-POLY1305:DHE-RSA-AES128-GCM-SHA256:DHE-RSA-AES256-GCM-SHA384"
				]
			]));
		}
		else
		{
			$stream = stream_socket_server("tcp://".$address, $errno, $errstr);
		}
		if(!is_resource($stream))
		{
			throw new Exception($errstr);
		}
		return $stream;
	}

	/**
	 * Sets the function to be called when a client has connected and finished the TLS handshake, if applicable.
	 *
	 * @param callable $client_handler
	 */
	public function onClient(callable $client_handler): void
	{
		$this->client_handler = $client_handler;
	}

	private function onTick(): void
	{
		// Accept
		foreach($this->streams as $stream)
		{
			while(($client = @stream_socket_accept($stream, 0)) !== false)
			{
				stream_set_blocking($client, false);
				if(array_key_exists("ssl", stream_context_get_options($client)))
				{
					array_push($this->handshaking_clients, $client);
				}
				else
				{
					($this->client_handler)($client);
				}
			}
		}
		// Handle
		foreach($this->handshaking_clients as $i => $client)
		{
			$ret = stream_socket_enable_crypto($client, true, STREAM_CRYPTO_METHOD_ANY_SERVER);
			if($ret === 0)
			{
				continue;
			}
			if($ret !== true)
			{
				fclose($client);
			}
			else
			{
				($this->client_handler)($client);
			}
			unset($this->handshaking_clients[$i]);
		}
	}
}
