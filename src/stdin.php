<?php /** @noinspection PhpUnused */
namespace pas;
use BadMethodCallException;
use RuntimeException;
abstract class stdin
{
	private static $initialized = false;
	private static $proc;
	private static $pipes;

	/**
	 * Initializes pas's STDIN handling, if it wasn't already, enabling the "stdin_line" event and the stdin::getNextLine() function.
	 * After this, STDIN is in pas's hands, and there's no way out.
	 *
	 * @param callable|null $line_function The function to be called when the user has submitted a line.
	 * @return void
	 */
	static function init(?callable $line_function = null): void
	{
		if($line_function !== null)
		{
			pas::on("stdin_line", $line_function);
		}
		if(self::$initialized)
		{
			return;
		}
		if(pas::isWindows())
		{
			self::openProcess();
		}
		else
		{
			stream_set_blocking(STDIN, false);
		}
		pas::add(function()
		{
			while(self::hasLine())
			{
				pas::fire("stdin_line", [self::getLine()]);
			}
		}, 0.1, true);
		self::$initialized = true;
	}

	private static function openProcess(): void
	{
		self::$proc = proc_open("SET /P pas_input= & SET pas_input", [
			0 => STDIN,
			1 => [
				"pipe",
				"w"
			],
			2 => [
				"pipe",
				"w"
			]
		], self::$pipes);
		if(!is_resource(self::$proc))
		{
			throw new RuntimeException("Failed to start Windows input process");
		}
	}

	private static function hasLine(): bool
	{
		if(pas::isWindows())
		{
			return !proc_get_status(self::$proc)["running"];
		}
		else
		{
			$read = [STDIN];
			$null = [];
			return stream_select($read, $null, $null, 0) === 1;
		}
	}

	private static function getLine(): string
	{
		if(pas::isWindows())
		{
			if(!self::hasLine())
			{
				return null;
			}
			$res = trim(substr(stream_get_contents(self::$pipes[1]), 10));
			self::openProcess();
			return $res;
		}
		return trim(fgets(STDIN));
	}

	/**
	 * Blocks until the user has submitted a line and then returns it.
	 *
	 * @throws BadMethodCallException if pas\stdin was not initialized via pas\stdin::init()
	 * @return string
	 */
	static function getNextLine(): string
	{
		if(!self::$initialized)
		{
			throw new BadMethodCallException("pas\stdin was not initialized via pas\stdin::init()");
		}
		while(!self::hasLine())
		{
			usleep(100000);
		}
		return self::getLine();
	}
}
