<?php
namespace Gt\Session;

use DirectoryIterator;

class FileHandler extends Handler {
	const EMPTY_PHP_ARRAY = "a:0:{}";
	protected string $path;
	/** @var array<string, mixed> $cache */
	protected array $cache;

	/**
	 * @link http://php.net/manual/en/sessionhandlerinterface.open.php
	 * @param string $savePath The path where to store/retrieve the session.
	 * @param string $name The session name.
	 */
	public function open(string $savePath, string $name):bool {
		$success = true;

		$savePath = str_replace(
			["/", "\\"],
			DIRECTORY_SEPARATOR,
			$savePath
		);

		$this->path = implode(DIRECTORY_SEPARATOR, [
			$savePath,
			$name,
		]);

		if(!is_dir($this->path)) {
			$success = mkdir($this->path, 0775, true);
		}

		return $success;
	}

	/** @link http://php.net/manual/en/sessionhandlerinterface.close.php */
	public function close():bool {
		return true;
	}

	/** @link http://php.net/manual/en/sessionhandlerinterface.read.php */
	public function read(string $sessionId):string {
		if(isset($this->cache[$sessionId])) {
			return $this->cache[$sessionId];
		}

		$filePath = $this->getFilePath($sessionId);

		if(!file_exists($filePath)) {
			return "";
		}

		$this->cache[$sessionId] = file_get_contents($filePath) ?: "";
		return $this->cache[$sessionId];
	}

	/** @link http://php.net/manual/en/sessionhandlerinterface.write.php */
	public function write(string $sessionId, string $sessionData):bool {
		if($sessionData === self::EMPTY_PHP_ARRAY) {
			return true;
		}
		$filePath = $this->getFilePath($sessionId);
		$bytesWritten = file_put_contents($filePath, $sessionData);
		return $bytesWritten !== false;
	}

	/** @link http://php.net/manual/en/sessionhandlerinterface.destroy.php */
	public function destroy(string $id = ""):bool {
		$filePath = $this->getFilePath($id);

		if(file_exists($filePath)) {
			return unlink($filePath);
		}

		return true;
	}

	/** @link http://php.net/manual/en/sessionhandlerinterface.gc.php */
	public function gc(int $maxLifeTime):int|false {
		$now = time();
		$expired = $now - $maxLifeTime;
		$num = 0;

		foreach(new DirectoryIterator($this->path) as $fileInfo) {
			if(!$fileInfo->isFile()) {
				continue;
			}

			$lastModified = $fileInfo->getMTime();
			if($lastModified < $expired) {
				if(!unlink($fileInfo->getPathname())) {
					return false;
				}
				$num++;
			}
		}

		return $num;
	}

	protected function getFilePath(string $id):string {
		return implode(DIRECTORY_SEPARATOR, [
			$this->path,
			$id,
		]);
	}
}
